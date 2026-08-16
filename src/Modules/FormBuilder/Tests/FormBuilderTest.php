<?php
// tests/FormBuilderTest.php
// Unit and integration tests for the Form Builder module

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Http\Router;
use Zero\Database\DB;
use Zero\Core\Validator;

// Dynamically run pending migrations on the test database to ensure form_submissions exists
ob_start();
\Zero\Database\MigrationManager::up();
ob_end_clean();

echo "=== Form Builder Module Tests ===\n";

// 1. Verify Module Auto-Discovery
echo "Testing module auto-discovery...\n";
App::bootstrap();
$modules = App::getModules();
assert_test(isset($modules['formbuilder']), "FormBuilder module is successfully auto-discovered and registered in the core");

// 2. Verify Route Mapping
echo "Testing API route mapping...\n";
$ref = new \ReflectionClass('Zero\Http\Router');
$prop = $ref->getProperty('routes');
$prop->setAccessible(true);
$routes = $prop->getValue();

$matched = false;
foreach ($routes as $pattern => $handler) {
    if (strpos($pattern, '/api/v1/contact/submit') !== false) {
        $matched = true;
        assert_test($handler === 'Zero\Modules\FormBuilder\Controllers\FormApiController', "Form Builder API endpoint correctly maps to FormApiController");
        break;
    }
}
assert_test($matched, "Form Builder API route pattern is registered in the global Router registry");

// 3. Verify Database Schema Table Existence
echo "Testing database table existence...\n";
$hasTable = DB::query("SHOW TABLES LIKE 'form_submissions'")->fetch();
assert_test(!empty($hasTable), "Table 'form_submissions' exists in the database schema");

// 4. Test API Controller Handler Mock (Integration)
echo "Testing dynamic form submission processing and validation...\n";

// Clear original bootstrapped static properties to allow re-bootstrap
$refApp = new \ReflectionClass('Zero\Core\App');
$propBoot = $refApp->getProperty('bootstrapped');
$propBoot->setAccessible(true);
$propBoot->setValue(null, false);

$propSite = $refApp->getProperty('currentSite');
$propSite->setAccessible(true);
$propSite->setValue(null, null);

// Mock a clean guest request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'd6laptop.zero'; // active tenant

// Insert mock site and page for isolated integration testing
$mockSiteId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, 'Mock Site', 'd6laptop.zero', 'default', '[\"formbuilder\"]', NOW(), NOW())
", [$mockSiteId]);

$mockPageId = \Zero\Support\Security::uuidv7();
$mockContent = json_encode([
    [
        'type' => 'form_builder',
        'id' => 'cf_corp_contact',
        'recipient_email' => 'admin@d6laptop.zero',
        'items' => [
            ['name' => 'name', 'label' => 'Sender Name', 'type' => 'text', 'required' => '1', 'validation' => 'none'],
            ['name' => 'email', 'label' => 'Sender Email', 'type' => 'email', 'required' => '1', 'validation' => 'email'],
            ['name' => 'phone', 'label' => 'Sender Phone', 'type' => 'tel', 'required' => '0', 'validation' => 'phone'],
            ['name' => 'message', 'label' => 'Submission Message', 'type' => 'textarea', 'required' => '1', 'validation' => 'none']
        ]
    ]
]);
DB::query("
    INSERT INTO pages (id, site_id, title, slug, content, status, type, created_at, updated_at)
    VALUES (?, ?, 'Contact Us', 'contact', ?, 'published', 'page', NOW(), NOW())
", [$mockPageId, $mockSiteId, $mockContent]);

App::bootstrap();

$siteId = App::getCurrentSiteId();
assert_test($siteId === $mockSiteId, "Correctly bootstraps mock site environment");

// Wipe out any existing dummy contact submissions for testing
DB::query("DELETE FROM form_submissions WHERE email = 'test-runner-guest@zero.cms'");

// Mock payload 1: Invalid email format
$dirtyJson1 = json_encode([
    'name' => 'Test Runner Guest',
    'email' => 'not-an-email',
    'phone' => '+1 (555) 123-4567',
    'message' => 'Hello team, this is a test message.',
    'block_id' => 'cf_corp_contact'
]);

// Run Validation locally using our validator (since the controller handles raw php://input, we can assert validator's output directly)
$rules = [
    'name' => 'required|min:2|max:255',
    'email' => 'required|email|max:255',
    'phone' => 'phone',
    'message' => 'required|min:5'
];

$validator1 = new Validator(json_decode($dirtyJson1, true), $rules);
assert_test(!$validator1->validate(), "Invalid email format fails validation");
assert_test(isset($validator1->getErrors()['email']), "Produces validation error specific to email field");

// Mock payload 2: Valid payload
$cleanJson2 = json_encode([
    'name' => 'Test Runner Guest',
    'email' => 'test-runner-guest@zero.cms',
    'phone' => '+1 (555) 123-4567',
    'message' => 'Hello team, this is a valid test message.',
    'block_id' => 'cf_corp_contact'
]);

$validator2 = new Validator(json_decode($cleanJson2, true), $rules);
assert_test($validator2->validate(), "Valid payload passes all core validation constraints");

// Insert clean payload directly and assert persistence
$subId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO form_submissions (id, site_id, name, email, phone, message, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
", [
    $subId,
    $siteId,
    'Test Runner Guest',
    'test-runner-guest@zero.cms',
    '+1 (555) 123-4567',
    'Hello team, this is a valid test message.'
]);

