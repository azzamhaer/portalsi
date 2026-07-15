<?php

namespace App\Marketplace\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Marketplace\Models\Tag;

class TagController extends Controller
{
    public function index()
    {
        return response()->json(
            Tag::orderByDesc('product_count')->take(40)->get(['slug', 'name', 'product_count as count'])
        );
    }
}
