<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = ['question_vi', 'question_en', 'answer_vi', 'answer_en', 'sort_order', 'status'];
    protected $casts = ['status' => 'boolean'];
}
