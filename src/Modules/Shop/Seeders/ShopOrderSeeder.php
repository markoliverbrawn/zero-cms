<?php
// src/Modules/Shop/Seeders/ShopOrderSeeder.php

namespace Zero\Modules\Shop\Seeders;

use Exception;
use Zero\Interfaces\SeederInterface;
use Zero\Database\DB;
use Zero\Support\Security;

class ShopOrderSeeder implements SeederInterface
{
    /**
     * Get the associated module identifier (e.g. 'shop', 'forum', 'blog').
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
     * @throws Exception If the module is not active for this site or no products exist
     */
    public function run(string $siteId, string $uploadsDir): void
    {
        // 1. Fetch site and verify the shop module is active
        $site = DB::query("SELECT id, name, enabled_modules FROM sites WHERE id = ? AND deleted_at IS NULL", [$siteId])->fetch();
        if (!$site) {
            throw new Exception("Seeding error: Target site ID '{$siteId}' not found.");
        }

        $enabled = json_decode($site['enabled_modules'] ?? '[]', true);
        if (!in_array('shop', $enabled)) {
            throw new Exception("Seeding error: Module 'shop' is not active for site '{$site['name']}'.");
        }

        echo "====================================================================\n";
        echo "ZERO CMS: DYNAMIC SHOP ORDERS SEEDER FOR '{$site['name']}'\n";
        echo "====================================================================\n";

        // 2. Retrieve products registered for this site
        $products = DB::query("SELECT id, title, price FROM shop_products WHERE site_id = ? AND deleted_at IS NULL", [$siteId])->fetchAll();

        if (empty($products)) {
            throw new Exception("Seeding error: No products found in database for shop site '{$site['name']}'. Seed products first before generating orders.");
        }

        echo "--> Found " . count($products) . " active shop products for '{$site['name']}'.\n";

        // 3. Clear existing orders/items for this site before re-seeding to keep it perfectly clean
        echo "--> Cleaning up existing orders & order items for site ID {$siteId}...\n";
        DB::query("DELETE FROM shop_order_items WHERE site_id = ?", [$siteId]);
        DB::query("DELETE FROM shop_orders WHERE site_id = ?", [$siteId]);

        // 4. Seeding Parameters
        $numOrders = 60; // Generate 60 realistic orders
        $statuses = ["completed", "shipped", "processing", "pending"];
        $firstNames = ["Alex", "Jordan", "Taylor", "Morgan", "Casey", "Jamie", "Riley", "Robin", "Drew", "Skyler", "Cameron", "Kim", "Pat", "Chris"];
        $lastNames = ["Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez", "Hernandez", "Lopez"];
        $streets = ["Neon Rd", "Cyber Way", "Fiber Lane", "Grid Ave", "Quantum Cres", "Titanium St", "Laser Blvd", "Hologram Dr"];
        $sectors = ["Sector 7", "Neo Tokyo", "Core City", "Grid 9", "District 4", "Hyper Sector"];

        echo "--> Initiating generation of {$numOrders} randomized orders...\n";

        $ordersCreated = 0;
        $itemsCreated = 0;

        for ($i = 0; $i < $numOrders; $i++) {
            // Generate order ID
            $orderId = Security::uuidv7();
            
            // Choose customer details
            $fname = $firstNames[array_rand($firstNames)];
            $lname = $lastNames[array_rand($lastNames)];
            $customerName = "{$fname} {$lname}";
            $customerEmail = strtolower($fname . "." . $lname . "_" . rand(100, 999) . "@example.com");
            $shippingAddress = rand(10, 999) . " " . $streets[array_rand($streets)] . ", Suite " . rand(1, 10) . ", " . $sectors[array_rand($sectors)];
            
            // Distribute order dates randomly over the last 30 days
            $daysAgo = rand(0, 30);
            $hoursAgo = rand(0, 23);
            $minsAgo = rand(0, 59);
            $secsAgo = rand(0, 59);
            $createdAt = gmdate('Y-m-d H:i:s', strtotime("-{$daysAgo} days -{$hoursAgo} hours -{$minsAgo} minutes -{$secsAgo} seconds"));
            
            // Select status (bias towards completed/shipped)
            $statusRoll = rand(1, 100);
            if ($statusRoll <= 50) {
                $status = "completed";
            } elseif ($statusRoll <= 75) {
                $status = "shipped";
            } elseif ($statusRoll <= 90) {
                $status = "processing";
            } else {
                $status = "pending";
            }
            
            // Determine items for this order (1 to 3 distinct items)
            $numItems = rand(1, 3);
            $selectedProductKeys = array_rand($products, $numItems);
            if (!is_array($selectedProductKeys)) {
                $selectedProductKeys = [$selectedProductKeys];
            }
            
            $totalPrice = 0;
            $orderItemsPayload = [];
            
            foreach ($selectedProductKeys as $prodKey) {
                $product = $products[$prodKey];
                $itemId = Security::uuidv7();
                $qty = rand(1, 3);
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
            
            // Insert order
            DB::query("
                INSERT INTO shop_orders (id, site_id, customer_name, customer_email, total_price, status, shipping_address, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$orderId, $siteId, $customerName, $customerEmail, $totalPrice, $status, $shippingAddress, $createdAt, $createdAt]);
            
            $ordersCreated++;
            
            // Insert corresponding items
            foreach ($orderItemsPayload as $item) {
                DB::query("
                    INSERT INTO shop_order_items (id, site_id, order_id, product_id, title, quantity, price, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [$item['id'], $item['site_id'], $item['order_id'], $item['product_id'], $item['title'], $item['quantity'], $item['price'], $item['created_at'], $item['created_at']]);
                
                $itemsCreated++;
            }
        }

        echo "====================================================================\n";
        echo "SUCCESS: Seeding Completed for '{$site['name']}'!\n";
        echo "--> Generated & Seeded {$ordersCreated} shop orders.\n";
        echo "--> Generated & Seeded {$itemsCreated} nested shop order items.\n";
        echo "====================================================================\n";
    }
}
