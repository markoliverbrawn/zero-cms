<?php

namespace Zero\Modules\Shop\Models;

use Zero\Interfaces\Model;
use Zero\Models\Traits\IsModel;

class ProductImage implements Model
{
    use IsModel;

    protected static $tableName = 'shop_product_images';
    protected static $fillable = ['site_id', 'product_id', 'url', 'sort_order'];

    public $id;
    public $site_id;
    public $product_id;
    public $url;
    public $sort_order;
    public $created_at;
    public $updated_at;

    public static function getConfig(): array
    {
        return [];
    }
}
