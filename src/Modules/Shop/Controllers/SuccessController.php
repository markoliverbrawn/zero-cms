<?php

namespace Zero\Modules\Shop\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Shop\Models\Order;

class SuccessController implements Controller
{
    public function handle($param)
    {
        $siteId = App::getCurrentSiteId();
        $orderId = $_GET['order_id'] ?? '';

        $order = Order::find($orderId);

        if (!$order || $order->site_id !== $siteId) {
            header('Location: /');
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
