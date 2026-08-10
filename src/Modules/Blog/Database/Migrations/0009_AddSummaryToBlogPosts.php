<?php

declare(strict_types=1);

/**
 * File: src/Modules/Blog/Database/Migrations/0009_AddSummaryToBlogPosts.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddSummaryToBlogPosts
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddSummaryToBlogPosts extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding summary column to blog_posts...\n";
        DB::query("ALTER TABLE blog_posts ADD COLUMN summary TEXT NULL AFTER title;");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing summary column from blog_posts...\n";
        DB::query("ALTER TABLE blog_posts DROP COLUMN summary;");
    }
}
