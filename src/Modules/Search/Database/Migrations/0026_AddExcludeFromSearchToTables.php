<?php

declare(strict_types=1);

/**
 * File: src/Modules/Search/Database/Migrations/0026_AddExcludeFromSearchToTables.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Search\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Search\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddExcludeFromSearchToTables
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddExcludeFromSearchToTables extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding exclude_from_search column to pages, blog_posts, and shop_products tables...\n";

        if (!DB::hasColumn('pages', 'exclude_from_search')) {
            DB::query("ALTER TABLE pages ADD COLUMN exclude_from_search TINYINT(1) NOT NULL DEFAULT 0 AFTER show_in_nav;");
        }

        if (!DB::hasColumn('blog_posts', 'exclude_from_search')) {
            DB::query("ALTER TABLE blog_posts ADD COLUMN exclude_from_search TINYINT(1) NOT NULL DEFAULT 0 AFTER featured_image;");
        }

        if (!DB::hasColumn('shop_products', 'exclude_from_search')) {
            DB::query("ALTER TABLE shop_products ADD COLUMN exclude_from_search TINYINT(1) NOT NULL DEFAULT 0 AFTER status;");
        }
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing exclude_from_search column from pages, blog_posts, and shop_products tables...\n";

        if (DB::hasColumn('pages', 'exclude_from_search')) {
            DB::query("ALTER TABLE pages DROP COLUMN exclude_from_search;");
        }

        if (DB::hasColumn('blog_posts', 'exclude_from_search')) {
            DB::query("ALTER TABLE blog_posts DROP COLUMN exclude_from_search;");
        }

        if (DB::hasColumn('shop_products', 'exclude_from_search')) {
            DB::query("ALTER TABLE shop_products DROP COLUMN exclude_from_search;");
        }
    }
}
