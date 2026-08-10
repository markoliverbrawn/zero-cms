<?php
/**
 * File: src/Database/Migrations/0019_AddOmitTitleToPages.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Database/Migrations/0019_AddOmitTitleToPages.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddOmitTitleToPages
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddOmitTitleToPages extends \Zero\Database\Migration
{
    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing omit_title column from pages table...\n";
        if (DB::hasColumn('pages', 'omit_title')) {
            DB::query("ALTER TABLE pages DROP COLUMN omit_title");
        }
    }

    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding omit_title column to pages table...\n";
        if (!DB::hasColumn('pages', 'omit_title')) {
            DB::query("ALTER TABLE pages ADD COLUMN omit_title TINYINT(1) NOT NULL DEFAULT 0 AFTER title");
        }
    }
}
