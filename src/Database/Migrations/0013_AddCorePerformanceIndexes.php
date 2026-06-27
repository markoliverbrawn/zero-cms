<?php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

class AddCorePerformanceIndexes extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding performance indexing to core pages table...\n";
        
        try {
            DB::query("ALTER TABLE pages DROP INDEX idx_pages_site_deleted_precedence;");
        } catch (\PDOException $e) {}
        
        DB::query("ALTER TABLE pages ADD INDEX idx_pages_site_deleted_precedence (site_id, deleted_at, precedence ASC);");
    }

    public function down(): void
    {
        echo "Removing performance indexing from core pages table...\n";
        
        try {
            DB::query("ALTER TABLE pages DROP INDEX idx_pages_site_deleted_precedence;");
        } catch (\PDOException $e) {}
    }
}
