<?php


namespace App\Http\Controllers\API;

use App\Models\AppCategory;
use App\Models\Brand;
use App\Models\Size;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ProductAttribute;
use Illuminate\Support\Facades\DB;

class DiscoverController extends Controller
{
    public function filters()
    {
        $categories = AppCategory::withCount('products')
            ->where('parent_id', 0)
            ->with(['children' => function ($q) {
                $q->withCount('products')
                    ->with(['children' => function ($q2) {
                        $q2->withCount('products');
                    }]);
            }])
            ->get();

        $brands = Brand::withCount('products')->get();
        $sizes  = Size::withCount('products')->get();

        $colors = ProductAttribute::select('value as name', DB::raw('COUNT(DISTINCT product_id) as count'))
            ->where('type', 'color')
            ->whereHas('product', function ($q) {
                $q->where('status', 1)->where('stock', '>', 0);
            })
            ->groupBy('value')
            ->get();

        $styles = ProductAttribute::select('value as name', DB::raw('COUNT(DISTINCT product_id) as count'))
            ->where('type', 'style')
            ->whereHas('product', function ($q) {
                $q->where('status', 1)->where('stock', '>', 0);
            })
            ->groupBy('value')
            ->get();

        $bodyFits = ProductAttribute::select('value as name', DB::raw('COUNT(DISTINCT product_id) as count'))
            ->where('type', 'body_fit')
            ->whereHas('product', function ($q) {
                $q->where('status', 1)->where('stock', '>', 0);
            })
            ->groupBy('value')
            ->get();


        $priceRange = [
            'min' => Product::min('price'),
            'max' => Product::max('price'),
        ];

        return response()->json([
            'filters' => [
                'categories' => $categories,
                'brands'     => $brands,
                'sizes'      => $sizes,
                'colors'     => $colors,
                'styles'     => $styles,
                'body_fits'  => $bodyFits,
                'price'      => $priceRange,
            ],
        ]);
    }



    public function getFilteredProducts(Request $request)
    {
        $query = Product::with([
            'brand:id,name',
            'appCategory:id,slug',
            'productSizes.size',
            'attributes' // relation to ProductAttribute
        ]);

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('app_category_id', $request->category_id);
        }

        // Brand filter
        if ($request->filled('brand_ids')) {
            $query->whereIn('brand_id', (array) $request->brand_ids);
        }

        // Sizes filter
        if ($request->filled('size_ids')) {
            $query->whereHas('productSizes', function ($q) use ($request) {
                $q->whereIn('size_id', (array) $request->size_ids);
            });
        }

        // Colors filter
        if ($request->filled('colors')) {
            $query->whereHas('attributes', function ($q) use ($request) {
                $q->where('type', 'color')
                    ->whereIn('value', (array) $request->colors);
            });
        }

        // Style filter
        if ($request->filled('styles')) {
            $query->whereHas('attributes', function ($q) use ($request) {
                $q->where('type', 'style')
                    ->whereIn('value', (array) $request->styles);
            });
        }

        // Body fit filter
        if ($request->filled('body_fits')) {
            $query->whereHas('attributes', function ($q) use ($request) {
                $q->where('type', 'body_fit')
                    ->whereIn('value', (array) $request->body_fits);
            });
        }

        // Price filter
        if ($request->filled('price_min') && $request->filled('price_max')) {
            $query->whereBetween('price', [$request->price_min, $request->price_max]);
        }

        // Sorting
        if ($request->filled('sort_by')) {
            switch ($request->sort_by) {
                case 'price_low_high':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_high_low':
                    $query->orderBy('price', 'desc');
                    break;
                case 'latest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'popular':
                    $query->orderBy('views', 'desc'); // or sales count
                    break;
            }
        }

        $products = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }
}
