<?php
// src/Database/Migrations/0029_AddExpiresAtToSitesTable.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

class AddExpiresAtToSitesTable extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding expires_at column to sites table...\n";
        DB::query("ALTER TABLE sites ADD COLUMN expires_at DATETIME NULL");
    }

    public function down(): void
    {
        echo "Removing expires_at column from sites table...\n";
        DB::query("ALTER TABLE sites DROP COLUMN expires_at");
    }
}
