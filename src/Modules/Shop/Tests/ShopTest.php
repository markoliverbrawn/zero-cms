<?php
// src/Modules/Shop/Tests/ShopTest.php
// Integration and unit tests for Luxe E-Commerce storefront and transaction processing (Zero\Modules\Shop)

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Modules\Shop\Controllers\CheckoutController;
use Zero\Modules\Shop\Models\Order;
use Zero\Modules\Shop\Models\OrderItem;
use Zero\Modules\Shop\Models\Product;
use Zero\Modules\Shop\Models\ProductVariant;
use Zero\Support\Security;

echo "=== Shop Module Integration Tests ===\n";

// 1. Setup clean environment and mock context
echo "Bootstrapping mock site and shop environments...\n";

// Reset App state
$refApp = new ReflectionClass(App::class);
$propBoot = $refApp->getProperty('bootstrapped');
$propBoot->setAccessible(true);
$propBoot->setValue(null, false);

$propSite = $refApp->getProperty('currentSite');
$propSite->setAccessible(true);
$propSite->setValue(null, null);

// Mock request headers
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'test-shop.zero';

// Clean up old tables
DB::query("DELETE FROM shop_order_items");
DB::query("DELETE FROM shop_orders");
DB::query("DELETE FROM shop_product_variants");
DB::query("DELETE FROM shop_products");
DB::query("DELETE FROM sites WHERE domain = 'test-shop.zero'");

// Insert mock site
$mockSiteId = Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, 'Test Shop Site', 'test-shop.zero', 'default', '[\"shop\"]', NOW(), NOW())
", [$mockSiteId]);

App::bootstrap();
$siteId = App::getCurrentSiteId();
assert_test($siteId === $mockSiteId, "Shop site is successfully bootstrapped and resolved");

// 2. Setup mock Catalog (Product and Variant)
echo "Creating product catalog...\n";

$productId = Security::uuidv7();
$product = new Product([
    'id' => $productId,
    'site_id' => $siteId,
    'title' => 'Luxe Gold Ring',
    'slug' => 'luxe-gold-ring',
    'sku' => 'LUXE-RING-01',
    'description' => 'A beautiful pure gold ring.',
    'price' => 250.00,
    'status' => 'published',
    'created_at' => gmdate('Y-m-d H:i:s'),
    'updated_at' => gmdate('Y-m-d H:i:s')
]);
$product->save();

$variantId = Security::uuidv7();
$variant = new ProductVariant([
    'id' => $variantId,
    'site_id' => $siteId,
    'product_id' => $productId,
    'title' => 'Size 7',
    'sku' => 'LUXE-RING-01-S7',
    'price' => 250.00,
    'stock' => 10,
    'created_at' => gmdate('Y-m-d H:i:s'),
    'updated_at' => gmdate('Y-m-d H:i:s')
]);
$variant->save();

// Verify persistence
$fetchedProduct = Product::find($productId);
assert_test($fetchedProduct !== null, "Product is successfully created and persisted");
assert_test($fetchedProduct->title === 'Luxe Gold Ring', "Product fields are correctly hydrated");

$fetchedVariant = ProductVariant::find($variantId);
assert_test($fetchedVariant !== null, "Product variant is successfully created and persisted");
assert_test((int)$fetchedVariant->stock === 10, "Product variant stock is correctly set");

// 3. Simulate shopping cart session and checkout execution
echo "Simulating transactional checkout...\n";

// Set up mock session cart
$_SESSION['cart'] = [
    $variantId => [
        'product_id' => $productId,
        'variant_id' => $variantId,
        'title' => 'Luxe Gold Ring',
        'variant_title' => 'Size 7',
        'price' => 250.00,
        'quantity' => 2
    ]
];

// Mock form submission details
$_POST['name'] = 'Alice Sterling';
$_POST['email'] = 'alice@sterling.co';
$_POST['address'] = '742 Evergreen Terrace, Springfield';

// Generate mock CSRF token to pass CsrfMiddleware check
$csrfToken = Security::csrfToken();
$_POST['csrf_token'] = $csrfToken;

// Execute Checkout Controller using try/catch as it redirects on success
$controller = new CheckoutController();
try {
    $controller->handle(null);
} catch (Exception $e) {
    // In case controller tries to call header() or exit
}

// 4. Verify transaction results (Order & OrderItem persistence)
echo "Verifying checkout persistence and inventory updates...\n";

$orders = Order::all();
assert_test(count($orders) === 1, "Exactly one order is successfully written to database");

$order = $orders[0];
assert_test($order->customer_name === 'Alice Sterling', "Order customer name is persisted correctly");
assert_test($order->customer_email === 'alice@sterling.co', "Order customer email is persisted correctly");
assert_test((float)$order->total_price === 500.00, "Order total price (subtotal) is computed and persisted correctly");

$items = OrderItem::all();
assert_test(count($items) === 1, "Exactly one order item is successfully written to database");

$orderItem = $items[0];
assert_test($orderItem->order_id === $order->id, "Order item is correctly linked back to parent order");
assert_test($orderItem->product_id === $productId, "Order item captures correct product");
assert_test($orderItem->variant_id === $variantId, "Order item captures correct product variant");
assert_test((int)$orderItem->quantity === 2, "Order item captures correct checkout quantity");

// Verify Inventory Deduction
$updatedVariant = ProductVariant::find($variantId);
assert_test((int)$updatedVariant->stock === 8, "Product variant inventory stock correctly decremented from 10 to 8");

// 5. Test Cascading Delete Integrity (Order -> OrderItems)
echo "Testing Active Record cascading deletions...\n";

// Deleting the order should cascade to delete the linked order items
$order->delete();
$remainingItems = OrderItem::all();
assert_test(count($remainingItems) === 0, "Deleting an Order cascadingly purges related OrderItems");

// Deleting the product should cascade to delete the linked product variants
$product->delete();
$remainingVariants = ProductVariant::all();
assert_test(count($remainingVariants) === 0, "Deleting a Product cascadingly purges related ProductVariants");

echo "Shop module integration tests completed successfully!\n";
