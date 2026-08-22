<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0032_CreateSessionsTable.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class CreateSessionsTable
 *
 * Creates the table backing DatabaseSessionHandler, so PHP sessions survive across app server
 * instances instead of relying on local disk (which is neither shared nor persistent once more
 * than one instance serves traffic).
 */
class CreateSessionsTable extends Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Creating Sessions Database Table...\n";

        DB::query("
            CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) PRIMARY KEY,
                data MEDIUMTEXT NOT NULL,
                last_activity INT UNSIGNED NOT NULL,
                INDEX idx_sessions_last_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Dropping Sessions Database Table...\n";
        DB::query("DROP TABLE IF EXISTS sessions;");
    }
}
