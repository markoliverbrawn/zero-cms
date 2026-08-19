<?php

declare(strict_types=1);

/**
 * File: src/Modules/Events/Database/Migrations/0032_AddFeaturedImageToEvents.php
 * Architectural Purpose: Add featured_image column to events table.
 * Package: Zero\Modules\Events\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Events\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddFeaturedImageToEvents
 *
 * Migration class to add a featured image column to events.
 */
class AddFeaturedImageToEvents extends \Zero\Database\Migration
{
    /**
     * Runs the database migrations to append the column.
     *
     * @return void
     */
    public function up(): void
    {
        echo "Adding featured_image column to events table...\n";

        // Defensively check if column exists first before adding
        if (!DB::hasColumn('events', 'featured_image')) {
            DB::query("ALTER TABLE events ADD COLUMN featured_image VARCHAR(36) NULL AFTER status;");
        }
    }

    /**
     * Reverses the migrations by dropping the column.
     *
     * @return void
     */
    public function down(): void
    {
        echo "Removing featured_image column from events table...\n";

        if (DB::hasColumn('events', 'featured_image')) {
            DB::query("ALTER TABLE events DROP COLUMN featured_image;");
        }
    }
}
