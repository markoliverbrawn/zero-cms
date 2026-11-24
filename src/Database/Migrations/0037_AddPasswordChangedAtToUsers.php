<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0037_AddPasswordChangedAtToUsers.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class AddPasswordChangedAtToUsers
 *
 * Records when a user's password last changed (UTC). Sessions that signed in before that moment
 * are refused, so changing or resetting a password signs out every other session.
 */
class AddPasswordChangedAtToUsers extends Migration
{
    /**
     * Drop the column.
     *
     * @return void
     */
    public function down(): void
    {
        echo "Dropping users.password_changed_at...\n";
        DB::query("ALTER TABLE users DROP COLUMN password_changed_at");
    }

    /**
     * Add the column.
     *
     * @return void
     */
    public function up(): void
    {
        echo "Adding users.password_changed_at...\n";
        DB::query("ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER password_hash");
    }
}
