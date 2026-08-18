<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0012_AddSummaryToPages.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddSummaryToPages
 *
 * Adds the summary column to pages, holding the short description used in listings and meta tags.
 */
class AddSummaryToPages extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding summary column to pages table...\n";
        DB::query("ALTER TABLE pages ADD COLUMN summary TEXT NULL AFTER content;");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing summary column from pages table...\n";
        DB::query("ALTER TABLE pages DROP COLUMN summary;");
    }
}
