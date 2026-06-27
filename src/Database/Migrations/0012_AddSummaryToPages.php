<?php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

class AddSummaryToPages extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding summary column to pages table...\n";
        DB::query("ALTER TABLE pages ADD COLUMN summary TEXT NULL AFTER content;");
    }

    public function down(): void
    {
        echo "Removing summary column from pages table...\n";
        DB::query("ALTER TABLE pages DROP COLUMN summary;");
    }
}
