<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0025_AddHomepageIdToSitesTable.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Database/Migrations/0025_AddHomepageIdToSitesTable.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddHomepageIdToSitesTable
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddHomepageIdToSitesTable extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding homepage_id column to sites table...\n";
        DB::query("ALTER TABLE sites ADD COLUMN homepage_id VARCHAR(36) NULL");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing homepage_id column from sites table...\n";
        DB::query("ALTER TABLE sites DROP COLUMN homepage_id");
    }
}
