<?php
// seeders/generate_city.php

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

use Zero\Core\Env;

Env::load(APPLICATION_ROOT);
$apiKey = Env::get('GEMINI_API_KEY');

if (empty($apiKey)) {
    echo "Error: GEMINI_API_KEY is not configured in your .env file.\n";
    exit(1);
}

$prompt = "A breathtaking high-resolution retro-futuristic dark cyberpunk city skyline at night, massive brutalist concrete and dark metal monoliths, glowing neon pink and cyan vector lines, holographic blueprint projections, clean technical wireframe overlays, tiny hovering neon vehicles far in the background distance, absolutely no close-up giant cars, dark background, cinematic volumetric lighting, 8k resolution, photorealistic digital art";

$outputFile = APPLICATION_ROOT . '/public/assets/img/cybercity-bg.jpg';
$outputDir = dirname($outputFile);
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "Initializing Google Imagen 4.0 API query for: 'cybercity-bg.jpg'...\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-generate-001:predict?key=" . urlencode($apiKey);
$payload = [
    'instances' => [['prompt' => $prompt]],
    'parameters' => [
        'sampleCount' => 1,
        'aspectRatio' => '16:9', // Wide-aspect ratio perfect for bottom page backgrounds!
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
    exit(1);
}

if ($httpCode !== 200) {
    echo "Google Imagen API Error (HTTP {$httpCode}): {$response}\n";
    exit(1);
}

$resData = json_decode($response, true);
$base64Data = $resData['predictions'][0]['bytesBase64Encoded'] ?? '';

if (empty($base64Data)) {
    echo "Error: Base64 data not found in response payload.\n";
    exit(1);
}

$imageBytes = base64_decode($base64Data);
file_put_contents($outputFile, $imageBytes);

echo "Successfully saved generated image: {$outputFile} (" . round(strlen($imageBytes)/1024, 2) . " KB)\n";
echo "Handshake completed successfully!\n";
