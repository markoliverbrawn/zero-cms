<?php
// src/Database/Migrations/0019_AddOmitTitleToPages.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

class AddOmitTitleToPages extends \Zero\Database\Migration
{
    public function down(): void
    {
        echo "Removing omit_title column from pages table...\n";
        if (DB::hasColumn('pages', 'omit_title')) {
            DB::query("ALTER TABLE pages DROP COLUMN omit_title");
        }
    }

    public function up(): void
    {
        echo "Adding omit_title column to pages table...\n";
        if (!DB::hasColumn('pages', 'omit_title')) {
            DB::query("ALTER TABLE pages ADD COLUMN omit_title TINYINT(1) NOT NULL DEFAULT 0 AFTER title");
        }
    }
}
