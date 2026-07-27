<?php

namespace Zero\Modules\Shop\Controllers\Api;

use Zero\Http\Controllers\ApiController;
use Zero\Modules\Shop\Models\Product;
use Zero\Modules\Shop\Models\ProductVariant;
use Zero\Core\App;
use Zero\Database\DB;

class ProductsController extends ApiController
{
    public function handle($matches)
    {
        // 1. Authenticate Request
        $user = $this->authenticate();

        $param = $matches[1] ?? '';

        if (empty($param)) {
            // Handle List: return all products belonging to active tenant with EAGER LOAD to avoid N+1 effects!
            $stmt = DB::query("
                SELECT shop_products.*, media.path AS main_image_path 
                FROM shop_products 
                LEFT JOIN media ON shop_products.main_image = media.id 
                WHERE shop_products.site_id = ? AND shop_products.deleted_at IS NULL
            ", [App::getCurrentSiteId()]);
            $productsData = $stmt->fetchAll();
            $products = [];
            foreach ($productsData as $row) {
                $products[] = new Product($row);
            }
            $output = [];
            foreach ($products as $prod) {
                $output[] = [
                    'id' => $prod->id,
                    'title' => $prod->title,
                    'slug' => $prod->slug,
                    'sku' => $prod->sku,
                    'price' => floatval($prod->price),
                    'compare_at_price' => $prod->compare_at_price ? floatval($prod->compare_at_price) : null,
                    'main_image' => $prod->main_image,
                    'status' => $prod->status,
                    'created_at' => $prod->created_at
                ];
            }
            $this->respond([
                'success' => true,
                'total' => count($output),
                'products' => $output
            ]);
        } else {
            // Handle Single View (search by ID or slug!)
            $product = null;
            if (strlen($param) === 36) {
                $product = Product::find($param);
            }
            if (!$product) {
                $product = Product::findBySlug($param);
            }

            if (!$product) {
                $this->respond([
                    'success' => false,
                    'error' => 'Product not found'
                ], 404);
            }

            // Load variants
            $variants = $product->getVariants();
            $variantsOutput = [];
            foreach ($variants as $v) {
                $variantsOutput[] = [
                    'id' => $v->id,
                    'title' => $v->title,
                    'sku' => $v->sku,
                    'price' => floatval($v->price),
                    'stock' => intval($v->stock)
                ];
            }

            // Load gallery
            $gallery = $product->getSecondaryImages();
            $galleryOutput = [];
            foreach ($gallery as $img) {
                $galleryOutput[] = $img['path'] ?? '';
            }

            $this->respond([
                'success' => true,
                'product' => [
                    'id' => $product->id,
                    'category_id' => $product->category_id,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'sku' => $product->sku,
                    'description' => $product->description,
                    'price' => floatval($product->price),
                    'compare_at_price' => $product->compare_at_price ? floatval($product->compare_at_price) : null,
                    'main_image' => $product->main_image,
                    'status' => $product->status,
                    'variants' => $variantsOutput,
                    'gallery' => $galleryOutput,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at
                ]
            ]);
        }
    }
}
