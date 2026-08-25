<?php
// src/Support/Tests/PermissionsTest.php
// Unit tests for the RBAC permission registry (Zero\Support\Permissions)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Permissions;

echo "=== Permissions Component Tests ===\n";

// 1. Test core's own default role -> permission grants
echo "Testing core default role permission grants...\n";
assert_test(Permissions::roleHas('super_admin', 'backoffice.access'), "super_admin has backoffice.access via wildcard");
assert_test(Permissions::roleHas('super_admin', 'sites.manage'), "super_admin has sites.manage via wildcard");
assert_test(Permissions::roleHas('super_admin', 'anything.never.registered'), "super_admin has even an unregistered permission via wildcard");

assert_test(Permissions::roleHas('admin', 'backoffice.access'), "admin has backoffice.access");
assert_test(Permissions::roleHas('admin', 'content.edit'), "admin has content.edit");
assert_test(Permissions::roleHas('admin', 'users.manage'), "admin has users.manage");
assert_test(Permissions::roleHas('admin', 'modules.manage'), "admin has modules.manage");
assert_test(!Permissions::roleHas('admin', 'sites.manage'), "admin does NOT have sites.manage");
assert_test(!Permissions::roleHas('admin', 'content.destructive'), "admin does NOT have content.destructive");

assert_test(Permissions::roleHas('editor', 'backoffice.access'), "editor has backoffice.access");
assert_test(Permissions::roleHas('editor', 'content.edit'), "editor has content.edit");
assert_test(!Permissions::roleHas('editor', 'users.manage'), "editor does NOT have users.manage");
assert_test(!Permissions::roleHas('editor', 'modules.manage'), "editor does NOT have modules.manage");

assert_test(!Permissions::roleHas('guest', 'content.edit'), "An unrecognized role has no permissions at all");

// 2. Test model -> permission lookups for core's own models
echo "Testing core default model permission mappings...\n";
assert_test(Permissions::permissionForModel('users') === 'users.manage', "permissionForModel('users') resolves to users.manage");
assert_test(Permissions::permissionForModel('sites') === 'sites.manage', "permissionForModel('sites') resolves to sites.manage");
assert_test(Permissions::permissionForModel('pages') === null, "permissionForModel returns null for a model with no restriction");

// 3. Test additive registration, mirroring how a module grants its own permissions from init()
echo "Testing additive permission registration...\n";
Permissions::register('test.widget.manage', ['editor']);
assert_test(Permissions::roleHas('editor', 'test.widget.manage'), "register() grants a new permission to the specified role");
assert_test(!Permissions::roleHas('admin', 'test.widget.manage'), "register() does not grant the new permission to a role that wasn't listed");
assert_test(Permissions::roleHas('super_admin', 'test.widget.manage'), "super_admin still has the newly-registered permission via wildcard");

// 4. Test additive model-permission registration
echo "Testing additive model permission registration...\n";
Permissions::registerModelPermission('widgets', 'test.widget.manage');
assert_test(Permissions::permissionForModel('widgets') === 'test.widget.manage', "registerModelPermission() adds a new model->permission mapping");

echo "Permissions component tests completed successfully!\n";
