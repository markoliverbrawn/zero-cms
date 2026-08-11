<?php
// tests/I18nTest.php
// Unit tests for internationalization engine (Zero\Support\I18n)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\I18n;

echo "=== I18n Component Tests ===\n";

// 1. Initialize translation engine
echo "Initializing translation engine...\n";
$_SESSION = []; // Clear session language prefs so it defaults to 'en'
I18n::reset();

assert_test(I18n::getLang() === 'en', "Initial language defaults to English ('en')");

// 2. Test translate on simple and missing keys
echo "Testing standard translation...\n";
$welcomeEn = I18n::translate('welcome');
// 'welcome' key might be translated to some string. Let's check that if it is missing, it returns 'welcome' key or resolves correctly.
$testKey = I18n::translate('non_existent_key_123_xyz');
assert_test($testKey === 'non_existent_key_123_xyz', "Translating non-existent keys returns the key itself as a fallback");

// 3. Test shortcut alias t()
$welcomeAlias = I18n::t('welcome');
assert_test($welcomeEn === $welcomeAlias, "I18n::t() acts as a correct shortcut alias for I18n::translate()");

// 4. Test dynamic runtime registration (register)
echo "Testing custom modular translation registration...\n";
I18n::register('en', ['custom_hello' => 'Hello {name}!']);
$customHello = I18n::t('custom_hello', ['name' => 'Alice']);
assert_test($customHello === 'Hello Alice!', "Correctly replaces placeholder variables inside translations");

// 5. Test multi-language switching via mock session
echo "Testing language switching via session preferences...\n";
try {
    $reflector = new ReflectionClass(I18n::class);
    $langProp = $reflector->getProperty('currentLang');
    $langProp->setAccessible(true);
    $langProp->setValue(null, 'es'); // Force Spanish lang
    
    // Register Spanish-specific translation at runtime
    I18n::register('es', ['hola' => 'Hola {name}']);
    assert_test(I18n::t('hola', ['name' => 'Bob']) === 'Hola Bob', "Spanish dictionary loads and resolves placeholders correctly");
} finally {
    // Revert language back to 'en'
    $langProp->setValue(null, 'en');
    I18n::reset();
}

echo "I18n component tests completed.\n\n";
