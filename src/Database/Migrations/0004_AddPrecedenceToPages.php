<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0004_AddPrecedenceToPages.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class AddPrecedenceToPages
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddPrecedenceToPages extends Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Running migration: Adding precedence column to pages table...\n";
        
        $hasPrecedence = DB::query("SHOW COLUMNS FROM pages LIKE 'precedence'")->fetch();
        if (!$hasPrecedence) {
            DB::query("ALTER TABLE pages ADD COLUMN precedence INT DEFAULT 0 AFTER status");
        }
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Reverting migration: Removing precedence column from pages table...\n";
        
        $hasPrecedence = DB::query("SHOW COLUMNS FROM pages LIKE 'precedence'")->fetch();
        if ($hasPrecedence) {
            DB::query("ALTER TABLE pages DROP COLUMN precedence");
        }
    }
}
