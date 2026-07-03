<?php
// seeders/generate_images.php

define('APPLICATION_ROOT', dirname(__DIR__));

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

use Zero\Core\App;
use Zero\Core\Env;

Env::load(APPLICATION_ROOT);
$apiKey = Env::get('GEMINI_API_KEY');

if (empty($apiKey)) {
    echo "Error: GEMINI_API_KEY is not configured in your .env file.\n";
    exit(1);
}

$prompts = [
    'cyberpunk-netrunner-deck.jpg' => "A high-contrast cyberpunk netrunner deck sitting on a brutalist concrete console with glowing cyan cables, photorealistic, 8k resolution, cinematic lighting, neon cyan accents, detailed tech hardware",
    'retro-futuristic-hologram.jpg' => "A retro-futuristic dark neon holographic projection of a cyber-vest module, glowing neon pink and cyan vector lines, hovering over a sleek metal pad, tech blueprint style, dark background"
];

$outputDir = APPLICATION_ROOT . '/seeders/data/generated-images';
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

foreach ($prompts as $filename => $prompt) {
    echo "Initializing Google Imagen 4.0 API query for: '{$filename}'...\n";
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-generate-001:predict?key=" . urlencode($apiKey);
    $payload = [
        'instances' => [['prompt' => $prompt]],
        'parameters' => [
            'sampleCount' => 1,
            'aspectRatio' => '1:1',
            'outputMimeType' => 'image/jpeg'
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!empty($curlError)) {
        echo "Error executing network handshake: {$curlError}\n";
        continue;
    }

    if ($httpCode !== 200) {
        echo "Google Imagen API Error (HTTP {$httpCode}): {$response}\n";
        continue;
    }

    $resData = json_decode($response, true);
    $base64Data = $resData['predictions'][0]['bytesBase64Encoded'] ?? '';

    if (empty($base64Data)) {
        echo "Error: Base64 data not found in response payload.\n";
        continue;
    }

    $imageBytes = base64_decode($base64Data);
    $targetPath = $outputDir . '/' . $filename;
    
    file_put_contents($targetPath, $imageBytes);
    echo "Successfully saved generated image: {$targetPath} (" . round(strlen($imageBytes)/1024, 2) . " KB)\n";
}

echo "All image generation handshakes concluded successfully!\n";
