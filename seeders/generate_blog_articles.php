<?php
// seeders/generate_blog_articles.php
// Clean, hand-written, highly analytical articles about the Zero CMS philosophy, security threat modeling, and dependency-free dev.

use Zero\Database\DB;
use Zero\Support\Security;

echo "--> Commencing seeding of 10 hand-written, high-quality technical publications...\n";

// Resolve the Guide Site ID from the database
$siteRow = DB::query("SELECT id FROM sites WHERE domain = 'd6laptop.zero.guide' LIMIT 1")->fetch();
$siteId = $siteRow ? $siteRow['id'] : null;

if (!$siteId) {
    echo "Warning: Guide site d6laptop.zero.guide not found in database. Skipping blog seeding.\n";
    return;
}

// Clean out any previous blog posts to prevent duplicate entries on re-run
DB::query("DELETE FROM blog_posts WHERE site_id = ?", [$siteId]);

// Load the 10 hand-written articles from the JSON file on disk
$jsonPath = APPLICATION_ROOT . '/seeders/data/handwritten_articles.json';
if (!file_exists($jsonPath)) {
    echo "Error: Seeder data file not found at: {$jsonPath}\n";
    return;
}

$postsData = json_decode(file_get_contents($jsonPath), true);
if (!is_array($postsData)) {
    echo "Error: Failed to decode handwritten articles JSON.\n";
    return;
}

// Fetch any available media IDs for this site to use as featured images
$mediaIds = DB::query("SELECT id FROM media WHERE site_id = ? AND deleted_at IS NULL", [$siteId])->fetchAll(\PDO::FETCH_COLUMN);

// Loop over our 10 beautifully hand-written posts and save them cleanly into the database!
foreach ($postsData as $index => $pData) {
    $title = $pData['title'];
    $slug = $pData['slug'];
    $summary = $pData['summary'];
    $contentBlocks = $pData['content'];
    
    $postId = Security::uuidv7();
    
    $totalWords = 0;
    foreach ($contentBlocks as $block) {
        $totalWords += str_word_count(strip_tags($block['content'] ?? ''));
    }
    
    // Serialize page-builder blocks into JSON content
    $contentJson = json_encode($contentBlocks);
    
    // Database dates cascading offset to simulate realistic chronological posting history
    $createdAt = date('Y-m-d H:i:s', time() - (10 - $index) * 86400);
    $updatedAt = $createdAt;
    
    $featuredImageId = null;
    if (!empty($mediaIds)) {
        $featuredImageId = $mediaIds[$index % count($mediaIds)];
    }
    
    // Insert into blog_posts table under Guide site isolation
    DB::query("
        INSERT INTO blog_posts (id, site_id, title, slug, content, summary, type, status, featured_image, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'post', 'published', ?, ?, ?)
    ", [
        $postId,
        $siteId,
        $title,
        $slug,
        $contentJson,
        $summary,
        $featuredImageId,
        $createdAt,
        $updatedAt
    ]);
    
    echo "   [Article " . str_pad($index + 1, 2, '0', STR_PAD_LEFT) . "/10] Seeded: '{$title}' ({$totalWords} words)\n";
}

echo "--> 10 hand-written, high-quality blog articles seeded successfully!\n";
