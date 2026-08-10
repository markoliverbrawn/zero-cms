<?php
/**
 * File: src/Modules/Shop/Controllers/ProductViewController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Shop\Models\Product;

/**
 * Class ProductViewController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class ProductViewController implements Controller
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
