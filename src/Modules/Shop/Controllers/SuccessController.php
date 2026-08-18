<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Controllers/SuccessController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Modules\Shop\Models\Order;

/**
 * Class SuccessController
 *
 * Renders the post-checkout confirmation page for a completed order.
 */
class SuccessController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        $siteId = App::getCurrentSiteId();
        $orderId = $_GET['order_id'] ?? '';

        $order = Order::find($orderId);

        if (!$order || $order->site_id !== $siteId) {
            \header('Location: /');
            exit;
        }

        $items = $order->getItems();

        App::render('success', [
            'order' => $order,
            'items' => $items
        ]);
        exit;
    }
}
