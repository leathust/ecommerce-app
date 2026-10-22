<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ElementController extends Controller
{
    //
    public function buttons()
    {
        return view('elements.buttons');
    }

    public function badges()
    {
        return view('elements.badges');
    }

    public function tabs()
    {
        return view('elements.tabs');
    }

    public function cards()
    {
        return view('elements.cards');
    }

    public function alerts()
    {
        return view('elements.alerts');
    }

    public function progressBars()
    {
        return view('elements.progress-bars');
    }

    public function modals()
    {
        return view('elements.modals');
    }

    public function switches()
    {
        return view('elements.switches');
    }

    public function gridSystem()
    {
        return view('elements.grid-system');
    }

    public function frontAwesome()
    {
        return view('elements.font-awesome');
    }

    public function typography()
    {
        return view('elements.typography');
    }
}
