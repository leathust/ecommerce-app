<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    //
    public function home()
    {
        return view('frontend.home');
    }

    public function cart()
    {
        return view('frontend.cart');
    }

    public function shop()
    {
        return view('frontend.shop');
    }

    public function products()
    {
        return view('frontend.products');
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

    public function language()
    {
        return view('frontend.language');
    }

    public function account()
    {
        return view('frontend.account');
    }
}
