<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0024_AddPrivateStorageFieldsToMediaTable.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class AddPrivateStorageFieldsToMediaTable
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddPrivateStorageFieldsToMediaTable extends Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
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

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
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
