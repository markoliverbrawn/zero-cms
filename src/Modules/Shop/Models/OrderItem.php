<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Models/OrderItem.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Models;

use Zero\Interfaces\Model;
use Zero\Models\Traits\IsModel;

/**
 * Class OrderItem
 *
 * Active Record model for one line of an order, holding the quantity and the price captured at the
 * time of purchase.
 */
class OrderItem implements Model
{
    use IsModel;

    protected static $tableName = 'shop_order_items';
    protected static $modelType = 'order_item';
    protected static $fillable = ['site_id', 'order_id', 'product_id', 'variant_id', 'title', 'quantity', 'price'];

    public $id;
    public $site_id;
    public $order_id;
    public $product_id;
    public $variant_id;
    public $title;
    public $quantity;
    public $price;
    public $created_at;
    public $updated_at;

    /**
     * Retrieves the config attribute value.
     *
     * @return mixed Response output.
     */
    public static function getConfig(): array
    {
        return [];
    }
}
