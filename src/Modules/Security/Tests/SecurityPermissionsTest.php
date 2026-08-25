<?php
// src/Modules/Security/Tests/SecurityPermissionsTest.php
// Regression test proving the Security module correctly self-registers its own RBAC permission
// keys and model->permission mappings from its own Module::init(), rather than core hardcoding
// them -- see Zero\Modules\Security\Module::init().

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;

echo "=== Security Module RBAC Self-Registration Tests ===\n";

// Trigger full module discovery (calls every module's init(), including Security's).
App::bootstrap();

// 1. Test the model -> permission mappings Security's own init() registers
echo "Testing Security's self-registered model permission mappings...\n";
assert_test(App::permissionForModel('audit_logs') === 'audit.manage', "audit_logs resolves to the audit.manage permission");
assert_test(App::permissionForModel('security_audits') === 'security.audit', "security_audits resolves to the security.audit permission");

// 2. Test the role grants Security's own init() registers
echo "Testing Security's self-registered role permission grants...\n";

$reflector = new ReflectionClass(App::class);
$property = $reflector->getProperty('currentUser');
$property->setAccessible(true);

$adminUser = new \Zero\Models\User(['id' => 'sec-admin-id', 'username' => 'sec_admin', 'role' => 'admin']);
$property->setValue(null, $adminUser);

assert_test(App::authorize('audit.manage') === true, "Admin is authorized for Security-owned audit.manage");
assert_test(App::authorize('audit.purge') === true, "Admin is authorized for Security-owned audit.purge");
assert_test(App::authorize('security.audit') === true, "Admin is authorized for Security-owned security.audit");
assert_test(App::authorize('audit.purge_global') === false, "Admin is NOT authorized for Security-owned audit.purge_global (super_admin only)");

$editorUser = new \Zero\Models\User(['id' => 'sec-editor-id', 'username' => 'sec_editor', 'role' => 'editor']);
$property->setValue(null, $editorUser);

assert_test(App::authorize('audit.manage') === false, "Editor is NOT authorized for Security-owned audit.manage");
assert_test(App::authorize('security.audit') === false, "Editor is NOT authorized for Security-owned security.audit");

$property->setValue(null, null);

echo "Security module RBAC self-registration tests completed successfully!\n";
