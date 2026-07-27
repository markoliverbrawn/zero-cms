<?php

namespace Zero\Modules\Shop\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Database\DB;
use Zero\Modules\Shop\Models\Product;
use Zero\Modules\Shop\Models\Category;

class CatalogController implements Controller
{
    public function handle($param)
    {
        $siteId = App::getCurrentSiteId();
        
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = 12; // Show 12 items per page for a beautiful grid!
        
        // Search & Filter parameters
        $search = $_GET['search'] ?? '';
        $minPrice = floatval($_GET['min_price'] ?? 0);
        $maxPrice = floatval($_GET['max_price'] ?? 0);
        $sort = $_GET['sort'] ?? 'newest';
        $categorySlug = $_GET['category'] ?? '';

        // Construct dynamic query with EAGER LOAD main_image_path in exactly ONE query to prevent N+1 effects!
        $sql = "
            SELECT shop_products.*, media.path AS main_image_path 
            FROM shop_products 
            LEFT JOIN media ON shop_products.main_image = media.id 
            WHERE shop_products.site_id = ? AND shop_products.status = 'published' AND shop_products.deleted_at IS NULL
        ";
        $params = [$siteId];

        // Filter by category if slug is set
        $activeCategory = null;
        if (!empty($categorySlug)) {
            $activeCategory = Category::findBySlug($categorySlug);
            if ($activeCategory) {
                $sql .= " AND shop_products.category_id = ?";
                $params[] = $activeCategory->id;
            }
        }

        if (!empty($search)) {
            $sql .= " AND (shop_products.title LIKE ? OR shop_products.description LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        if ($minPrice > 0) {
            $sql .= " AND shop_products.price >= ?";
            $params[] = $minPrice;
        }

        if ($maxPrice > 0) {
            $sql .= " AND shop_products.price <= ?";
            $params[] = $maxPrice;
        }

        // Apply sorting
        if ($sort === 'price_asc') {
            $sql .= " ORDER BY shop_products.price ASC";
        } elseif ($sort === 'price_desc') {
            $sql .= " ORDER BY shop_products.price DESC";
        } else {
            $sql .= " ORDER BY shop_products.created_at DESC";
        }

        // Handle raw SQL pagination manually for maximum precision and speed
        $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as sub";
        $totalRows = DB::query($countSql, $params)->fetch()['total'] ?? 0;
        
        $totalPages = ceil($totalRows / $perPage);
        $offset = ($page - 1) * $perPage;

        $sql .= " LIMIT " . intval($perPage) . " OFFSET " . intval($offset);

        $productsData = DB::query($sql, $params)->fetchAll();
        
        // Map raw arrays to Product Models
        $products = [];
        foreach ($productsData as $row) {
            $products[] = new Product($row);
        }

        // Load all available shop categories belonging to this site
        $categories = Category::all();

        App::render('catalog', [
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'categorySlug' => $categorySlug,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'sort' => $sort,
            'totalItems' => $totalRows
        ]);
        exit;
    }
}
