<?php

namespace Zero\Modules\Shop\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Database\DB;
use Zero\Modules\Shop\Models\Order;
use Zero\Modules\Shop\Models\OrderItem;
use Zero\Modules\Shop\Models\ProductVariant;
use Zero\Support\Security;

class CheckoutController implements Controller
{
    public function handle($param)
    {
        App::ensureSession();
        $cart = $_SESSION['cart'] ?? [];

        if (empty($cart)) {
            header('Location: /shop/cart');
            exit;
        }

        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $address = $_POST['address'] ?? '';

            if (empty($name) || empty($email) || empty($address)) {
                App::render('checkout', [
                    'cart' => $cart,
                    'subtotal' => $subtotal,
                    'error' => 'Please fill out all required fields.'
                ]);
                exit;
            }

            $siteId = App::getCurrentSiteId();
            $orderId = Security::uuidv7();

            // Initialize Order Model
            $order = new Order([
                'id' => $orderId,
                'site_id' => $siteId,
                'customer_name' => $name,
                'customer_email' => $email,
                'total_price' => $subtotal,
                'status' => 'paid', // Mark as paid for demo checkout simulation!
                'shipping_address' => $address,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s')
            ]);

            // Save order to database
            $order->save();

            // Create Order Items and update stocks
            foreach ($cart as $key => $item) {
                $itemId = Security::uuidv7();
                $orderItem = new OrderItem([
                    'id' => $itemId,
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?: null,
                    'title' => $item['title'] . ($item['variant_title'] ? ' - ' . $item['variant_title'] : ''),
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s')
                ]);
                $orderItem->save();

                // Inventory Deduction logic
                if (!empty($item['variant_id'])) {
                    $variant = ProductVariant::find($item['variant_id']);
                    if ($variant) {
                        $variant->stock = max(0, $variant->stock - $item['quantity']);
                        $variant->save();
                    }
                }
            }

            // Clear Cart Session
            $_SESSION['cart'] = [];

            // Forward to success screen
            header('Location: /shop/success?order_id=' . $orderId);
            exit;
        }

        App::render('checkout', [
            'cart' => $cart,
            'subtotal' => $subtotal
        ]);
        exit;
    }
}
