<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Controllers/Api/CategoriesController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Controllers\Api
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Controllers\Api;

use Zero\Http\Controllers\ApiController;
use Zero\Modules\Shop\Models\Category;

/**
 * Class CategoriesController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class CategoriesController extends ApiController
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
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
                'total' => \count($output),
                'categories' => $output
            ]);
        } else {
            $category = null;
            if (\strlen($param) === 36) {
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
