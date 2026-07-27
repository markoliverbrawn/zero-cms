<?php
// tests/CascadesDeletesTest.php
// Unit tests for the new CascadesDeletes Core Model Trait

require_once __DIR__ . '/bootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Media;
use Zero\Models\Site;
use Zero\Models\Page;
use Zero\Modules\Blog\Models\Post;
use Zero\Modules\Blog\Models\Comment;

echo "=== CascadesDeletes Trait Component Tests ===\n";

// Clear original bootstrapped static properties to allow clean re-bootstrap
$refApp = new \ReflectionClass('Zero\Core\App');
$propBoot = $refApp->getProperty('bootstrapped');
$propBoot->setAccessible(true);
$propBoot->setValue(null, false);

$propSite = $refApp->getProperty('currentSite');
$propSite->setAccessible(true);
$propSite->setValue(null, null);

// Mock request headers
$_SERVER['HTTP_HOST'] = 'cascades.zero';

// Insert mock site for isolated integration testing
$mockSiteId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, 'Cascading Deletes Site', 'cascades.zero', 'default', '[\"blog\", \"forum\", \"shop\"]', NOW(), NOW())
", [$mockSiteId]);

App::bootstrap();

$siteId = App::getCurrentSiteId();
assert_test($siteId === $mockSiteId, "Correctly bootstraps mock site environment");

// 1. Create mock Media asset (to act as featured image)
echo "Creating dummy featured image...\n";
$media = new Media([
    'filename' => 'featured-hero.jpg',
    'path' => '/storage/uploads/featured-hero.jpg',
    'mime' => 'image/jpeg'
]);
$media->save();
$mediaId = $media->id;

assert_test(!empty($mediaId), "Featured image media asset successfully saved");

// 2. Create mock Post
echo "Creating dummy post with featured image...\n";
$post = new Post([
    'title' => 'Cascading Deletes Core Trait Test',
    'slug' => 'cascading-deletes-test',
    'summary' => 'This is a core test for the CascadesDeletes trait.',
    'content' => '[]',
    'status' => 'published',
    'featured_image' => $mediaId
]);
$post->save();
$postId = $post->id;

assert_test(!empty($postId), "Post successfully saved");

// 3. Create mock Comments
echo "Creating dummy comments linked to the post...\n";
$comment1 = new Comment([
    'post_id' => $postId,
    'author_name' => 'John Doe',
    'author_email' => 'john@doe.com',
    'content' => 'Great post!',
    'status' => 'approved'
]);
$comment1->save();
$c1Id = $comment1->id;

$comment2 = new Comment([
    'post_id' => $postId,
    'author_name' => 'Jane Smith',
    'author_email' => 'jane@smith.com',
    'content' => 'Very informative.',
    'status' => 'approved'
]);
$comment2->save();
$c2Id = $comment2->id;

assert_test(!empty($c1Id) && !empty($c2Id), "Comments successfully saved and linked to post");

// Verify they exist via standard find
assert_test(Comment::find($c1Id) !== null, "Comment 1 exists in DB");
assert_test(Comment::find($c2Id) !== null, "Comment 2 exists in DB");

// 4. Soft Delete the Post
echo "Soft deleting the post...\n";
$post->delete();

// Verify Post is soft-deleted
assert_test(Post::find($postId) === null, "Post is no longer findable via find() (soft-deleted)");
$trashedPost = Post::findTrashed($postId);
assert_test($trashedPost !== null && !empty($trashedPost->deleted_at), "Post resides in trash with a valid deleted_at timestamp");

// Verify Comments are also soft-deleted automatically!
assert_test(Comment::find($c1Id) === null, "Comment 1 is no longer findable via find() (automatically soft-deleted)");
assert_test(Comment::find($c2Id) === null, "Comment 2 is no longer findable via find() (automatically soft-deleted)");

