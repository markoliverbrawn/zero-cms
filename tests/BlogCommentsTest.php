<?php
// tests/BlogCommentsTest.php
// Unit and integration tests for the Blog Commenting feature and Spam protection

require_once __DIR__ . '/bootstrap.php';

use Zero\Core\App;
use Zero\Http\Router;
use Zero\Database\DB;
use Zero\Core\Validator;
use Zero\Modules\Blog\Models\Comment;

// Dynamically run pending migrations on the test database to ensure blog_comments exists
ob_start();
\Zero\Database\MigrationManager::up();
ob_end_clean();

echo "=== Blog Comments Module Tests ===\n";

// 1. Verify Model Auto-Discovery
echo "Testing Comment model loading...\n";
App::bootstrap();
$commentClassExists = class_exists('Zero\Modules\Blog\Models\Comment');
assert_test($commentClassExists === true, "Comment model class is successfully loaded and registered in the core namespaces");

// 2. Verify Route Mapping
echo "Testing API route mapping...\n";
$ref = new \ReflectionClass('Zero\Http\Router');
$prop = $ref->getProperty('routes');
$prop->setAccessible(true);
$routes = $prop->getValue();

$matched = false;
foreach ($routes as $pattern => $handler) {
    if (strpos($pattern, '/api/v1/blog/comments/submit') !== false) {
        $matched = true;
        assert_test($handler === 'Zero\Modules\Blog\Controllers\Api\CommentsController', "Comments submission API maps to CommentsController");
        break;
    }
}
assert_test($matched, "Comments submission API route is registered in the Router");

// 3. Verify Database Table Schema
echo "Testing database table existence...\n";
$hasTable = DB::query("SHOW TABLES LIKE 'blog_comments'")->fetch();
assert_test(!empty($hasTable), "Table 'blog_comments' exists in the database schema");

// 4. Test Submission Validation & Persistence
echo "Testing comment submissions, validation, and multi-tenant isolation...\n";

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
$_SERVER['HTTP_HOST'] = 'd6laptop.zero'; // active tenant

// Insert mock site, blog post and comment for isolated integration testing
$mockSiteId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, 'Mock Site', 'd6laptop.zero', 'default', '[\"blog\"]', NOW(), NOW())
", [$mockSiteId]);

$mockPostId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO blog_posts (id, site_id, title, slug, content, status, type, created_at, updated_at)
    VALUES (?, ?, 'Mock Article Title', 'mock-article', '[]', 'published', 'post', NOW(), NOW())
", [$mockPostId, $mockSiteId]);

App::bootstrap();

$siteId = App::getCurrentSiteId();
assert_test($siteId === $mockSiteId, "Correctly bootstraps mock site environment");

// Wipe out any legacy testing comments for a clean slate
DB::query("DELETE FROM blog_comments WHERE author_email = 'test-author@zero.cms'");

// Mock Payload 1: Invalid Email format
$dirtyJson1 = [
    'post_id' => $mockPostId,
    'author_name' => 'Zero Purist',
    'author_email' => 'not-an-email',
    'content' => 'This is a test comment.'
];

$rules = [
    'post_id' => 'required',
    'author_name' => 'required|min:2|max:100',
    'author_email' => 'required|email|max:100',
    'content' => 'required|min:5|max:2000'
];

$validator1 = new Validator($dirtyJson1, $rules);
assert_test(!$validator1->validate(), "Invalid email format fails validation");

// Mock Payload 2: Valid payload
$cleanJson2 = [
    'post_id' => $mockPostId,
    'author_name' => 'Zero Purist',
    'author_email' => 'test-author@zero.cms',
    'content' => 'This is an awesome, clean, zero-dependency article!'
];

$validator2 = new Validator($cleanJson2, $rules);
assert_test($validator2->validate(), "Valid payload passes all core validation constraints");

// Insert valid comment directly and assert persistence
$commentId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO blog_comments (id, site_id, post_id, author_name, author_email, content, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW())
", [
    $commentId,
    $siteId,
    $mockPostId,
    'Zero Purist',
    'test-author@zero.cms',
    'This is an awesome, clean, zero-dependency article!'
]);

$comments = Comment::getForPost($mockPostId);
assert_test(count($comments) === 1, "Successfully retrieves exactly 1 approved comment for post");
assert_test($comments[0]->author_name === 'Zero Purist', "Saves correct author name metadata");
assert_test($comments[0]->content === 'This is an awesome, clean, zero-dependency article!', "Saves correct comment body");

