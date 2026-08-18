<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0010_AddShowInNavToPages.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddShowInNavToPages
 *
 * Adds the show_in_nav flag to pages, so a page can be published and reachable yet omitted from
 * generated navigation.
 */
class AddShowInNavToPages extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding show_in_nav column to pages table...\n";
        DB::query("ALTER TABLE pages ADD COLUMN show_in_nav TINYINT(1) NOT NULL DEFAULT 1 AFTER precedence;");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing show_in_nav column from pages table...\n";
        DB::query("ALTER TABLE pages DROP COLUMN show_in_nav;");
    }
}
