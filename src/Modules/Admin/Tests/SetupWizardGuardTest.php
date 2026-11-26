<?php
// src/Modules/Admin/Tests/SetupWizardGuardTest.php
// The setup wizard can be shown by a transient database error on a live install, so runSetup()
// must refuse to create records unless the install is really empty. Theme names become part of a
// filesystem path, so resolveThemeDir() only accepts plain identifiers.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Modules\Admin\Controllers\SetupWizardController;

echo "=== Setup Wizard Guard Tests ===\n";

echo "Testing runSetup() refuses to run on an installed database...\n";

// Make sure the test database is not empty, whatever ran before.
$siteId = \Zero\Support\Security::uuidv7();
DB::query(
    "INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at) VALUES (?, 'Wizard Guard', ?, 'default', '[]', NOW(), NOW())",
    [$siteId, 'wizard-guard-' . bin2hex(random_bytes(4)) . '.zero']
);
$usersBefore = (int) DB::query("SELECT COUNT(*) FROM users")->fetchColumn();

$error = null;
try {
    (new SetupWizardController())->runSetup([
        'username' => 'wizard_guard_root',
        'email' => 'wizard_guard_root@example.test',
        'password' => 'WizardGuard123',
        'password_confirmation' => 'WizardGuard123',
        'site_name' => 'Hijacked',
        'site_domain' => 'hijacked.example',
        'site_timezone' => 'UTC',
        'site_language' => 'en',
        'site_theme' => 'default',
    ]);
} catch (\Exception $e) {
    $error = $e->getMessage();
}

assert_test($error === 'Zero CMS is already installed.', "runSetup() throws 'already installed' when sites exist");
assert_test((int) DB::query("SELECT COUNT(*) FROM users")->fetchColumn() === $usersBefore, "No user is created by runSetup() on an installed database");
assert_test(DB::query("SELECT COUNT(*) FROM users WHERE username = 'wizard_guard_root'")->fetchColumn() == 0, "The wizard's super_admin row does not exist");

DB::query("DELETE FROM sites WHERE id = ?", [$siteId]);

echo "Testing resolveThemeDir() rejects path-like theme names...\n";
assert_test(App::resolveThemeDir('default') !== null, "resolveThemeDir('default') resolves");
foreach (['../default', 'default/../default', '..', '/etc', 'default/x'] as $bad) {
    assert_test(App::resolveThemeDir($bad) === null, "resolveThemeDir(" . json_encode($bad) . ") returns null");
}

echo "Setup wizard guard tests completed successfully!\n";
