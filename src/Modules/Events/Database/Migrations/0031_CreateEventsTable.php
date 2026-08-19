<?php

declare(strict_types=1);

/**
 * File: src/Modules/Events/Database/Migrations/0031_CreateEventsTable.php
 * Architectural Purpose: Creates the database schema for the events module.
 * Package: Zero\Modules\Events\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Events\Database\Migrations;

use Zero\Database\DB;

/**
 * Class CreateEventsTable
 *
 * Migration class to compile events table structures in the MySQL database under transactional checks.
 */
class CreateEventsTable extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void
     */
    public function up(): void
    {
        echo "Creating Events Module Database Tables...\n";

        // Events Table (Saves multi-tenant events with timezone-safe UTC timestamps)
        DB::query("
            CREATE TABLE IF NOT EXISTS events (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT NULL,
                event_date DATETIME NOT NULL,
                location VARCHAR(255) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'published',
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                INDEX (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void
     */
    public function down(): void
    {
        echo "Dropping Events Module Database Tables...\n";
        DB::query("DROP TABLE IF EXISTS events;");
    }
}
