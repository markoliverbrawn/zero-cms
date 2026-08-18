<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Database/Migrations/0003_CreateShopTables.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Database\Migrations;

use Zero\Database\DB;

/**
 * Class CreateShopTables
 *
 * Creates the shop tables: shop_categories, shop_products, shop_product_variants, shop_orders, and
 * shop_order_items.
 */
class CreateShopTables extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Creating Shop Module Database Tables...\n";

        DB::query("
            CREATE TABLE IF NOT EXISTS shop_categories (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT NULL,
                image VARCHAR(255) NULL,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                UNIQUE KEY site_category_slug_unique (site_id, slug),
                INDEX (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS shop_products (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                category_id VARCHAR(36) NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                sku VARCHAR(255) NULL,
                description TEXT NULL,
                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                compare_at_price DECIMAL(10, 2) NULL,
                main_image VARCHAR(255) NULL,
                media_ids TEXT NULL,
                status VARCHAR(20) DEFAULT 'published',
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                UNIQUE KEY site_product_slug_unique (site_id, slug),
                INDEX (site_id),
                INDEX (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS shop_product_variants (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                sku VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                stock INT NOT NULL DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                INDEX (site_id),
                INDEX (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS shop_orders (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                customer_name VARCHAR(255) NOT NULL,
                customer_email VARCHAR(255) NOT NULL,
                total_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                shipping_address TEXT NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                INDEX (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS shop_order_items (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                order_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                variant_id VARCHAR(36) NULL,
                title VARCHAR(255) NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                INDEX (site_id),
                INDEX (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Dropping Shop Module Database Tables...\n";
        DB::query("DROP TABLE IF EXISTS shop_order_items, shop_orders, shop_product_variants, shop_products, shop_categories;");
    }
}
