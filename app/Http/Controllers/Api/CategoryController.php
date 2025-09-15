<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::paginate(10);
        return response()->json($categories);
    }


    public function getCategoriesWithSizes(Request $request)
    {
        $type = strtolower($request->get('type', ''));

        // Load top-level categories with children
        $query = \App\Models\Category::with([
            'children.products.productSizes.size:id,name,type'
        ])->orderBy('id', 'asc');

        if ($type === 'women') {
            $query->where('slug', 'womens-clothing');
        } elseif ($type === 'men') {
            $query->where('slug', 'mens-clothing');
        } else {
            $query->whereIn('slug', ['womens-clothing', 'mens-clothing']);
        }

        $categories = $query->get(['id', 'slug', 'parent_id']);

        // Transform to only include children sizes
        $categories->transform(function ($category) {
            $category->children->transform(function ($child) {
                $child->sizes = $child->products
                    ->flatMap(fn($product) => $product->productSizes->pluck('size'))
                    ->unique('id')
                    ->values();

                return $child->only(['id', 'slug', 'parent_id', 'sizes']);
            });

            // Rename 'children' to 'categories' in the response
            $category->categories = $category->children;
            unset($category->children);

            return $category->only(['id', 'slug', 'parent_id', 'categories']);
        });
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }




}
