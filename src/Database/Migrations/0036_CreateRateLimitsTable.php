<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0036_CreateRateLimitsTable.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class CreateRateLimitsTable
 *
 * Server-side state for Security::rateLimit(): one row per action + client IP + site bucket,
 * holding the Unix time of the last allowed action. Replaces the session-held timestamp, which a
 * client reset simply by not sending its session cookie.
 */
class CreateRateLimitsTable extends Migration
{
    /**
     * Drop the table.
     *
     * @return void
     */
    public function down(): void
    {
        echo "Dropping rate_limits table...\n";
        DB::query("DROP TABLE IF EXISTS rate_limits");
    }

    /**
     * Create the table.
     *
     * @return void
     */
    public function up(): void
    {
        echo "Creating rate_limits table...\n";
        DB::query("
            CREATE TABLE IF NOT EXISTS rate_limits (
                bucket CHAR(64) PRIMARY KEY,
                last_hit INT UNSIGNED NOT NULL,
                INDEX idx_rate_limits_last_hit (last_hit)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
