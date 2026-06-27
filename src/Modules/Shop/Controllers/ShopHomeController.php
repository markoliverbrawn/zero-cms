<?php

namespace Zero\Modules\Shop\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Shop\Models\Product;

class ShopHomeController implements Controller
{
    public function handle($param)
    {
        $siteId = App::getCurrentSiteId();
        
        // Load featured items (top 6 newest published products of this tenant site!)
        $featuredProducts = Product::where('site_id', $siteId, 'ORDER BY created_at DESC LIMIT 6');

        App::render('home', [
            'featuredProducts' => $featuredProducts
        ]);
        exit;
    }
}
