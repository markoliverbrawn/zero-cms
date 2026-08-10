<?php
/**
 * File: src/Database/Migrations/0029_AddExpiresAtToSitesTable.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */


// src/Database/Migrations/0029_AddExpiresAtToSitesTable.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddExpiresAtToSitesTable
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddExpiresAtToSitesTable extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding expires_at column to sites table...\n";
        DB::query("ALTER TABLE sites ADD COLUMN expires_at DATETIME NULL");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing expires_at column from sites table...\n";
        DB::query("ALTER TABLE sites DROP COLUMN expires_at");
    }
}
