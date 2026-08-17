<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0017_AddFocusPointsToMedia.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddFocusPointsToMedia
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddFocusPointsToMedia extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding focus point columns to media table...\n";
        
        // Defensively check if columns exist first before adding
        if (!DB::hasColumn('media', 'focus_x')) {
            DB::query("ALTER TABLE media ADD COLUMN focus_x INT NOT NULL DEFAULT 50 AFTER title;");
        }
        if (!DB::hasColumn('media', 'focus_y')) {
            DB::query("ALTER TABLE media ADD COLUMN focus_y INT NOT NULL DEFAULT 50 AFTER focus_x;");
        }
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing focus point columns from media table...\n";
        
        if (DB::hasColumn('media', 'focus_x')) {
            DB::query("ALTER TABLE media DROP COLUMN focus_x;");
        }
        if (DB::hasColumn('media', 'focus_y')) {
            DB::query("ALTER TABLE media DROP COLUMN focus_y;");
        }
    }
}
