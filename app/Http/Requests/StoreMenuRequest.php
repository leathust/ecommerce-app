<?php

namespace App\Http\Requests;

class StoreMenuRequest extends SaveMenuRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('menus.create') === true;
    }
}
