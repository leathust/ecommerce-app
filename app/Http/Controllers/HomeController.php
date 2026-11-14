<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorefrontCatalogRequest;
use App\Models\Product;
use App\Services\StorefrontCatalogService;
use App\Services\StorefrontHomeService;

class HomeController extends Controller
{
    //
    public function home(StorefrontHomeService $home)
    {
        return view('frontend.home', ['homePage' => $home->home(app()->getLocale())]);
    }

    public function shop(StorefrontCatalogRequest $request, StorefrontCatalogService $catalog)
    {
        $data = $catalog->shop($request->validated(), app()->getLocale());

        if ($request->ajax()) {
            return response()->json([
                'html' => view('frontend.shop.results', $data)->render(),
                'total' => $data['products']->total(),
            ]);
        }

        return view('frontend.shop', $data);
    }

    public function products()
    {
        return redirect()->route('shop');
    }

    public function product(Product $storefrontProduct, StorefrontCatalogService $catalog)
    {
        return view('frontend.products', $catalog->detail($storefrontProduct, app()->getLocale()));
    }

    public function contact()
    {
        return view('frontend.contact');
    }

    public function trackOrder()
    {
        return view('frontend.track-order');
    }

    public function help()
    {
        return view('frontend.help');
    }

}
