<?php

namespace Zero\Modules\Shop\Models;

use Zero\Interfaces\Model;
use Zero\Models\Traits\IsModel;

class ProductVariant implements Model
{
    use IsModel;

    protected static $tableName = 'shop_product_variants';
    protected static $fillable = ['site_id', 'product_id', 'title', 'sku', 'price', 'stock'];

    public $id;
    public $site_id;
    public $product_id;
    public $title;
    public $sku;
    public $price;
    public $stock;
    public $created_at;
    public $updated_at;

    public static function getConfig(): array
    {
        return [
            'id' => ['type' => 'text', 'label' => 'ID', 'editable' => false, 'listDisplay' => false],
            'product_id' => ['type' => 'text', 'label' => 'Product ID', 'editable' => true, 'required' => true, 'listDisplay' => true],
            'title' => ['type' => 'text', 'label' => 'Variant Option', 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'sku' => ['type' => 'text', 'label' => 'Variant SKU', 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'price' => ['type' => 'number', 'label' => 'Variant Price', 'editable' => true, 'required' => true, 'listDisplay' => true],
            'stock' => ['type' => 'number', 'label' => 'Stock Quantity', 'editable' => true, 'required' => true, 'listDisplay' => true],
        ];
    }
}
