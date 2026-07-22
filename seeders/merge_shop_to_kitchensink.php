<?php
// seeders/merge_shop_to_kitchensink.php
// Script to merge the full premium product catalog from shop.json into kitchensink.json

$shopPath = __DIR__ . '/data/shop.json';
$sinkPath = __DIR__ . '/data/kitchensink.json';

if (!file_exists($shopPath) || !file_exists($sinkPath)) {
    echo "Error: Blueprint files not found!\n";
    exit(1);
}

$shop = json_decode(file_get_contents($shopPath), true);
$sink = json_decode(file_get_contents($sinkPath), true);

if (!is_array($shop) || !is_array($sink)) {
    echo "Error: Failed to parse blueprint JSONs.\n";
    exit(1);
}

echo "==================================================\n";
echo "MERGING SHOP PRODUCT CATALOG TO KITCHEN SINK\n";
echo "==================================================\n";

$targetDomain = 'd6laptop.zero.kitchensink';

// 1. Copy Media Assets from shop.json
$copiedMediaCount = 0;
if (isset($shop['media']) && is_array($shop['media'])) {
    foreach ($shop['media'] as $media) {
        // Change site domain context to kitchensink
        $media['site_domain'] = $targetDomain;
        
        // Ensure no duplicate media is inserted
        $exists = false;
        foreach ($sink['media'] as $existing) {
            if ($existing['filename'] === $media['filename']) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $sink['media'][] = $media;
            $copiedMediaCount++;
        }
    }
}
echo "--> Merged and registered {$copiedMediaCount} new media records.\n";

// 2. Overwrite Shop Categories from shop.json
$categories = [];
if (isset($shop['shop_categories']) && is_array($shop['shop_categories'])) {
    foreach ($shop['shop_categories'] as $cat) {
        $cat['site_domain'] = $targetDomain;
        $categories[] = $cat;
    }
}
$sink['shop_categories'] = $categories;
echo "--> Copied " . count($categories) . " product categories.\n";

// 3. Overwrite Shop Products from shop.json
$products = [];
if (isset($shop['shop_products']) && is_array($shop['shop_products'])) {
    foreach ($shop['shop_products'] as $prod) {
        $prod['site_domain'] = $targetDomain;
        $products[] = $prod;
    }
}
$sink['shop_products'] = $products;
echo "--> Copied " . count($products) . " products.\n";

// 4. Overwrite Shop Product Variants from shop.json
$variants = [];
if (isset($shop['shop_product_variants']) && is_array($shop['shop_product_variants'])) {
    foreach ($shop['shop_product_variants'] as $v) {
        $v['site_domain'] = $targetDomain;
        $variants[] = $v;
    }
}
$sink['shop_product_variants'] = $variants;
echo "--> Copied " . count($variants) . " product variants.\n";

// Save the updated kitchensink.json
file_put_contents($sinkPath, json_encode($sink, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n✅ Successfully merged shop catalog into kitchensink.json!\n";
echo "==================================================\n";
