<?php
// src/Integration/Tests/BlockTypePathTraversalTest.php
// A block's 'type' names its template file ('.../blocks/' . $type . '.php'), so a stored type like
// '../../x' would make every theme include an arbitrary .php file. Types must be rejected on save
// and skipped on render.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Support\BlockHelper;
use Zero\Support\Security;
use Zero\Support\TestRequest;

echo "=== Block Type Path Traversal Tests ===\n";

$adminModules = ['security'];
$pageFields = ['omit_title' => 0, 'precedence' => 0, 'show_in_nav' => 0, 'exclude_from_search' => 0, 'status' => 'published'];

// Any .php file the attack could reach. Relative to src/Modules/Admin/Views/blocks/ (the admin
// editor's block view directory), this is the access-denied view, which prints a recognisable
// heading if it is ever included. (Core's default theme has no blocks/ directory, so on the
// frontend the same payload needs a theme that has one -- every sub-project theme does.)
$probeTraversal = '../access-denied';

echo "Testing the block type validator...\n";
assert_test(BlockHelper::isSafeBlockType('text'), "A plain type is safe");
assert_test(BlockHelper::isSafeBlockType('text_image-2'), "Underscores, dashes and digits are safe");
assert_test(!BlockHelper::isSafeBlockType('../x'), "A traversal type is rejected");
assert_test(!BlockHelper::isSafeBlockType('a/b'), "A slash is rejected");
assert_test(!BlockHelper::isSafeBlockType(['text']), "A non-string type is rejected");

echo "Testing a traversal block type is rejected on save...\n";
$response = TestRequest::post('/api/v1/admin/models/pages', $pageFields + [
        'title' => 'Traversal Block Page',
        'content' => \json_encode([['type' => $probeTraversal, 'content' => 'x']]),
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'block_type_editor', 'role' => 'editor'])
    ->withCsrf()
    ->send();

assert_test(strpos($response['stdout'], 'Invalid block type') !== false, "The admin API rejects a traversal block type");
assert_test(
    (int) DB::query('SELECT COUNT(*) AS c FROM pages WHERE title = ?', ['Traversal Block Page'])->fetch()['c'] === 0,
    "No page is written with a traversal block type"
);

echo "Testing a stored traversal block type is skipped on render...\n";
$response = TestRequest::get('/block-traversal-page')
    ->onSite()
    ->withPage([
        'slug' => 'block-traversal-page',
        'title' => 'Block Traversal Page',
        'type' => 'page',
        'content' => \json_encode([['type' => $probeTraversal], ['type' => 'text', 'content' => '<p>Safe block body</p>']]),
    ])
    ->send();

assert_test($response['exit_code'] === 0, "The page renders without a fatal error");
assert_test(strpos($response['stdout'], 'Administrative Access Denied') === false, "The traversal target file is not included");
assert_test(strpos($response['stdout'], 'Safe block body') !== false, "Safe blocks on the same page still render");

echo "Testing a stored traversal block type is skipped by the admin editor...\n";
$pageId = Security::uuidv7();
$response = TestRequest::get('/admin/edit/pages/' . $pageId)
    ->onSite(['enabled_modules' => $adminModules])
    ->withPage([
        'id' => $pageId,
        'title' => 'Editor Traversal Page',
        'type' => 'page',
        'content' => \json_encode([['type' => $probeTraversal]]),
    ])
    ->asUser(['username' => 'block_type_root', 'role' => 'super_admin'])
    ->send();

assert_test(strpos($response['stdout'], 'Administrative Access Denied') === false, "The admin editor does not include the traversal target");

echo "Testing the block preview API rejects a traversal type...\n";
$response = TestRequest::post('/api/v1/admin/block-preview', ['block' => ['type' => $probeTraversal]])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'block_preview_editor', 'role' => 'editor'])
    ->withCsrf()
    ->send();

assert_test(strpos($response['stdout'], 'Invalid block type') !== false, "Block preview rejects a traversal type");

echo "Block type path traversal tests completed successfully!\n";
