<?php
// seeders/rekey_kitchensink_media.php
// Script to cleanly rekey all media primary key IDs inside kitchensink.json and dynamically rewrite all references globally.

define('APPLICATION_ROOT', dirname(__DIR__));

// Register PSR-4 Namespace Autoloading
spl_autoload_register(function ($class) {
    $prefix = 'Zero\\';
    $base_dir = APPLICATION_ROOT . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Zero\Support\Security;

$path = APPLICATION_ROOT . '/seeders/data/kitchensink.json';
if (!file_exists($path)) {
    echo "Error: kitchensink.json not found!\n";
    exit(1);
}

$data = json_decode(file_get_contents($path), true);
if (!is_array($data)) {
    echo "Error: Failed to decode kitchensink.json.\n";
    exit(1);
}

echo "==================================================\n";
echo "REKEYING KITCHEN SINK MEDIA & ID REWRITING\n";
echo "==================================================\n";

$mediaIdMap = [];
$rekeyedMedia = [];

// 1. Rekey all media entries to have globally unique, non-colliding IDs
foreach (($data['media'] ?? []) as $media) {
    if (!isset($media['id'])) {
        $rekeyedMedia[] = $media;
        continue;
    }
    
    $oldId = $media['id'];
    $newId = Security::uuidv7();
    
    $mediaIdMap[$oldId] = $newId;
    
    $media['id'] = $newId;
    $rekeyedMedia[] = $media;
}
$data['media'] = $rekeyedMedia;
echo "--> Rekeyed " . count($rekeyedMedia) . " media records inside kitchensink.json.\n";

// Convert entire data object to JSON string for efficient global ID replacements
$rawJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// 2. Perform global string search-replace for all old IDs with their new unique UUIDv7s
$replacedCount = 0;
foreach ($mediaIdMap as $oldId => $newId) {
    $rawJson = str_replace($oldId, $newId, $rawJson);
    $replacedCount++;
}
echo "--> Rewrote {$replacedCount} media ID references globally across pages, blogs, and product galleries!\n";

$finalData = json_decode($rawJson, true);

// Save updated kitchensink.json back to disk
file_put_contents($path, json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n✅ Successfully completed kitchensink.json media rekeying!\n";
echo "==================================================\n";
