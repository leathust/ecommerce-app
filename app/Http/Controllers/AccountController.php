<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountController extends Controller
{
    //
    public function profile()
    {
        return view('account.profile.profile');
    }

    public function pricing()
    {
        return view('account.pricing.pricing');
    }

    public function invoice()
    {
        return view('account.invoice.invoice');
    }
}
