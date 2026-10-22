<?php

namespace App\Http\Controllers;

class AdminController extends Controller
{
    //
    public function dashboard()
    {
        return view('admin.dashboard.dashboard1');
    }

    public function dashboard2()
    {
        return view('admin.dashboard.dashboard2');
    }

    public function dashboard3()
    {
        return view('admin.dashboard.dashboard3');
    }

    public function dashboard4()
    {
        return view('admin.dashboard.dashboard4');
    }

    public function charts()
    {
        return view('admin.charts.charts');
    }

    public function tables()
    {
        return view('admin.tables.tables');
    }

    public function dataTables()
    {
        return view('admin.tables.data-table');
    }

    public function forms()
    {
        return view('admin.forms.form');
    }

    public function wizards()
    {
        return view('admin.forms.wizard');
    }

    public function calendar()
    {
        return view('admin.calendar.index');
    }

    public function maps()
    {
        return view('admin.maps.map');
    }

    public function inbox()
    {
        return view('admin.inbox.inbox');
    }

    public function kanban()
    {
        return view('admin.kanban.kanban');
    }

    public function notifications()
    {
        return view('admin.notifications.notification');
    }

    public function documentation()
    {
        return view('admin.documentations.documentation');
    }

    public function products()
    {
        return view('admin.products');
    }

    public function orders()
    {
        return view('admin.orders');
    }

    public function customers()
    {
        return view('admin.customers');
    }
}
