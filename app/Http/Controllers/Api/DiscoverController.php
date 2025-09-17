<?php


namespace App\Http\Controllers\API;

use App\Models\AppCategory;
use App\Models\Brand;
use App\Models\Size;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DiscoverController extends Controller
{
    public function filters()
    {
        $categories = AppCategory::where('parent_id', 0)
            ->with('children') // load subcategories
            ->get(['id', 'slug', 'parent_id']);

        $brands = Brand::select('id', 'name')->get();

        $sizes = Size::select('id', 'name')->get();

        $priceRange = [
            'min' => Product::min('price'),
            'max' => Product::max('price'),
        ];

        return response()->json([
            'categories' => $categories,
            'brands'     => $brands,
            'sizes'      => $sizes,
            'price'      => $priceRange,
        ]);
    }
}