$trashedC1 = Comment::findTrashed($c1Id);
$trashedC2 = Comment::findTrashed($c2Id);
assert_test($trashedC1 !== null && !empty($trashedC1->deleted_at), "Comment 1 has been soft-deleted and possesses a deleted_at timestamp");
assert_test($trashedC2 !== null && !empty($trashedC2->deleted_at), "Comment 2 has been soft-deleted and possesses a deleted_at timestamp");

// Verify Featured Image media record has NOT been deleted
assert_test(Media::find($mediaId) !== null, "Featured image media asset was NOT deleted (remains active)");

// Verify that Post::paginate with trash filter returns the soft-deleted post, and NOT the active ones
$trashPagination = Post::paginate(1, 10, ['trash' => true]);
$trashPostIds = array_map(fn($p) => $p->id, $trashPagination['data'] ?? []);
assert_test(in_array($postId, $trashPostIds), "Post::paginate() with trash filter correctly returns the soft-deleted post");

// 5. Force Delete the Post
echo "Force deleting the post (permanent clean)...\n";
$trashedPost->forceDelete();

// Verify Post is permanently deleted from DB
assert_test(Post::findTrashed($postId) === null, "Post is permanently removed from the database");

// Verify Comments are permanently deleted from DB automatically!
assert_test(Comment::findTrashed($c1Id) === null, "Comment 1 is permanently removed from the database");
assert_test(Comment::findTrashed($c2Id) === null, "Comment 2 is permanently removed from the database");

// Verify Featured Image media record is still untouched and alive!
assert_test(Media::find($mediaId) !== null, "Featured image media asset remains active in the database (untouched during force deletion)");

// Clean up mock media asset
$media->forceDelete();
assert_test(Media::find($mediaId) === null, "Cleanup of mock media asset successfully complete");

// 6. Test Site dynamic cascade deletes
echo "Testing dynamic Site cascade deletions (Zero module dependencies)...\n";

$testSite = new Site([
    'name' => 'Decoupled Cascade Site',
    'domain' => 'decoupled.zero',
    'theme' => 'default',
    'enabled_modules' => '["blog"]'
]);
$testSite->save();
$testSiteId = $testSite->id;

assert_test(!empty($testSiteId), "Site successfully created and saved");

// Create Page under testSite
$testPage = new Page([
    'site_id' => $testSiteId,
    'title' => 'Decoupled Site Page',
    'slug' => 'decoupled-page',
    'content' => '[]',
    'status' => 'published'
]);
$testPage->save();
$testPageId = $testPage->id;
assert_test(!empty($testPageId), "Page successfully saved under testSite");

// Create Post under testSite
$testPost = new Post([
    'site_id' => $testSiteId,
    'title' => 'Decoupled Site Post',
    'slug' => 'decoupled-post',
    'summary' => 'Dynamic cascade post.',
    'content' => '[]',
    'status' => 'published'
]);
$testPost->save();
$testPostId = $testPost->id;
assert_test(!empty($testPostId), "Post successfully saved under testSite");

// Create Comment under testSite
$testComment = new Comment([
    'site_id' => $testSiteId,
    'post_id' => $testPostId,
    'author_name' => 'Decoupled Comm',
    'author_email' => 'comm@decoupled.zero',
    'content' => 'Dynamic comment cascade',
    'status' => 'approved'
]);
$testComment->save();
$testCommentId = $testComment->id;
assert_test(!empty($testCommentId), "Comment successfully saved under testSite");

// Create mock physical uploads for this site to verify automatic directory purging on soft-delete
$uploadDir = APPLICATION_ROOT . '/public/storage/uploads/' . $testSiteId;
@mkdir($uploadDir, 0775, true);
$cropsDir = $uploadDir . '/_crops';
@mkdir($cropsDir, 0775, true);
$tempFile1 = $uploadDir . '/file1.jpg';
$tempFile2 = $cropsDir . '/crop1.jpg';
file_put_contents($tempFile1, 'mock JPEG data');
file_put_contents($tempFile2, 'mock crop JPEG data');

