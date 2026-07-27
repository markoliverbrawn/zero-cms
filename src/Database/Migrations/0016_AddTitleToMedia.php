<?php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

class AddTitleToMedia extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding title column to media table...\n";
        
        // Defensively check if column exists first
        if (!DB::hasColumn('media', 'title')) {
            DB::query("ALTER TABLE media ADD COLUMN title VARCHAR(255) NULL AFTER site_id;");
        }
    }

    public function down(): void
    {
        echo "Removing title column from media table...\n";
        
        if (DB::hasColumn('media', 'title')) {
            DB::query("ALTER TABLE media DROP COLUMN title;");
        }
    }
}
