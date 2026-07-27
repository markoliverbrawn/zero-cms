<?php
// src/Database/Migrations/0025_AddHomepageIdToSitesTable.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

class AddHomepageIdToSitesTable extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding homepage_id column to sites table...\n";
        DB::query("ALTER TABLE sites ADD COLUMN homepage_id VARCHAR(36) NULL");
    }

    public function down(): void
    {
        echo "Removing homepage_id column from sites table...\n";
        DB::query("ALTER TABLE sites DROP COLUMN homepage_id");
    }
}
