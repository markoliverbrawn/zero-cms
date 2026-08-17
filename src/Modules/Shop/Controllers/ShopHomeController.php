<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Controllers/ShopHomeController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Models\Page;
use Zero\Modules\Shop\Models\Product;

/**
 * Class ShopHomeController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class ShopHomeController implements Controller
{
    /**
     * Process the front-office shop homepage request.
     * Loads both featured products and the site homepage page record (blocks)
     * if they exist inside active tenant boundaries.
     *
     * @param mixed $param Custom parameter from Router
     * @return void
     */
    public function handle($param)
    {
        $siteId = App::getCurrentSiteId();
        
        // Load featured items (top 6 newest published products of this tenant site!)
        $featuredProducts = Product::where('site_id', $siteId, 'ORDER BY created_at DESC LIMIT 6');

        // Load the database homepage Page record (slug = "") if it exists!
        $homePage = Page::findBySlug('');

        App::render('home', [
            'featuredProducts' => $featuredProducts,
            'post' => $homePage // Pass it as 'post' to render blocks inside themes/kitchensink/home.php
        ]);
        exit;
    }
}
