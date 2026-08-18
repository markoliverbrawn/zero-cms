<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Database/Migrations/0028_CreateProductCategoryLinksTable.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class CreateProductCategoryLinksTable
 *
 * Creates the join table linking products to categories, replacing the single-category column with
 * a many-to-many relationship.
 */
class CreateProductCategoryLinksTable extends Migration
{
    /**
     * Rollback this sequential migration.
     */
    public function down(): void
    {
        echo "Dropping shop_product_category_links table...\n";
        DB::query("DROP TABLE IF EXISTS shop_product_category_links;");
    }

    /**
     * Run this sequential migration.
     */
    public function up(): void
    {
        echo "Creating shop_product_category_links table...\n";
        
        DB::query("
            CREATE TABLE IF NOT EXISTS shop_product_category_links (
                product_id VARCHAR(36) NOT NULL,
                category_id VARCHAR(36) NOT NULL,
                site_id VARCHAR(36) NOT NULL,
                PRIMARY KEY (product_id, category_id),
                INDEX site_id_index (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}
