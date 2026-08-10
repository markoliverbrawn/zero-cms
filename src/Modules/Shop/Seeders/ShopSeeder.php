<?php

declare(strict_types=1);

/**
 * Zero CMS Shop Module Dynamic Seeder
 *
 * This class handles dynamic database seeding of the Shop module, executing both
 * declarative category and product loading, and procedural e-commerce order generation.
 *
 * @package Zero\Modules\Shop\Seeders
 */

namespace Zero\Modules\Shop\Seeders;

use Exception;
use Zero\Database\DB;
use Zero\Interfaces\SeederInterface;
use Zero\Support\Security;

/**
 * Class ShopSeeder
 *
 * Implements SeederInterface to populate category layouts, products, variations,
 * and randomized multi-tenant transaction history on bootstrap.
 */
class ShopSeeder implements SeederInterface
{
    /**
     * Get the associated module identifier.
     *
     * @return string
     */
    public function getModuleId(): string
    {
        return 'shop';
    }

    /**
     * Get the execution priority (lower numbers run first).
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 30;
    }

    /**
     * Run the dynamic seeder routine for a specific site ID.
     *
     * @param string $siteId The unique UUID of the site to seed
     * @param string $uploadsDir Absolute path to public uploads directory
     * @return void
     * @throws Exception If the module is not active for this site or blueprints are missing
     */
    public function run(string $siteId, string $uploadsDir): void
    {
        // 1. Fetch site and verify the shop module is active
        $site = DB::query("SELECT id, name, enabled_modules FROM sites WHERE id = ? AND deleted_at IS NULL", [$siteId])->fetch();
        if (!$site) {
            throw new Exception("Seeding error: Target site ID '{$siteId}' not found.");
        }

        $enabled = \json_decode($site['enabled_modules'] ?? '[]', true);
        if (!\in_array('shop', $enabled)) {
            throw new Exception("Seeding error: Module 'shop' is not active for site '{$site['name']}'.");
        }

        echo "====================================================================\n";
        echo "ZERO CMS: DYNAMIC E-COMMERCE SEEDER FOR '{$site['name']}'\n";
        echo "====================================================================\n";

        // Load the declarative blueprints from blueprints.php
        $shopDataPath = __DIR__ . '/blueprints.php';
        if (!\file_exists($shopDataPath)) {
            throw new Exception("Seeding error: blueprints.php file not found.");
        }
        $shopData = require $shopDataPath;
        $categoriesData = $shopData['shop_categories_blueprint'] ?? [];
        $productsData = $shopData['shop_products_blueprint'] ?? [];

        if (empty($categoriesData) || empty($productsData)) {
            throw new Exception("Seeding error: shop_categories_blueprint or shop_products_blueprint is empty.");
        }

        // 2. Clean out previous shop-specific records to prevent collisions or duplicates
        DB::query("DELETE FROM shop_order_items WHERE site_id = ?", [$siteId]);
        DB::query("DELETE FROM shop_orders WHERE site_id = ?", [$siteId]);
        DB::query("DELETE FROM shop_product_variants WHERE site_id = ?", [$siteId]);
        DB::query("DELETE FROM shop_products WHERE site_id = ?", [$siteId]);
        DB::query("DELETE FROM shop_categories WHERE site_id = ?", [$siteId]);

        // 3. Seed categories
        $categoryMap = [];
        foreach ($categoriesData as $key => $cat) {
            $catId = Security::uuidv7();
            
            $imagePath = null;
            $mediaRow = DB::query("SELECT path FROM media WHERE site_id = ? AND filename = ? LIMIT 1", [$siteId, $cat['image_file']])->fetch();
            if ($mediaRow) {
                $imagePath = $mediaRow['path'];
            }

            DB::query("
                INSERT INTO shop_categories (id, site_id, title, slug, description, image, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $catId,
                $siteId,
                $cat['title'],
                $key,
                $cat['description'],
                $imagePath
            ]);

            $categoryMap[$key] = $catId;
            echo "   Created Category: '{$cat['title']}' (ID: {$catId})\n";
        }

        // 4. Seed products
        $productsList = [];
        foreach ($productsData as $p) {
            $productId = Security::uuidv7();
            $catId = $categoryMap[$p['category']];

            $imagePath = null;
            $mediaRow = DB::query("SELECT path FROM media WHERE site_id = ? AND filename = ? LIMIT 1", [$siteId, $p['image_file']])->fetch();
            if ($mediaRow) {
                $imagePath = $mediaRow['path'];
            }

            DB::query("
                INSERT INTO shop_products (id, site_id, category_id, title, slug, sku, description, price, compare_at_price, main_image, media_ids, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW(), NOW())
            ", [
                $productId,
                $siteId,
                $catId,
                $p['title'],
                $p['slug'],
                $p['sku'],
                $p['description'],
                $p['price'],
                $p['compare_price'] ?? null,
                $imagePath,
                $imagePath
            ]);

            $productsList[] = [
                'id' => $productId,
                'title' => $p['title'],
                'price' => $p['price']
            ];

            foreach ($p['variants'] as $v) {
                $variantId = Security::uuidv7();
                DB::query("
                    INSERT INTO shop_product_variants (id, site_id, product_id, title, sku, price, stock, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    $variantId,
                    $siteId,
                    $productId,
                    $v['title'],
                    $v['sku'],
                    $v['price'],
                    $v['stock']
                ]);
            }

            echo "   Created Product: '{$p['title']}' ({$p['sku']}) with " . \count($p['variants']) . " variants\n";
        }

