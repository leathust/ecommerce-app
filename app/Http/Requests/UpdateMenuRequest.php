<?php

namespace App\Http\Requests;

class UpdateMenuRequest extends SaveMenuRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('menus.update') === true;
    }
}
