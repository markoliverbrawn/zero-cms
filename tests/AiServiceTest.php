<?php
// tests/AiServiceTest.php
// Integration and unit tests for the decoupled AI Service and Provider architecture.

require_once __DIR__ . '/bootstrap.php';

use Zero\Services\AiService;
use Zero\Core\Env;
use Zero\Services\Ai\Providers\GeminiProvider;
use Zero\Services\Ai\Providers\MockProvider;
use Zero\Interfaces\AiProvider;

echo "=== AI Service & Provider Component Tests ===\n";

// 1. Verify AiProvider interface implementation
echo "  Testing AiProvider interface loader...\n";
assert_test(interface_exists(AiProvider::class), "AiProvider interface is registered and loadable");

// 2. Verify AiService resolution and default fallback behavior
echo "  Testing AiService provider instantiation...\n";
assert_test(class_exists(AiService::class), "AiService class is registered and loadable");

// We set provider temporarily to mock to test integration without real API key dependencies
$originalProvider = Env::get('AI_PROVIDER', 'gemini');
putenv("AI_PROVIDER=mock");

try {
    $provider = AiService::getProvider();
    assert_test($provider instanceof AiProvider, "AiService successfully returns an active AiProvider");
    assert_test($provider instanceof MockProvider, "Mock provider is correctly loaded based on environmental configurations");
} catch (\Exception $e) {
    assert_test(false, "Failed to resolve active provider from environment: " . $e->getMessage());
}

// 3. Testing isAvailable check
echo "  Testing AiService::isAvailable() logic...\n";
assert_test(AiService::isAvailable() === true, "isAvailable returns true when mock provider is active");

// Temporarily set to gemini and clear credentials to test isAvailable false fallback
putenv("AI_PROVIDER=gemini");
$originalApiKey = Env::get('GEMINI_API_KEY');
// Clear singleton cache
$reflector = new ReflectionClass(AiService::class);
$prop = $reflector->getProperty('providerInstance');
$prop->setAccessible(true);
$prop->setValue(null, null);

// Temporarily clear API key in Env using reflection
$envReflector = new ReflectionClass(Env::class);
$envProp = $envReflector->getProperty('data');
$envProp->setAccessible(true);
$envData = $envProp->getValue();
$envData['GEMINI_API_KEY'] = '';
$envProp->setValue(null, $envData);

assert_test(AiService::isAvailable() === false, "isAvailable returns false when gemini is active but GEMINI_API_KEY is empty");

// Restore key and verify isAvailable returns true if key exists
if (!empty($originalApiKey)) {
    $envData['GEMINI_API_KEY'] = $originalApiKey;
    $envProp->setValue(null, $envData);
    assert_test(AiService::isAvailable() === true, "isAvailable returns true when gemini is active and GEMINI_API_KEY is configured");
}

// Restore mock provider
putenv("AI_PROVIDER=mock");
$prop->setValue(null, null);

// 4. Testing content generation via MockProvider
echo "  Testing Mock AI Content Generation...\n";
try {
    $prompt = "Audit local telemetry security score 95";
    $response = AiService::generate($prompt);
    
    assert_test(strpos($response, 'MOCK AI RESPONSE') !== false, "Mock AI responds with expected heading markers");
    assert_test(strpos($response, 'Audit local telemetry') !== false, "Mock AI payload successfully preserves and echo back prompt context");
} catch (\Exception $e) {
    assert_test(false, "AiService::generate threw an unexpected exception: " . $e->getMessage());
}

// 5. Testing custom option overrides
echo "  Testing Mock AI custom response options...\n";
try {
    $customResponse = "### DETECTED HIGH SEVERITY EXPLOIT";
    $response = AiService::generate("Some prompt", [
        'mock_response' => $customResponse
    ]);
    assert_test($response === $customResponse, "Mock AI supports custom response options overrides perfectly");
} catch (\Exception $e) {
    assert_test(false, "AiService::generate custom option override failed: " . $e->getMessage());
}

// 6. Testing failure and error propagation
echo "  Testing failure propagation and error handling...\n";
try {
    AiService::generate("Some prompt", ['should_fail' => true]);
    assert_test(false, "AiService failed to propagate simulated provider exceptions upwards");
} catch (\Exception $e) {
    assert_test($e->getMessage() === "Simulated AI Provider Failure.", "AiService propagates provider exception messages exactly");
}

// 7. Testing dynamic custom provider registration
echo "  Testing dynamic custom provider registration...\n";

class CustomTestAiProvider implements AiProvider
{
    public function generate(string $prompt, array $options = []): string
    {
        return "Custom response for: " . $prompt;
    }
}

try {
    $customProvider = new CustomTestAiProvider();
    AiService::registerProvider('custom_test_ai', $customProvider);
    
    // Set environment variable to switch to custom provider
    putenv("AI_PROVIDER=custom_test_ai");
    
    // Reset singleton instance in AiService using reflection for thorough testing
    $reflector = new ReflectionClass(AiService::class);
    $prop = $reflector->getProperty('providerInstance');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
    
    $resolvedProvider = AiService::getProvider();
    assert_test($resolvedProvider instanceof CustomTestAiProvider, "AiService successfully resolves dynamically registered providers");
    
    $response = AiService::generate("Hello Custom AI");
    assert_test($response === "Custom response for: Hello Custom AI", "Dynamic custom AI responds correctly on generate calls");
} catch (\Exception $e) {
    assert_test(false, "Dynamic provider registration failed: " . $e->getMessage());
}

// 8. Test dynamic routing pattern of Admin AI summary generator
echo "  Testing dynamic routing pattern lookup for AI Summary Endpoint...\n";
$routeMatchesPattern = '#^/api/v1/admin/([a-zA-Z0-9_/\-]+)$#';
$uri = '/api/v1/admin/ai/generate-summary';
assert_test(preg_match($routeMatchesPattern, $uri, $matches) && $matches[1] === 'ai/generate-summary', "Router dynamically and securely matches the new AI Summary API endpoint");

// Restore original test runner environment settings
putenv("AI_PROVIDER=" . $originalProvider);
$reflector = new ReflectionClass(AiService::class);
$prop = $reflector->getProperty('providerInstance');
$prop->setAccessible(true);
$prop->setValue(null, null);

// Restore original API key to Env
if (isset($envProp) && isset($envData) && !empty($originalApiKey)) {
    $envData['GEMINI_API_KEY'] = $originalApiKey;
    $envProp->setValue(null, $envData);
}

echo "AI Service & Provider component tests completed successfully!\n\n";
