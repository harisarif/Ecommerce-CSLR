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
            ->with('children.children')
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

    public function discover(Request $request)
    {
        $query = Product::query()
            ->with(['brand:id,name', 'appCategory:id,slug', 'sizes:id,name', 'images']);

        if ($request->filled('category_id')) {
            $query->where('app_category_id', $request->category_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('size_id')) {
            $query->whereHas('sizes', function ($q) use ($request) {
                $q->where('sizes.id', $request->size_id);
            });
        }

        if ($request->filled('min_price') && $request->filled('max_price')) {
            $query->whereBetween('price', [$request->min_price, $request->max_price]);
        }

        if ($request->sort === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($request->sort === 'price_desc') {
            $query->orderBy('price', 'desc');
        } elseif ($request->sort === 'newest') {
            $query->latest();
        }

        $products = $query->paginate(20);

        return response()->json($products);
    }

}
