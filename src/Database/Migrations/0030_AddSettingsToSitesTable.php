<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0030_AddSettingsToSitesTable.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Database/Migrations/0030_AddSettingsToSitesTable.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddSettingsToSitesTable
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddSettingsToSitesTable extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding settings column to sites table...\n";
        DB::query("ALTER TABLE sites ADD COLUMN settings TEXT NULL");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing settings column from sites table...\n";
        DB::query("ALTER TABLE sites DROP COLUMN settings");
    }
}
