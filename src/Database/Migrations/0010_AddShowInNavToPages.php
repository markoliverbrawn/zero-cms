<?php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

class AddShowInNavToPages extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding show_in_nav column to pages table...\n";
        DB::query("ALTER TABLE pages ADD COLUMN show_in_nav TINYINT(1) NOT NULL DEFAULT 1 AFTER precedence;");
    }

    public function down(): void
    {
        echo "Removing show_in_nav column from pages table...\n";
        DB::query("ALTER TABLE pages DROP COLUMN show_in_nav;");
    }
}