        echo "--> Successfully seeded " . \count($productsList) . " target products and variants.\n";

        // 5. Generate and seed orders procedural stream
        $numOrders = 60;
        $statuses = ["completed", "shipped", "processing", "pending"];
        $firstNames = ["Alex", "Jordan", "Taylor", "Morgan", "Casey", "Jamie", "Riley", "Robin", "Drew", "Skyler", "Cameron", "Kim", "Pat", "Chris"];
        $lastNames = ["Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez", "Hernandez", "Lopez"];
        $streets = ["Neon Rd", "Cyber Way", "Fiber Lane", "Grid Ave", "Quantum Cres", "Titanium St", "Laser Blvd", "Hologram Dr"];
        $sectors = ["Sector 7", "Neo Tokyo", "Core City", "Grid 9", "District 4", "Hyper Sector"];

        echo "--> Initiating generation of {$numOrders} randomized orders based on new products...\n";

        $ordersCreated = 0;
        $itemsCreated = 0;

        for ($i = 0; $i < $numOrders; $i++) {
            $orderId = Security::uuidv7();

            $fname = $firstNames[\array_rand($firstNames)];
            $lname = $lastNames[\array_rand($lastNames)];
            $customerName = "{$fname} {$lname}";
            $customerEmail = \strtolower($fname . "." . $lname . "_" . \rand(100, 999) . "@example.com");
            $shippingAddress = \rand(10, 999) . " " . $streets[\array_rand($streets)] . ", Suite " . \rand(1, 10) . ", " . $sectors[\array_rand($sectors)];

            $daysAgo = \rand(0, 30);
            $hoursAgo = \rand(0, 23);
            $minsAgo = \rand(0, 59);
            $secsAgo = \rand(0, 59);
            $createdAt = \gmdate('Y-m-d H:i:s', \strtotime("-{$daysAgo} days -{$hoursAgo} hours -{$minsAgo} minutes -{$secsAgo} seconds"));

            $statusRoll = \rand(1, 100);
            if ($statusRoll <= 50) {
                $status = "completed";
            } elseif ($statusRoll <= 75) {
                $status = "shipped";
            } elseif ($statusRoll <= 90) {
                $status = "processing";
            } else {
                $status = "pending";
            }

            $numItems = \rand(1, 3);
            $selectedProductKeys = \array_rand($productsList, $numItems);
            if (!\is_array($selectedProductKeys)) {
                $selectedProductKeys = [$selectedProductKeys];
            }

            $totalPrice = 0;
            $orderItemsPayload = [];

            foreach ($selectedProductKeys as $prodKey) {
                $product = $productsList[$prodKey];
                $itemId = Security::uuidv7();
                $qty = \rand(1, 3);
                $price = $product['price'];
                $itemTotal = $qty * $price;
                $totalPrice += $itemTotal;

                $orderItemsPayload[] = [
                    'id' => $itemId,
                    'site_id' => $siteId,
                    'order_id' => $orderId,
                    'product_id' => $product['id'],
                    'title' => $product['title'],
                    'quantity' => $qty,
                    'price' => $price,
                    'created_at' => $createdAt
                ];
            }

            DB::query("
                INSERT INTO shop_orders (id, site_id, customer_name, customer_email, total_price, status, shipping_address, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$orderId, $siteId, $customerName, $customerEmail, $totalPrice, $status, $shippingAddress, $createdAt, $createdAt]);

            $ordersCreated++;

            foreach ($orderItemsPayload as $item) {
                DB::query("
                    INSERT INTO shop_order_items (id, site_id, order_id, product_id, title, quantity, price, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [$item['id'], $item['site_id'], $item['order_id'], $item['product_id'], $item['title'], $item['quantity'], $item['price'], $item['created_at'], $item['created_at']]);

                $itemsCreated++;
            }
        }

        echo "SUCCESS: Seeding Completed for '{$site['name']}'!\n";
        echo "--> Generated & Seeded {$ordersCreated} shop orders.\n";
        echo "--> Generated & Seeded {$itemsCreated} nested shop order items.\n";
    }
}