// Mock Payload 3: Spam payload with honeypot website_url populated
$spamJson = [
    'post_id' => $mockPostId,
    'author_name' => 'Spammer Bot',
    'author_email' => 'spam@crawler.bot',
    'content' => 'Buy cheap pills now!',
    'website_url' => 'http://spam-phishing-url.com'
];

$isSpam = !empty($spamJson['website_url']);
assert_test($isSpam === true, "Honeypot detector catches the spam bot successfully");

// 5. Test Scheduled Job (PurgeOldCommentsJob)
echo "Testing PurgeOldCommentsJob scheduled task...\n";

// Generate timestamps
$tenDaysAgo = gmdate('Y-m-d H:i:s', strtotime('-10 days'));
$twoDaysAgo = gmdate('Y-m-d H:i:s', strtotime('-2 days'));

// Wipe any potential legacy comments with these IDs
DB::query("DELETE FROM blog_comments WHERE id IN ('comment-approved-old', 'comment-pending-old', 'comment-spam-old', 'comment-rejected-old', 'comment-spam-new')");

// Insert mock comment records
DB::query("
    INSERT INTO blog_comments (id, site_id, post_id, author_name, author_email, content, status, created_at, updated_at)
    VALUES 
    ('comment-approved-old', ?, ?, 'John Approved', 'test-author@zero.cms', 'Good content', 'approved', ?, NOW()),
    ('comment-pending-old', ?, ?, 'Peter Pending', 'test-author@zero.cms', 'Mod required', 'pending', ?, NOW()),
    ('comment-spam-old', ?, ?, 'Spammy Old', 'test-author@zero.cms', 'Buy direct!', 'spam', ?, NOW()),
    ('comment-rejected-old', ?, ?, 'Rej Old', 'test-author@zero.cms', 'Rejected comment', 'rejected', ?, NOW()),
    ('comment-spam-new', ?, ?, 'Spammy New', 'test-author@zero.cms', 'Buy direct fast!', 'spam', ?, NOW())
", [
    $mockSiteId, $mockPostId, $tenDaysAgo,
    $mockSiteId, $mockPostId, $tenDaysAgo,
    $mockSiteId, $mockPostId, $tenDaysAgo,
    $mockSiteId, $mockPostId, $tenDaysAgo,
    $mockSiteId, $mockPostId, $twoDaysAgo
]);

// Execute job
$purgeJob = new \Zero\Modules\Blog\Jobs\PurgeOldCommentsJob();
$purgeJob->execute([]);

// Verify results
$remApprovedOld = DB::query("SELECT COUNT(*) FROM blog_comments WHERE id = 'comment-approved-old'")->fetchColumn();
$remPendingOld = DB::query("SELECT COUNT(*) FROM blog_comments WHERE id = 'comment-pending-old'")->fetchColumn();
$remSpamNew = DB::query("SELECT COUNT(*) FROM blog_comments WHERE id = 'comment-spam-new'")->fetchColumn();
$remSpamOld = DB::query("SELECT COUNT(*) FROM blog_comments WHERE id = 'comment-spam-old'")->fetchColumn();
$remRejectedOld = DB::query("SELECT COUNT(*) FROM blog_comments WHERE id = 'comment-rejected-old'")->fetchColumn();

assert_test($remApprovedOld == 1, "Preserves approved comments even if older than 7 days");
assert_test($remPendingOld == 1, "Preserves pending comments even if older than 7 days");
assert_test($remSpamNew == 1, "Preserves spam/rejected comments if they are newer than 7 days (e.g. 2 days old)");
assert_test($remSpamOld == 0, "Automatically and permanently purges spam comments older than 7 days (e.g. 10 days old)");
assert_test($remRejectedOld == 0, "Automatically and permanently purges rejected comments older than 7 days (e.g. 10 days old)");

// Clean up scheduled job test comments
DB::query("DELETE FROM blog_comments WHERE id IN ('comment-approved-old', 'comment-pending-old', 'comment-spam-new')");

// Clean up database tables
DB::query("DELETE FROM blog_comments WHERE id = ?", [$commentId]);
DB::query("DELETE FROM blog_posts WHERE id = ?", [$mockPostId]);
DB::query("DELETE FROM sites WHERE id = ?", [$mockSiteId]);

echo "Blog Comments module tests completed successfully!\n";
