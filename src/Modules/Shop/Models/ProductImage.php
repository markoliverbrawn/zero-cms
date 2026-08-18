<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Models/ProductImage.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Models;

use Zero\Interfaces\Model;
use Zero\Models\Traits\IsModel;

/**
 * Class ProductImage
 *
 * Active Record model for a secondary product image, ordered beneath its parent product.
 */
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
