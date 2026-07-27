<?php
// src/Database/Migrations/0021_AddSiteTimezoneAndLanguage.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

class AddSiteTimezoneAndLanguage extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding timezone and default_language columns to sites table...\n";
        DB::query("ALTER TABLE sites ADD COLUMN timezone VARCHAR(100) NOT NULL DEFAULT 'UTC'");
        DB::query("ALTER TABLE sites ADD COLUMN default_language VARCHAR(50) NOT NULL DEFAULT 'en'");
    }

    public function down(): void
    {
        echo "Removing timezone and default_language columns from sites table...\n";
        DB::query("ALTER TABLE sites DROP COLUMN timezone");
        DB::query("ALTER TABLE sites DROP COLUMN default_language");
    }
}
