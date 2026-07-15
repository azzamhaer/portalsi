<?php

namespace App\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $connection = 'marketplace';

    protected $fillable = ['section', 'question', 'answer', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
