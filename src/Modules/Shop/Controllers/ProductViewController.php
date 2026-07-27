<?php

namespace Zero\Modules\Shop\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Shop\Models\Product;

class ProductViewController implements Controller
{
    public function handle($param)
    {
        $siteId = App::getCurrentSiteId();
        $slug = $param[1] ?? '';

        // Find the product by its slug inside the active tenant site!
        $product = Product::findBySlug($slug);

        if (!$product || $product->site_id !== $siteId || $product->status !== 'published') {
            http_response_code(404);
            echo "Product not found";
            exit;
        }

        // Fetch variants and gallery images
        $variants = $product->getVariants();
        $gallery = $product->getSecondaryImages();

        App::render('product', [
            'product' => $product,
            'variants' => $variants,
            'gallery' => $gallery
        ]);
        exit;
    }
}
