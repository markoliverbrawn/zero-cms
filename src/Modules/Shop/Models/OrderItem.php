<?php

namespace Zero\Modules\Shop\Models;

use Zero\Interfaces\Model;
use Zero\Models\Traits\IsModel;

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

    public static function getConfig(): array
    {
        return [];
    }
}
