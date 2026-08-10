<?php
/**
 * File: src/Database/Migrations/0013_AddCorePerformanceIndexes.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddCorePerformanceIndexes
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddCorePerformanceIndexes extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding performance indexing to core pages table...\n";
        
        try {
            DB::query("ALTER TABLE pages DROP INDEX idx_pages_site_deleted_precedence;");
        } catch (\PDOException $e) {}
        
        DB::query("ALTER TABLE pages ADD INDEX idx_pages_site_deleted_precedence (site_id, deleted_at, precedence ASC);");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing performance indexing from core pages table...\n";
        
        try {
            DB::query("ALTER TABLE pages DROP INDEX idx_pages_site_deleted_precedence;");
        } catch (\PDOException $e) {}
    }
}
