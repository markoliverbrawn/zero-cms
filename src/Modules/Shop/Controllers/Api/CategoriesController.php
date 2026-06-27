<?php

namespace Zero\Modules\Shop\Controllers\Api;

use Zero\Http\Controllers\ApiController;
use Zero\Modules\Shop\Models\Category;

class CategoriesController extends ApiController
{
    public function handle($matches)
    {
        // 1. Authenticate Request
        $user = $this->authenticate();

        $param = $matches[1] ?? '';

        if (empty($param)) {
            $categories = Category::all();
            $output = [];
            foreach ($categories as $cat) {
                $output[] = [
                    'id' => $cat->id,
                    'title' => $cat->title,
                    'slug' => $cat->slug,
                    'description' => $cat->description,
                    'created_at' => $cat->created_at
                ];
            }
            $this->respond([
                'success' => true,
                'total' => count($output),
                'categories' => $output
            ]);
        } else {
            $category = null;
            if (strlen($param) === 36) {
                $category = Category::find($param);
            }
            if (!$category) {
                $category = Category::findBySlug($param);
            }

            if (!$category) {
                $this->respond([
                    'success' => false,
                    'error' => 'Category not found'
                ], 404);
            }

            $this->respond([
                'success' => true,
                'category' => [
                    'id' => $category->id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at
                ]
            ]);
        }
    }
}