assert_test(file_exists($tempFile1) === true, "Mock physical media file exists on disk prior to soft-deletion");
assert_test(file_exists($tempFile2) === true, "Mock physical crop file exists on disk prior to soft-deletion");

// Soft delete Site
echo "Soft deleting the Site...\n";
$testSite->delete();

// Verify Site is soft-deleted
assert_test(Site::find($testSiteId) === null, "Site is soft-deleted (find returns null)");
$trashedSite = Site::findTrashed($testSiteId);
assert_test($trashedSite !== null && !empty($trashedSite->deleted_at), "Site exists in trash with valid deleted_at");

// Verify dynamic cascade soft-deletions!
assert_test(Page::find($testPageId) === null, "Page is successfully soft-deleted via Site cascade");
assert_test(Post::find($testPostId) === null, "Post is successfully soft-deleted via Site cascade");
assert_test(Comment::find($testCommentId) === null, "Comment is successfully soft-deleted via recursive cascade of Post cascade!");

// Verify physical uploads are cleanly, recursively purged upon soft delete!
assert_test(file_exists($tempFile1) === false, "Mock physical media file is cleanly deleted from disk when site is soft-deleted");
assert_test(file_exists($tempFile2) === false, "Mock physical crop file is cleanly deleted from disk when site is soft-deleted");
assert_test(file_exists($uploadDir) === false, "Site uploads directory is cleanly deleted and recursively purged from disk when site is soft-deleted");

// Re-create mock physical uploads for this site to verify automatic directory purging on force-delete
@mkdir($uploadDir, 0775, true);
@mkdir($cropsDir, 0775, true);
file_put_contents($tempFile1, 'mock JPEG data');
file_put_contents($tempFile2, 'mock crop JPEG data');

assert_test(file_exists($tempFile1) === true, "Mock physical media file exists on disk prior to force-deletion");
assert_test(file_exists($tempFile2) === true, "Mock physical crop file exists on disk prior to force-deletion");

// Force delete Site
echo "Force deleting the Site...\n";
$trashedSite->forceDelete();

// Verify absolute permanent deletion across all related entities!
assert_test(Site::findTrashed($testSiteId) === null, "Site permanently deleted from DB");
assert_test(Page::findTrashed($testPageId) === null, "Page permanently deleted from DB via Site cascade force delete");
assert_test(Post::findTrashed($testPostId) === null, "Post permanently deleted from DB via Site cascade force delete");
assert_test(Comment::findTrashed($testCommentId) === null, "Comment permanently deleted from DB via dynamic recursive force delete cascade");

// Verify physical uploads are cleanly, recursively purged upon force-delete!
assert_test(file_exists($tempFile1) === false, "Mock physical media file is cleanly deleted from disk when site is force-deleted");
assert_test(file_exists($tempFile2) === false, "Mock physical crop file is cleanly deleted from disk when site is force-deleted");
assert_test(file_exists($uploadDir) === false, "Site uploads directory is cleanly deleted and recursively purged from disk when site is force-deleted");

// 7. Verify we CANNOT delete the current site
echo "Testing blocked deletion of active tenant site...\n";
$currentSite = Site::find($mockSiteId);
assert_test($currentSite !== null, "Current site retrieved successfully");

$softDeleteBlocked = false;
try {
    $currentSite->delete();
} catch (\Exception $e) {
    if (strpos($e->getMessage(), "Deletion blocked") !== false) {
        $softDeleteBlocked = true;
    }
}
assert_test($softDeleteBlocked, "Soft deleting the active tenant site is successfully blocked and throws Exception");

// Clean up DB mock active site
DB::query("DELETE FROM sites WHERE id = ?", [$mockSiteId]);

echo "CascadesDeletes trait component tests completed successfully!\n";
