<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    //
    public function login()
    {
        return view('pages.login.login');
    }

    public function register()
    {
        return view('pages.register.register');
    }

    public function forgetPassword()
    {
        return view('pages.forget-password.forget-password');
    }

    public function page404()
    {
        return view('pages.404.404');
    }

    public function page500()
    {
        return view('pages.500.500');
    }

    public function maintenance()
    {
        return view('pages.maintenance.maintenance');
    }
}
