<?php

namespace Zero\Database\Migrations;

use Zero\Database\Migration;
use Zero\Database\DB;

class AddPrecedenceToPages extends Migration
{
    public function up(): void
    {
        echo "Running migration: Adding precedence column to pages table...\n";
        
        $hasPrecedence = DB::query("SHOW COLUMNS FROM pages LIKE 'precedence'")->fetch();
        if (!$hasPrecedence) {
            DB::query("ALTER TABLE pages ADD COLUMN precedence INT DEFAULT 0 AFTER status");
        }
    }

    public function down(): void
    {
        echo "Reverting migration: Removing precedence column from pages table...\n";
        
        $hasPrecedence = DB::query("SHOW COLUMNS FROM pages LIKE 'precedence'")->fetch();
        if ($hasPrecedence) {
            DB::query("ALTER TABLE pages DROP COLUMN precedence");
        }
    }
}
