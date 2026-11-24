<?php
// src/Models/Tests/PageControllerViewGuardTest.php
// Proves content editors cannot choose which PHP class the router instantiates for a page, nor point
// a page's custom view outside the theme folder -- the ZeroCMS analogue of Silverstripe
// CVE-2026-91115 / CVE-2026-91113 (request-chosen class names reaching object construction).

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Models\Page;
use Zero\Support\TestRequest;

echo "=== Page Controller / View Guard Tests ===\n";

$adminModules = ['security'];
$pageController = 'Zero\\Modules\\Admin\\Controllers\\LoginController';

// The admin API writes every editable column it is given, so page creates need the scalar columns
// the edit form would otherwise submit.
$pageFields = ['omit_title' => 0, 'precedence' => 0, 'show_in_nav' => 0, 'exclude_from_search' => 0, 'status' => 'published'];

/**
 * Fetch a page's stored controller and view by title (null when no live row exists).
 */
$pageByTitle = function (string $title): ?array {
    $row = DB::query('SELECT controller, view FROM pages WHERE title = ? AND deleted_at IS NULL LIMIT 1', [$title])->fetch();
    return $row ?: null;
};

// 1. Pure validators.
echo "Testing the view-name and controller validators...\n";

assert_test(Page::isSafeViewName('post'), "A plain view name is safe");
assert_test(Page::isSafeViewName('landing/wide-hero_2'), "A nested theme-relative view name is safe");
assert_test(!Page::isSafeViewName('../../../../etc/passwd'), "A parent-directory traversal is rejected");
assert_test(!Page::isSafeViewName('/etc/passwd'), "An absolute path is rejected");
assert_test(!Page::isSafeViewName('post.php'), "A dotted name is rejected");
assert_test(!Page::isSafeViewName("post\0"), "A NUL byte is rejected");

assert_test(Page::isDispatchableController($pageController), "A class implementing the Controller contract is dispatchable");
assert_test(!Page::isDispatchableController('stdClass'), "A class outside the Controller contract is not dispatchable");
assert_test(!Page::isDispatchableController('No\\Such\\ClassName'), "A missing class is not dispatchable");

// 2. An editor cannot set a page's controller through either admin write path.
echo "Testing an editor cannot assign a page controller...\n";

$apiResponse = TestRequest::post('/api/v1/admin/models/pages', $pageFields + [
        'title' => 'Guard Editor Api Controller',
        'controller' => $pageController,
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'guard_editor_1', 'role' => 'editor'])
    ->withCsrf()
    ->send();

assert_test(
    strpos($apiResponse['stdout'], Page::CONTROLLER_PERMISSION) !== false,
    "Assigning a controller through the admin API as an editor renders the access-denied page"
);
assert_test(
    $pageByTitle('Guard Editor Api Controller') === null,
    "No page is written when an editor assigns a controller through the admin API"
);

TestRequest::post('/admin/edit/pages/new', $pageFields + [
        'title' => 'Guard Editor Form Controller',
        'controller' => $pageController,
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'guard_editor_2', 'role' => 'editor'])
    ->withCsrf()
    ->send();

assert_test(
    $pageByTitle('Guard Editor Form Controller') === null,
    "No page is written when an editor assigns a controller through the edit form"
);

// 3. An editor can still save an ordinary page with a safe custom view.
echo "Testing an editor can still save a page with a safe view...\n";

TestRequest::post('/api/v1/admin/models/pages', $pageFields + [
        'title' => 'Guard Editor Plain Page',
        'view' => 'post',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'guard_editor_3', 'role' => 'editor'])
    ->withCsrf()
    ->send();

$plain = $pageByTitle('Guard Editor Plain Page');
assert_test($plain !== null && $plain['view'] === 'post', "An editor can save a page with a safe custom view");

// 4. A traversal view is refused with a 422, even for a super_admin.
echo "Testing a traversal view name is refused...\n";

$traversalResponse = TestRequest::post('/api/v1/admin/models/pages', $pageFields + [
        'title' => 'Guard Traversal View',
        'view' => '../../../../../../etc/hosts',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'guard_root_1', 'role' => 'super_admin'])
    ->withCsrf()
    ->send();

assert_test(
    strpos($traversalResponse['stdout'], 'Invalid custom view name') !== false,
    "A traversal view name is rejected with a validation error"
);
assert_test($pageByTitle('Guard Traversal View') === null, "No page is written with a traversal view name");

// 5. A super_admin may assign a real controller, but not an arbitrary class.
echo "Testing a super_admin can assign only dispatchable controllers...\n";

TestRequest::post('/api/v1/admin/models/pages', $pageFields + [
        'title' => 'Guard Root Controller',
        'controller' => $pageController,
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'guard_root_2', 'role' => 'super_admin'])
    ->withCsrf()
    ->send();

$rootPage = $pageByTitle('Guard Root Controller');
assert_test($rootPage !== null && $rootPage['controller'] === $pageController, "A super_admin can assign a Controller class");

$badClassResponse = TestRequest::post('/api/v1/admin/models/pages', $pageFields + [
        'title' => 'Guard Root Bad Class',
        'controller' => 'stdClass',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'guard_root_3', 'role' => 'super_admin'])
    ->withCsrf()
    ->send();

assert_test(
    strpos($badClassResponse['stdout'], 'Controller class must exist') !== false,
    "A non-Controller class is rejected with a validation error, even for a super_admin"
);
assert_test($pageByTitle('Guard Root Bad Class') === null, "No page is written with a non-Controller class");

// 6. Rows that bypassed the model (legacy data, direct SQL) are still neutralised at dispatch.
echo "Testing the router ignores unsafe stored controller and view values...\n";

$legacyView = TestRequest::get('/guard-legacy-view')
    ->onSite()
    ->withPage(['slug' => 'guard-legacy-view', 'title' => 'Guard Legacy View', 'type' => 'page', 'view' => '../../../../../../public/index'])
    ->send();

assert_test($legacyView['exit_code'] === 0, "A page with a stored traversal view renders without a fatal error");
assert_test(
    strpos($legacyView['stdout'], 'Guard Legacy View') !== false,
    "A stored traversal view falls back to the default post template"
);

$legacyController = TestRequest::get('/guard-legacy-controller')
    ->onSite()
    ->withPage(['slug' => 'guard-legacy-controller', 'title' => 'Guard Legacy Controller', 'type' => 'page', 'controller' => 'stdClass'])
    ->send();

assert_test($legacyController['exit_code'] === 0, "A page with a stored non-Controller class does not fatally instantiate it");
assert_test(
    strpos($legacyController['stdout'], 'Guard Legacy Controller') !== false,
    "A stored non-Controller class falls back to the default post template"
);

echo "Page controller / view guard tests completed successfully!\n";
