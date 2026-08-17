<?php

declare(strict_types=1);

/**
 * Zero CMS Forum Module Dynamic Post Seeder
 *
 * This class handles dynamic database seeding of the Forum module, executing both
 * vocabulary templates loading, and combinatorial procedural community thread and reply generation.
 *
 * @package Zero\Modules\Forum\Seeders
 */

namespace Zero\Modules\Forum\Seeders;

use Exception;
use Zero\Database\DB;
use Zero\Interfaces\SeederInterface;
use Zero\Support\Security;

/**
 * Class ForumPostSeeder
 *
 * Implements SeederInterface to populate dynamic threaded discussions, community board
 * channels, and user replies on bootstrap.
 */
class ForumPostSeeder implements SeederInterface
{
    /**
     * Get the associated module identifier (e.g. 'shop', 'forum', 'blog').
     *
     * @return string
     */
    public function getModuleId(): string
    {
        return 'forum';
    }

    /**
     * Get the execution priority (lower numbers run first).
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 20;
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
        // 1. Fetch site and verify the forum module is active
        $site = DB::query("SELECT id, name, enabled_modules FROM sites WHERE id = ? AND deleted_at IS NULL", [$siteId])->fetch();
        if (!$site) {
            throw new Exception("Seeding error: Target site ID '{$siteId}' not found.");
        }

        $enabled = \json_decode($site['enabled_modules'] ?? '[]', true);
        if (!\in_array('forum', $enabled)) {
            throw new Exception("Seeding error: Module 'forum' is not active for site '{$site['name']}'.");
        }

        echo "====================================================================\n";
        echo "ZERO CMS: DYNAMIC FORUM SEEDER FOR '{$site['name']}'\n";
        echo "====================================================================\n";

        // 2. Retrieve threads registered for this site
        $threads = DB::query("SELECT id, slug, title FROM forum_threads WHERE site_id = ? AND deleted_at IS NULL", [$siteId])->fetchAll();

        if (empty($threads)) {
            throw new Exception("Seeding error: No forum threads found for '{$site['name']}'. Seed boards & threads first!");
        }

        echo "--> Found " . \count($threads) . " active forum threads for '{$site['name']}'.\n";

        // 3. Clear existing procedural forum posts for this site, keeping the 7 static/json-seeded ones safe
        // The static ones have IDs defined in kitchensink.json starting with '019ed7cd-0d1b-71e2-8062-88c2f0e89f0'
        echo "--> Safely purging previous dynamic forum posts while preserving default json seed posts...\n";
        DB::query("
            DELETE FROM forum_posts 
            WHERE site_id = ? 
              AND id NOT LIKE '019ed7cd-0d1b-71e2-8062-88c2f0e89f0%'
        ", [$siteId]);

        // 4. Retrieve available user IDs from the target site
        $users = DB::query("SELECT id, username FROM users WHERE site_id = ? AND deleted_at IS NULL", [$siteId])->fetchAll();
        if (empty($users)) {
            $users = DB::query("SELECT id, username FROM users WHERE deleted_at IS NULL LIMIT 5")->fetchAll();
        }
        $userIds = \array_column($users, 'id');

        // 5. Setup content dictionaries for combinatorial dynamic post generation (categorized by thread slug)
        $forumDataPath = __DIR__ . '/forum.php';
        if (!\file_exists($forumDataPath)) {
            throw new Exception("Seeding error: forum.php blueprint file not found.");
        }
        $forumData = require $forumDataPath;
        $themes = $forumData['forum_themes_blueprint'] ?? [];

        if (empty($themes)) {
            throw new Exception("Seeding error: forum_themes_blueprint is empty.");
        }

        // 6. Generate 100 posts (replies) distributed across the threads
        $postsToCreate = 100;
        $postsCreated = 0;

        // To make replies threaded and realistic, we will keep track of posts created in each thread
        // so we can randomly assign parent_id to some of them to build conversation trees.
        $threadPostsMap = [];

        // First, query the existing static/json-seeded posts and populate our thread-to-post mapping
        $defaultPosts = DB::query("SELECT id, thread_id FROM forum_posts WHERE site_id = ?", [$siteId])->fetchAll();
        foreach ($defaultPosts as $p) {
            $threadPostsMap[$p['thread_id']][] = $p['id'];
        }

        echo "--> Seeding {$postsToCreate} realistic forum replies sequentially across threads...\n";

        for ($i = 0; $i < $postsToCreate; $i++) {
            // Select thread
            $thread = $threads[$i % \count($threads)];
            $tId = $thread['id'];
            $tSlug = $thread['slug'];

            // Select user
            $userId = $userIds[\array_rand($userIds)];

            // Compile themed message
            $theme = $themes[$tSlug] ?? $themes['welcome-to-the-kitchensink-showroom-forums'];
            $opener = $theme['openers'][\array_rand($theme['openers'])];
            $body = $theme['bodies'][\array_rand($theme['bodies'])];
            $closer = $theme['closers'][\array_rand($theme['closers'])];
            $content = "{$opener} {$body} {$closer}";

            // Randomly decide if this is a reply to an existing post in the same thread (for conversation nesting)
            $parentId = null;
            if (!empty($threadPostsMap[$tId]) && \rand(1, 100) > 40) {
                $parentId = $threadPostsMap[$tId][\array_rand($threadPostsMap[$tId])];
            }

            // Generate random timestamp over the last 15 days
            $daysAgo = \rand(0, 15);
            $hoursAgo = \rand(0, 23);
            $minsAgo = \rand(0, 59);
            $secsAgo = \rand(0, 59);
            $createdAt = \gmdate('Y-m-d H:i:s', \strtotime("-{$daysAgo} days -{$hoursAgo} hours -{$minsAgo} minutes -{$secsAgo} seconds"));

            $postId = Security::uuidv7();

            DB::query("
                INSERT INTO forum_posts (id, site_id, thread_id, user_id, content, parent_id, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'approved', ?, ?)
            ", [
                $postId,
                $siteId,
                $tId,
                $userId,
                $content,
                $parentId,
                $createdAt,
                $createdAt
            ]);

            // Keep track of the post ID so subsequent iterations can reply to it
            $threadPostsMap[$tId][] = $postId;
            $postsCreated++;
        }

        echo "====================================================================\n";
        echo "SUCCESS: Seeding Completed for '{$site['name']}'!\n";
        echo "--> Generated & Seeded {$postsCreated} forum replies distributed across " . \count($threads) . " threads.\n";
        echo "====================================================================\n";
    }
}
