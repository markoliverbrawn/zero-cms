<?php
// tests/AiImageTest.php
// Unit tests for the new AI Image Generation capability inside AiService

require_once __DIR__ . '/bootstrap.php';

use Zero\Services\AiService;
use Zero\Services\Ai\Providers\MockProvider;

echo "=== AI Image Generation Component Tests ===\n";

// Force load the mock provider for absolute offline-safety during test suite execution
$mock = new MockProvider();
$ref = new \ReflectionClass(AiService::class);
$prop = $ref->getProperty('providerInstance');
$prop->setAccessible(true);
$prop->setValue(null, $mock);

// Test 1: Verification of registration and interface
echo "Testing AiService image generation capability...\n";
assert_test(method_exists(AiService::class, 'generateImage'), "AiService has generateImage method implemented");

// Test 2: Generate mock image
echo "Testing mock image generation...\n";
$prompt = "A tactical cyberpunk utility belt";
$base64Data = AiService::generateImage($prompt);

assert_test(!empty($base64Data), "AiService successfully returns image data");
$decoded = base64_decode($base64Data);
assert_test(strpos($decoded, '<svg') !== false, "Decoded image bytes represent a valid SVG vector placeholder");
assert_test(strpos($decoded, $prompt) !== false, "Generated mock image successfully embeds prompt text: '{$prompt}'");

// Test 3: Failure propagation
echo "Testing simulated provider exception propagation...\n";
try {
    AiService::generateImage($prompt, ['should_fail' => true]);
    assert_test(false, "Failing provider should have thrown an exception");
} catch (\Exception $e) {
    assert_test(strpos($e->getMessage(), "Simulated AI Image Provider Failure") !== false, "AiService propagates image generation failure messages correctly");
}

echo "AI Image Generation component tests completed successfully!\n\n";
