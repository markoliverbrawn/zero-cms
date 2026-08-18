<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0016_AddTitleToMedia.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddTitleToMedia
 *
 * Adds the title column to media, so an asset carries a human-readable label independent of its
 * filename.
 */
class AddTitleToMedia extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding title column to media table...\n";
        
        // Defensively check if column exists first
        if (!DB::hasColumn('media', 'title')) {
            DB::query("ALTER TABLE media ADD COLUMN title VARCHAR(255) NULL AFTER site_id;");
        }
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing title column from media table...\n";
        
        if (DB::hasColumn('media', 'title')) {
            DB::query("ALTER TABLE media DROP COLUMN title;");
        }
    }
}
