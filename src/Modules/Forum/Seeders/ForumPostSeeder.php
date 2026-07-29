<?php
// src/Modules/Forum/Seeders/ForumPostSeeder.php

namespace Zero\Modules\Forum\Seeders;

use Exception;
use Zero\Interfaces\SeederInterface;
use Zero\Database\DB;
use Zero\Support\Security;

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

        $enabled = json_decode($site['enabled_modules'] ?? '[]', true);
        if (!in_array('forum', $enabled)) {
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

        echo "--> Found " . count($threads) . " active forum threads for '{$site['name']}'.\n";

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
        $userIds = array_column($users, 'id');

        // 5. Setup content dictionaries for combinatorial dynamic post generation (categorized by thread slug)
        $themes = [
            'welcome-to-the-kitchensink-showroom-forums' => [
                'openers' => [
                    "This community is amazing!", "Totally agreed.", "Incredible framework speed here.", 
                    "Hello team!", "Wow, I am so glad to finally find a project that", "It is so refreshing to see",
                    "This is spectacular.", "Absolutely outstanding.", "Zero-dependencies is truly", "My initial thoughts:"
                ],
                'bodies' => [
                    "the lightweight load time is absolutely outstanding. Zero framework overhead makes all the difference.",
                    "bypassing unverified vendor packages keeps our security posture perfectly hardened against supply chain exploits.",
                    "pure raw SQL PDO queries on bare metal run in microseconds, leaving heavy bloated ORMs completely in the dust.",
                    "the custom SVG icons look exceptionally sharp and load instantaneously under high contrast themes.",
                    "event delegation and parent-to-iframe messaging bypasses standard browser sandbox filters beautifully.",
                    "writing code with 100% auditable modules restores true developer control and logical clarity."
                ],
                'closers' => [
                    "Kudos to Neo for this incredible release!", "Excited to see where this project goes.", 
                    "Let's build some gorgeous, highly secure sites!", "This is a masterpiece of clean software craft.", 
                    "Highly recommended to everyone here.", "Count me in for further contributions!"
                ]
            ],
            'how-do-i-sync-my-neural-interface-core' => [
                'openers' => [
                    "I had a similar issue last week.", "Try checking your biometrics diagnostics.", "Quick reminder:", 
                    "Actually, the telemetry signal drops", "Regarding neural core thermal stability:", "Have you considered", 
                    "In my experience,", "An easy fix is", "Make sure to review", "To prevent biometrics dropping,"
                ],
                'bodies' => [
                    "if the core thermal heat exceeds 85°C under intensive compiler cycles, the 1024-Qubit link will automatically desync.",
                    "attaching the cryogenic cooling module provides consistent telemetry alignment during heavy mental loads.",
                    "manually recalibrating the bio-link wrist strap to 1200hz secures the communication stream perfectly.",
                    "overclocking the synapse bridge without adequate obsidian plating will trigger biometrics latency drop-offs.",
                    "the local power grid in District 4 can experience transient voltage spikes that destabilize sub-dermal links.",
                    "flashing the neural interface core with the latest patch optimizes sensory synchronization dramatically."
                ],
                'closers' => [
                    "Let me know if attaching the cooler resolves it!", "That did the trick for my setup.", 
                    "Be extremely careful with high thermal signatures!", "Good luck with your neural sync!", 
                    "Let me know how the calibration goes.", "Keep an eye on those biometrics charts."
                ]
            ],
            'show-off-your-active-glow-cyber-vest-custom-configurations' => [
                'openers' => [
                    "Your pink neon rig looks amazing!", "Here is a quick look at my setup:", "I just finished routing", 
                    "My current vest configuration is", "Just added active neon wires", "The brutalist dark aesthetic", 
                    "For those interested in DIY gear:", "I am currently using", "Highly recommend routing", "My matte black vest"
                ],
                'bodies' => [
                    "a cyan glowing EL-wire sleeve running down both shoulders powered by a central 45W rechargeable lithium pack.",
                    "integrated sub-dermal haptic actuators mapped to provide physical recoil alerts during real-time system warnings.",
                    "matte black obsidian carbon plates custom-molded to fit an adjustable tactical exoskeleton harness rig.",
                    "graphene-fiber weave underlayers that distribute heat signature emissions evenly to keep biometric temp stable.",
                    "high-voltage pulsar chargers embedded in custom utility belts to run active neon glowing strips for 12+ hours.",
                    "a custom HUD visors synchronization node that overlays real-time server telemetry directly onto my glasses."
                ],
                'closers' => [
                    "It looks exceptionally sleek in total darkness!", "Will post pictures of the glowing cuffs soon.", 
                    "Absolutely matches the greenscreen admin dashboard theme.", "Let me know if you want the wire routing diagram!", 
                    "Next step is adding automated thermal regulation.", "Cyberpunk fashion at its finest."
                ]
            ],
            'add-a-dark-theme-switcher-to-the-forum-layouts' => [
                'openers' => [
                    "Strongly support this!", "Nostalgic screens are the best.", "If we build a switcher,", 
                    "Regarding layout aesthetics:", "A vintage greenscreen theme would", "We can easily implement", 
                    "For maximum theme adaptability,", "My two cents on this:", "To make styles fully adapt,", "A native switcher is"
                ],
                'bodies' => [
                    "using standard, modern CSS nesting rules and custom properties makes toggling color schemes incredibly clean.",
                    "avoiding flat repetitive paths and nested overrides preserves stylesheet modularity perfectly.",
                    "pure vanilla CSS variables are definitely the best way to handle theme swapping without any framework bloat.",
                    "eliminating heavy inline script tags from the view templates keeps our content security policies pristine.",
                    "the high-contrast Monokai colors are gorgeous, but a vintage terminal layout would look beautifully historic.",
                    "ensuring 100% compliance with modern native selectors allows browser rendering speeds to drop below 0.5ms."
                ],
                'closers' => [
                    "No !important rules are needed if we use precise CSS nesting!", "Let's definitely propose a PR for this soon.", 
                    "Can't wait to see the vintage terminal layout in action.", "Let's keep the CSS file super lightweight.", 
                    "Excellent suggestion for our back-office look!", "Cheers to keeping the CSS clean and auditable."
                ]
            ]
        ];

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
            $thread = $threads[$i % count($threads)];
            $tId = $thread['id'];
            $tSlug = $thread['slug'];

            // Select user
            $userId = $userIds[array_rand($userIds)];

            // Compile themed message
            $theme = $themes[$tSlug] ?? $themes['welcome-to-the-kitchensink-showroom-forums'];
            $opener = $theme['openers'][array_rand($theme['openers'])];
            $body = $theme['bodies'][array_rand($theme['bodies'])];
            $closer = $theme['closers'][array_rand($theme['closers'])];
            $content = "{$opener} {$body} {$closer}";

            // Randomly decide if this is a reply to an existing post in the same thread (for conversation nesting)
            $parentId = null;
            if (!empty($threadPostsMap[$tId]) && rand(1, 100) > 40) {
                $parentId = $threadPostsMap[$tId][array_rand($threadPostsMap[$tId])];
            }

            // Generate random timestamp over the last 15 days
            $daysAgo = rand(0, 15);
            $hoursAgo = rand(0, 23);
            $minsAgo = rand(0, 59);
            $secsAgo = rand(0, 59);
            $createdAt = gmdate('Y-m-d H:i:s', strtotime("-{$daysAgo} days -{$hoursAgo} hours -{$minsAgo} minutes -{$secsAgo} seconds"));

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
        echo "--> Generated & Seeded {$postsCreated} forum replies distributed across " . count($threads) . " threads.\n";
        echo "====================================================================\n";
    }
}
