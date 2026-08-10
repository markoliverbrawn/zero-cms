<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0018_AddFeaturedImageToPosts.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddFeaturedImageToPosts
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddFeaturedImageToPosts extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding featured_image column to blog_posts table...\n";
        
        // Defensively check if column exists first before adding
        if (!DB::hasColumn('blog_posts', 'featured_image')) {
            DB::query("ALTER TABLE blog_posts ADD COLUMN featured_image VARCHAR(36) NULL AFTER status;");
        }
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing featured_image column from blog_posts table...\n";
        
        if (DB::hasColumn('blog_posts', 'featured_image')) {
            DB::query("ALTER TABLE blog_posts DROP COLUMN featured_image;");
        }
    }
}
