<?php

namespace Zero\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

class AddPrivateStorageFieldsToMediaTable extends Migration
{
    public function up(): void
    {
        echo "Adding Private Storage and Secure Upload columns to 'media' table...\n";

        DB::query("
            ALTER TABLE media 
            ADD COLUMN visibility VARCHAR(20) DEFAULT 'public',
            ADD COLUMN submission_id VARCHAR(36) NULL,
            ADD COLUMN original_name VARCHAR(255) NULL,
            ADD COLUMN file_size INT NOT NULL DEFAULT 0,
            ADD INDEX (visibility),
            ADD INDEX (submission_id)
        ");
    }

    public function down(): void
    {
        echo "Removing Private Storage columns from 'media' table...\n";
        DB::query("
            ALTER TABLE media 
            DROP COLUMN visibility,
            DROP COLUMN submission_id,
            DROP COLUMN original_name,
            DROP COLUMN file_size
        ");
    }
}
