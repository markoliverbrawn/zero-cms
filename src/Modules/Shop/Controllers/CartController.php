<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Controllers/CartController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Modules\Shop\Models\Product;
use Zero\Modules\Shop\Models\ProductVariant;

/**
 * Class CartController
 *
 * Maintains the visitor's cart -- adding, updating, and removing lines -- and renders the cart
 * view.
 */
class CartController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        App::ensureSession();
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            $action = $_POST['action'] ?? '';

            if ($action === 'add') {
                $productId = $_POST['product_id'] ?? '';
                $variantId = $_POST['variant_id'] ?? '';
                $quantity = \max(1, \intval($_POST['quantity'] ?? 1));

                $product = Product::find($productId);
                if ($product) {
                    $variant = !empty($variantId) ? ProductVariant::find($variantId) : null;
                    
                    // Create unique key for product + variant combination
                    $itemKey = $productId . '_' . ($variantId ?: 'default');
                    
                    if (isset($_SESSION['cart'][$itemKey])) {
                        $_SESSION['cart'][$itemKey]['quantity'] += $quantity;
                    } else {
                        $_SESSION['cart'][$itemKey] = [
                            'product_id' => $productId,
                            'variant_id' => $variantId,
                            'title' => $product->title,
                            'variant_title' => $variant ? $variant->title : '',
                            'price' => $variant ? $variant->price : $product->price,
                            'main_image' => $product->main_image,
                            'quantity' => $quantity,
                            'sku' => $variant ? $variant->sku : $product->sku,
                            'slug' => $product->slug
                        ];
                    }
                }
            } elseif ($action === 'update') {
                $itemKey = $_POST['item_key'] ?? '';
                $quantity = \max(0, \intval($_POST['quantity'] ?? 1));

                if (isset($_SESSION['cart'][$itemKey])) {
                    if ($quantity === 0) {
                        unset($_SESSION['cart'][$itemKey]);
                    } else {
                        $_SESSION['cart'][$itemKey]['quantity'] = $quantity;
                    }
                }
            } elseif ($action === 'remove') {
                $itemKey = $_POST['item_key'] ?? '';
                if (isset($_SESSION['cart'][$itemKey])) {
                    unset($_SESSION['cart'][$itemKey]);
                }
            }

            // Redirect back to the cart view to avoid form resubmission
            \header('Location: /shop/cart');
            exit;
        }

        // Calculate cart metrics
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $site = App::getCurrentSite();

        App::render('cart', [
            'cart' => $_SESSION['cart'],
            'subtotal' => $subtotal,
            'currencySymbol' => $site ? $site->getModuleSetting('shop', 'currency_symbol', '$') : '$',
            'freeShippingThreshold' => $site ? (float)$site->getModuleSetting('shop', 'free_shipping_threshold', 150) : 150,
            'standardShippingCost' => $site ? (float)$site->getModuleSetting('shop', 'standard_shipping_cost', 15) : 15
        ]);
        exit;
    }
}
