<?php

namespace App\Http\Controllers;

use App\Models\AppCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = AppCategory::where('parent_id', 0)
            ->with('childrenRecursive')
            ->orderBy('category_order')
            ->paginate(10);

        return view('categories.index', compact('categories'));
    }
}
