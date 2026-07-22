<?php
// seeders/rekey_kitchensink_all.php
// Script to cleanly rekey all shop categories, products, variants, and media primary keys inside kitchensink.json to prevent any duplicate key collisions.

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
echo "REKEYING ALL KITCHEN SINK DATA & RELATIONS\n";
echo "==================================================\n";

$idMap = [];

// 1. Rekey all Media entries
$rekeyedMedia = [];
foreach (($data['media'] ?? []) as $media) {
    if (!isset($media['id'])) {
        $rekeyedMedia[] = $media;
        continue;
    }
    $oldId = $media['id'];
    $newId = Security::uuidv7();
    $idMap[$oldId] = $newId;
    $media['id'] = $newId;
    $rekeyedMedia[] = $media;
}
$data['media'] = $rekeyedMedia;
echo "--> Rekeyed " . count($rekeyedMedia) . " media records.\n";

// 2. Rekey all Shop Categories
$rekeyedCats = [];
foreach (($data['shop_categories'] ?? []) as $cat) {
    if (!isset($cat['id'])) {
        $rekeyedCats[] = $cat;
        continue;
    }
    $oldId = $cat['id'];
    $newId = Security::uuidv7();
    $idMap[$oldId] = $newId;
    $cat['id'] = $newId;
    $rekeyedCats[] = $cat;
}
$data['shop_categories'] = $rekeyedCats;
echo "--> Rekeyed " . count($rekeyedCats) . " product categories.\n";

// 3. Rekey all Shop Products
$rekeyedProds = [];
foreach (($data['shop_products'] ?? []) as $prod) {
    if (!isset($prod['id'])) {
        $rekeyedProds[] = $prod;
        continue;
    }
    $oldId = $prod['id'];
    $newId = Security::uuidv7();
    $idMap[$oldId] = $newId;
    $prod['id'] = $newId;
    
    // Remap category_id if present in translation map
    if (!empty($prod['category_id']) && isset($idMap[$prod['category_id']])) {
        $prod['category_id'] = $idMap[$prod['category_id']];
    }
    
    $rekeyedProds[] = $prod;
}
$data['shop_products'] = $rekeyedProds;
echo "--> Rekeyed " . count($rekeyedProds) . " products.\n";

// 4. Rekey all Shop Product Variants
$rekeyedVariants = [];
foreach (($data['shop_product_variants'] ?? []) as $v) {
    if (!isset($v['id'])) {
        $rekeyedVariants[] = $v;
        continue;
    }
    $v['id'] = Security::uuidv7();
    
    // Remap product_id if present in translation map
    if (!empty($v['product_id']) && isset($idMap[$v['product_id']])) {
        $v['product_id'] = $idMap[$v['product_id']];
    }
    
    $rekeyedVariants[] = $v;
}
$data['shop_product_variants'] = $rekeyedVariants;
echo "--> Rekeyed " . count($rekeyedVariants) . " product variants.\n";

// Convert entire data object to JSON string for efficient global ID replacements
$rawJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// 5. Perform global string search-replace for all translated IDs (including images in pages/gallery/blogs, category link tables etc.)
$replacedCount = 0;
foreach ($idMap as $oldId => $newId) {
    $rawJson = str_replace($oldId, $newId, $rawJson);
    $replacedCount++;
}
echo "--> Rewrote {$replacedCount} ID references globally across the entire kitchensink.json dataset!\n";

$finalData = json_decode($rawJson, true);

// Save updated kitchensink.json back to disk
file_put_contents($path, json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n✅ Successfully completed full kitchensink.json rekeying!\n";
echo "==================================================\n";
