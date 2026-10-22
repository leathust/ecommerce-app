<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const CREATED_AT = null;
    protected $fillable = ['setting_key', 'setting_value', 'updated_by'];

    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
}