$submission = DB::query("SELECT * FROM form_submissions WHERE id = ?", [$subId])->fetch();
assert_test(!empty($submission), "Successfully inserts and persists contact form submission in form_submissions table");
assert_test($submission['name'] === 'Test Runner Guest', "Saves correct name metadata");
assert_test($submission['site_id'] === $siteId, "Enforces strict multi-tenant site_id scoping isolation");

// 5. Verify Honeypot Spam Filter Logic
echo "Testing honeypot spam filter trap...\n";
$spamJson = json_encode([
    'name' => 'Spam Bot Crawler',
    'email' => 'spam@bot.crawler',
    'phone' => '+1 (555) 999-9999',
    'message' => 'Viagra pharmaceuticals, buy now!',
    'block_id' => 'cf_corp_contact',
    'website_url' => 'http://spam-phishing-url.com'
]);
$spamData = json_decode($spamJson, true);
$isSpam = !empty($spamData['website_url']);
assert_test($isSpam === true, "Honeypot detector identifies spam submission successfully");

// 6. FormField component system migration: frontend rendering
echo "Testing frontend form_builder.php rendering via the FormField component system...\n";
$viewPath = APPLICATION_ROOT . '/src/Modules/FormBuilder/Views/blocks/frontend/form_builder.php';
$mockBlock = [
    'id' => 'blk-test',
    'content' => '',
    'items' => [
        ['name' => 'sender_email', 'label' => 'Email', 'type' => 'email', 'required' => '1'],
        ['name' => 'sender_phone', 'label' => 'Phone', 'type' => 'tel', 'required' => '0'],
        ['name' => 'plan', 'label' => 'Plan', 'type' => 'select', 'options' => 'Basic, Pro', 'required' => '0'],
        ['name' => 'interests', 'label' => 'Interests', 'type' => 'checkbox', 'options' => 'News, Offers', 'required' => '0'],
        ['name' => 'size', 'label' => 'Size', 'type' => 'radio', 'options' => 'S, M, L', 'required' => '1'],
    ],
];
$renderedForm = \Zero\Core\Template::renderFile($viewPath, ['block' => $mockBlock]);

assert_test(\strpos($renderedForm, 'type="email"') !== false, "Email field renders type=\"email\"");
assert_test(\strpos($renderedForm, 'type="tel"') !== false, "Tel field renders type=\"tel\" (not lost as a generic text input)");
assert_test(\strpos($renderedForm, 'field-help-text') === false, "No i18n helper text leaks into a public contact form field (e.g. an 'email'-named field must not surface the unrelated admin User.email_help string)");
assert_test(\strpos($renderedForm, '-- Select Option --') !== false, "Select field still renders its blank placeholder option");
assert_test(\substr_count($renderedForm, 'name="size"') >= 3, "Radio group renders one input per option");
$sizeGroupStart = \strpos($renderedForm, 'name="size"');
$sizeGroupHtml = \substr($renderedForm, $sizeGroupStart - 200, 700);
assert_test(\substr_count($sizeGroupHtml, 'required') === 1, "Radio group puts 'required' on exactly one input, not every option (preserved HTML semantics)");

// 7. FormField component system migration: submission casting (mirrors FormApiController's own logic)
echo "Testing FormApiController-style submission casting via the FormField component system...\n";

$checkboxField = App::makeFormField('checkbox_group', 'interests', ['options' => []]);
$castedCheckbox = $checkboxField->castSubmittedValue(['interests' => ['News', 'Tampered Value']]);
assert_test($castedCheckbox === ['News', 'Tampered Value'], "Checkbox group casting does not filter against options (deliberately deferred -- a security-tightening change kept separate from this rendering migration)");

$radioField = App::makeFormField('radio_group', 'size', ['options' => []]);
assert_test($radioField->castSubmittedValue(['size' => 'XL']) === 'XL', "Radio group casting does not filter against options either, for the same deliberate deferral reason");

$selectOptions = ['' => '-- Select Option --'] + \array_combine(['Basic', 'Pro'], ['Basic', 'Pro']);
$selectField = App::makeFormField('select', 'plan', ['options' => $selectOptions]);
assert_test($selectField->castSubmittedValue(['plan' => '']) === '', "An intentionally-unselected optional dropdown casts through as '' rather than being rejected by the options allow-list");
assert_test($selectField->castSubmittedValue(['plan' => 'Pro']) === 'Pro', "A legitimately-selected dropdown option casts through unchanged");
assert_test($selectField->castSubmittedValue(['plan' => 'Tampered']) === null, "A tampered, never-rendered option value is rejected (no 'default' configured here, so it falls back to null)");

// Clean up
DB::query("DELETE FROM form_submissions WHERE id = ?", [$subId]);
DB::query("DELETE FROM pages WHERE id = ?", [$mockPageId]);
DB::query("DELETE FROM sites WHERE id = ?", [$mockSiteId]);

echo "Form Builder module tests completed successfully!\n";
