<?php
/**
 * File: src/Database/Migrations/0021_AddSiteTimezoneAndLanguage.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Database/Migrations/0021_AddSiteTimezoneAndLanguage.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddSiteTimezoneAndLanguage
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddSiteTimezoneAndLanguage extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding timezone and default_language columns to sites table...\n";
        DB::query("ALTER TABLE sites ADD COLUMN timezone VARCHAR(100) NOT NULL DEFAULT 'UTC'");
        DB::query("ALTER TABLE sites ADD COLUMN default_language VARCHAR(50) NOT NULL DEFAULT 'en'");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing timezone and default_language columns from sites table...\n";
        DB::query("ALTER TABLE sites DROP COLUMN timezone");
        DB::query("ALTER TABLE sites DROP COLUMN default_language");
    }
}
