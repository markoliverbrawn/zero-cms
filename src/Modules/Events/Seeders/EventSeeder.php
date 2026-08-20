<?php

declare(strict_types=1);

/**
 * File: src/Modules/Events/Seeders/EventSeeder.php
 * Architectural Purpose: Handles dynamic multi-tenant database seeding for Events module.
 * Package: Zero\Modules\Events\Seeders
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Events\Seeders;

use Exception;
use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Interfaces\SeederInterface;
use Zero\Support\Security;

/**
 * Class EventSeeder
 *
 * Implements SeederInterface to populate seed events under strict multi-tenant isolation on bootstrap.
 */
class EventSeeder implements SeederInterface
{
    /**
     * Get the associated module identifier (e.g. 'shop', 'forum', 'blog', 'events').
     *
     * @return string
     */
    public function getModuleId(): string
    {
        return 'events';
    }

    /**
     * Get the execution priority (lower numbers run first).
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 30;
    }

    /**
     * Run the dynamic seeder routine for a specific site ID.
     *
     * @param string $siteId The unique UUID of the site to seed
     * @param string $uploadsDir Absolute path to public uploads directory
     * @return void
     * @throws Exception If the site cannot be fetched or resolved
     */
    public function run(string $siteId, string $uploadsDir): void
    {
        $site = DB::query("SELECT id, name, enabled_modules FROM sites WHERE id = ? AND deleted_at IS NULL", [$siteId])->fetch();
        if (!$site) {
            throw new Exception("Seeding error: Target site ID '{$siteId}' not found.");
        }

        $enabled = \json_decode($site['enabled_modules'] ?? '[]', true);
        if (!\in_array('events', $enabled)) {
            return; // Exit silently if events is not active for this site
        }

        echo "Seeding Events Module data for '{$site['name']}'...\n";

        // Clean up previous seeded events for this site
        DB::query("DELETE FROM events WHERE site_id = ?", [$siteId]);

        // Helper anonymous function to load and copy seed featured image from disk
        $loadFeaturedImage = function(string $slug) use ($siteId): ?string {
            try {
                $filename = "event-featured-{$slug}.jpg";
                $seedImgPath = APPLICATION_ROOT . '/src/Modules/Events/Seeders/data/images/' . $filename;
                
                if (!\file_exists($seedImgPath)) {
                    return null;
                }
                
                $content = \file_get_contents($seedImgPath);
                
                // Write to physical storage under uploads/site_id/filename
                $uploadsRoot = Storage::getUploadsRoot();
                $physicalPath = $uploadsRoot . '/' . $siteId . '/' . $filename;
                Storage::write($physicalPath, $content);
                
                $publicPath = '/storage/uploads/' . $siteId . '/' . $filename;
                $mediaId = Security::uuidv7();
                
                // Create a media record
                DB::query("
                    INSERT INTO media (id, site_id, filename, path, mime, title, visibility, file_size, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'image/jpeg', ?, 'public', ?, NOW(), NOW())
                ", [
                    $mediaId,
                    $siteId,
                    $filename,
                    $publicPath,
                    "Featured Image for {$slug}",
                    \strlen($content)
                ]);
                
                return $mediaId;
            } catch (Exception $ex) {
                echo "Warning: Failed to load event featured image {$slug}: " . $ex->getMessage() . "\n";
                return null;
            }
        };

        // Seed 3 high-quality, non-conflicting default events
        $events = [
            [
                'title' => 'Zero CMS Community Hackathon',
                'slug' => 'zero-cms-community-hackathon',
                'description' => '<p>Join fellow zero-dependency enthusiasts to build high-contrast custom themes, new lightweight block structures, and native tools during our annual hacking sprint.</p><p>We will have multiple panel reviews, speed coding challenges, and open-source extension workshops.</p>',
                'event_date' => \gmdate('Y-m-d H:i:s', \strtotime('+5 days 10:00:00')),
                'location' => 'Decentralized Netrunner Deck Online'
            ],
            [
                'title' => 'E-Commerce Security Summit',
                'slug' => 'ecommerce-security-summit',
                'description' => '<p>A workshop exploring cryptographic request signing, raw SMTP socket handshakes, and multi-tenant database boundary hardening.</p><p>Learn directly from the engineers who deployed Zero CMS securely in distributed serverless pipelines.</p>',
                'event_date' => \gmdate('Y-m-d H:i:s', \strtotime('+12 days 14:30:00')),
                'location' => 'Metropolis Conference Hall'
            ],
            [
                'title' => 'Brutalist Design & Typography Meetup',
                'slug' => 'brutalist-design-typography-meetup',
                'description' => '<p>A physical panel discussing the return of monospace high-contrast layouts, native CSS nesting specificity, and strict content separation in user interfaces.</p><p>Bring your modern CSS notebooks and wireframe designs.</p>',
                'event_date' => \gmdate('Y-m-d H:i:s', \strtotime('+20 days 18:00:00')),
                'location' => 'Neo-Auckland Cyber Cafe'
            ]
        ];

        foreach ($events as $e) {
            // Load and link the static high-quality featured image
            $featuredImageId = $loadFeaturedImage($e['slug']);

            DB::query("
                INSERT INTO events (id, site_id, title, slug, description, event_date, location, status, featured_image, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'published', ?, NOW(), NOW())
            ", [
                Security::uuidv7(),
                $siteId,
                $e['title'],
                $e['slug'],
                $e['description'],
                $e['event_date'],
                $e['location'],
                $featuredImageId
            ]);
        }

        echo "Successfully seeded 3 events with high-quality featured images!\n";
    }
}
