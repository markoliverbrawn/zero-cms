<?php
// tests/ForumTest.php
// Unit and integration tests for the Forum module, models, and multi-tenant isolation

require_once __DIR__ . '/bootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Modules\Forum\Models\ForumBoard;
use Zero\Modules\Forum\Models\ForumThread;
use Zero\Modules\Forum\Models\ForumPost;

// Dynamically run pending migrations on the test database
ob_start();
\Zero\Database\MigrationManager::up();
ob_end_clean();

echo "=== Forum Module Unit Tests ===\n";

// 1. Verify ActiveRecord Models Auto-Discovery
echo "Testing Forum models loading...\n";
App::bootstrap();
assert_test(class_exists('Zero\Modules\Forum\Models\ForumBoard') === true, "ForumBoard model class loaded successfully");
assert_test(class_exists('Zero\Modules\Forum\Models\ForumThread') === true, "ForumThread model class loaded successfully");
assert_test(class_exists('Zero\Modules\Forum\Models\ForumPost') === true, "ForumPost model class loaded successfully");

// 2. Verify Database Table Schema
echo "Testing database table existence...\n";
assert_test(!empty(DB::query("SHOW TABLES LIKE 'forum_boards'")->fetch()), "Table 'forum_boards' exists in the database schema");
assert_test(!empty(DB::query("SHOW TABLES LIKE 'forum_threads'")->fetch()), "Table 'forum_threads' exists in the database schema");
assert_test(!empty(DB::query("SHOW TABLES LIKE 'forum_posts'")->fetch()), "Table 'forum_posts' exists in the database schema");

// 3. Test ActiveRecord CRUD, Threading, and Isolation
echo "Testing forum threads and posts lifecycle and isolation...\n";

// Clear original bootstrapped static properties to allow re-bootstrap
$refApp = new \ReflectionClass('Zero\Core\App');
$propBoot = $refApp->getProperty('bootstrapped');
$propBoot->setAccessible(true);
$propBoot->setValue(null, false);

$propSite = $refApp->getProperty('currentSite');
$propSite->setAccessible(true);
$propSite->setValue(null, null);

// Mock request headers
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'd6laptop.zero.kitchensink'; // active tenant

// Pre-cleanup to ensure no leftover records from previous failed runs crash this test run
DB::query("DELETE FROM users WHERE username = 'neo_test' OR email = 'neo@matrix.zero'");
DB::query("DELETE FROM sites WHERE domain = 'd6laptop.zero.kitchensink'");

// Insert mock site, board, user, and thread for integration testing
$mockSiteId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, 'Mock Kitchensink Site', 'd6laptop.zero.kitchensink', 'kitchensink', '[\"forum\"]', NOW(), NOW())
", [$mockSiteId]);

$mockUserId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO users (id, site_id, username, email, password_hash, role, created_at, updated_at)
    VALUES (?, ?, 'neo_test', 'neo@matrix.zero', 'hash', 'editor', NOW(), NOW())
", [$mockUserId, $mockSiteId]);

App::bootstrap();

$siteId = App::getCurrentSiteId();
assert_test($siteId === $mockSiteId, "Correctly bootstraps mock site environment");

// Insert board
$boardId = \Zero\Support\Security::uuidv7();
$board = new ForumBoard([
    'id' => $boardId,
    'site_id' => $siteId,
    'title' => 'Test Discussion Board',
    'slug' => 'test-board',
    'description' => 'Test Description',
    'precedence' => 10
]);
$board->save();

// Insert thread
$threadId = \Zero\Support\Security::uuidv7();
$thread = new ForumThread([
    'id' => $threadId,
    'site_id' => $siteId,
    'board_id' => $boardId,
    'user_id' => $mockUserId,
    'title' => 'Test Thread Title',
    'slug' => 'test-thread',
    'status' => 'published',
    'views_count' => 15
]);
$thread->save();

// Insert root post
$rootPostId = \Zero\Support\Security::uuidv7();
$rootPost = new ForumPost([
    'id' => $rootPostId,
    'site_id' => $siteId,
    'thread_id' => $threadId,
    'user_id' => $mockUserId,
    'content' => 'This is the original topic post.',
    'parent_id' => null,
    'status' => 'approved'
]);
$rootPost->save();

// Insert nested reply post
$replyPostId = \Zero\Support\Security::uuidv7();
$replyPost = new ForumPost([
    'id' => $replyPostId,
    'site_id' => $siteId,
    'thread_id' => $threadId,
    'user_id' => $mockUserId,
    'content' => 'This is a threaded reply to the original post.',
    'parent_id' => $rootPostId,
    'status' => 'approved'
]);
$replyPost->save();

// 4. Verify thread posts and replies counts helpers
$posts = $thread->getPosts();
assert_test(count($posts) === 2, "getPosts() successfully retrieves exactly 2 posts in the thread");
assert_test($posts[0]->id === $rootPostId, "First post resolved is the root original post");
assert_test($posts[1]->parent_id === $rootPostId, "Second post resolved is the nested threaded reply");
assert_test($thread->getRepliesCount() === 1, "getRepliesCount() correctly returns exactly 1 reply in the thread");

// 5. Verify user and metadata association
$author = $posts[0]->getUser();
assert_test($author !== null && $author->username === 'neo_test', "getUser() correctly resolves the post author username");

// 6. Verify thread findBySlug and site_id hydration
$foundThread = ForumThread::findBySlug('test-thread');
assert_test($foundThread !== null, "findBySlug() successfully retrieves the forum thread by its slug");
assert_test($foundThread->site_id === $siteId, "hydrated ForumThread has a non-empty site_id matching the active site");

// 7. Verify post find and site_id hydration
$foundPost = ForumPost::find($rootPostId);
assert_test($foundPost !== null, "find() successfully retrieves the forum post by id");
assert_test($foundPost->site_id === $siteId, "hydrated ForumPost has a non-empty site_id matching the active site");

// Clean up database tables
DB::query("DELETE FROM forum_posts WHERE thread_id = ?", [$threadId]);
DB::query("DELETE FROM forum_threads WHERE id = ?", [$threadId]);
DB::query("DELETE FROM forum_boards WHERE id = ?", [$boardId]);
DB::query("DELETE FROM users WHERE id = ?", [$mockUserId]);
DB::query("DELETE FROM sites WHERE id = ?", [$mockSiteId]);

echo "Forum module tests completed successfully!\n";
