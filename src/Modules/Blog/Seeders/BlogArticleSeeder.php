<?php
// src/Modules/Blog/Seeders/BlogArticleSeeder.php

namespace Zero\Modules\Blog\Seeders;

use Exception;
use Zero\Interfaces\SeederInterface;
use Zero\Database\DB;
use Zero\Support\Security;

class BlogArticleSeeder implements SeederInterface
{
    /**
     * Get the associated module identifier (e.g. 'shop', 'forum', 'blog').
     *
     * @return string
     */
    public function getModuleId(): string
    {
        return 'blog';
    }

    /**
     * Get the execution priority (lower numbers run first).
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * Run the dynamic seeder routine for a specific site ID.
     *
     * @param string $siteId The unique UUID of the site to seed
     * @param string $uploadsDir Absolute path to public uploads directory
     * @return void
     * @throws Exception If the module is not active for this site
     */
    public function run(string $siteId, string $uploadsDir): void
    {
        // 1. Fetch site and verify the blog module is active
        $site = DB::query("SELECT id, name, enabled_modules FROM sites WHERE id = ? AND deleted_at IS NULL", [$siteId])->fetch();
        if (!$site) {
            throw new Exception("Seeding error: Target site ID '{$siteId}' not found.");
        }

        $enabled = json_decode($site['enabled_modules'] ?? '[]', true);
        if (!in_array('blog', $enabled)) {
            throw new Exception("Seeding error: Module 'blog' is not active for site '{$site['name']}'.");
        }

        echo "--> Seeding Hand-Written Publications for Site: '{$site['name']}' (ID: {$siteId})...\n";

        // Clean out any previous blog posts to prevent duplicate entries on re-run
        DB::query("DELETE FROM blog_posts WHERE site_id = ?", [$siteId]);

        // Load the 10 hand-written articles from the JSON file on disk
        $jsonPath = __DIR__ . '/handwritten_articles.json';
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

        // Explicit, robust mapping of blog post slug -> featured image filename
        $slugImageMap = [
            'intro-to-zero-dependency-cms-architecture' => 'framework-speed-benchmarks.jpg',
            'managing-supply-chain-risks-web-apps' => 'supply-chain-security.jpg',
            'how-ai-driven-exploit-scanning-affects-patching' => 'decoupled-architecture.jpg',
            'securing-web-applications-code-simplicity' => 'code-simplicity.jpg',
            'comparing-orms-to-raw-sql-prepared-queries' => 'database-performance.jpg',
            'sending-transactional-emails-php-tcp-sockets' => 'tcp-socket-emailer.jpg',
            'handling-concurrency-race-conditions-checkouts' => 'concurrency-race-conditions.jpg',
            'preventing-cross-site-scripting-recursive-input-sanitization' => 'xss-prevention.jpg',
            'enforcing-strict-database-boundary-isolation-multi-tenant' => 'database-boundary-isolation.jpg',
            'continuous-integration-isolated-tests-php-subprocesses' => 'ci-isolated-tests.jpg',
        ];

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
            
            // Resolve featured image from direct slug map
            $featuredImageId = null;
            if (isset($slugImageMap[$slug])) {
                $filename = $slugImageMap[$slug];
                $mediaRow = DB::query("SELECT id FROM media WHERE site_id = ? AND filename = ? LIMIT 1", [$siteId, $filename])->fetch();
                if ($mediaRow) {
                    $featuredImageId = $mediaRow['id'];
                }
            }
            
            // Fallback to traditional modulo selection if direct mapping is not found or resolved
            if (!$featuredImageId && !empty($mediaIds)) {
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

        echo "--> 10 hand-written, high-quality blog articles seeded successfully for '{$site['name']}'!\n";
    }
}
