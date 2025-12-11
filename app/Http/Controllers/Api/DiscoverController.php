<?php


namespace App\Http\Controllers\Api;

use App\Models\AppCategory;
use App\Models\Brand;
use App\Models\Size;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\BodyFit;
use App\Models\ProductAttribute;
use App\Models\Style;
use Illuminate\Support\Facades\DB;

class DiscoverController extends Controller
{
    public function filters()
    {
        // $categories = AppCategory::select('id', 'slug', 'parent_id') // keep parent_id for relation
        //     ->withCount('products') // add product count
        //     ->where('parent_id', 0)
        //     ->with(['children' => function ($q) {
        //         $q->select('id', 'slug', 'parent_id')
        //             ->withCount('products')
        //             ->with(['children' => function ($q2) {
        //                 $q2->select('id', 'slug', 'parent_id')
        //                     ->withCount('products');
        //             }]);
        //     }])
        //     ->get();

        $categories = AppCategory::select('id', 'slug', 'parent_id')
        ->where('parent_id', 0)
        ->with([
            'children.children.children', // load deeper children
            'children' => function ($q) {
                $q->select('id', 'slug', 'parent_id')
                    ->with(['children' => function ($q2) {
                        $q2->select('id', 'slug', 'parent_id')
                            ->with('children');
                    }]);
            }
        ])
        ->get()
        ->map(function ($cat) {
            return [
                'id' => $cat->id,
                'slug' => $cat->slug,
                'parent_id' => $cat->parent_id,
                'products_count' => $cat->total_products, // recursive total
                'children' => $cat->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'slug' => $child->slug,
                        'parent_id' => $child->parent_id,
                        'products_count' => $child->total_products, // recursive total
                        'children' => $child->children->map(function ($sub) {
                            return [
                                'id' => $sub->id,
                                'slug' => $sub->slug,
                                'parent_id' => $sub->parent_id,
                                'products_count' => $sub->total_products, // recursive total
                            ];
                        })
                    ];
                })
            ];
        });


        $brands = Brand::withCount('products')->get();
        $brands->prepend([
            'id'    => 0,
            'name'  => 'All Brands',
            'products_count' => Product::count(), // TOTAL products
        ]);
        $sizes  = Size::withCount('products')->get();

        $colorMap = collect(config('colors'))
            ->mapWithKeys(function ($hex, $name) {
                // normalize keys: remove spaces, lowercase
                $normalized = strtolower(str_replace(' ', '', $name));
                return [$normalized => $hex];
            });

        $colors = ProductAttribute::select('value as name', DB::raw('COUNT(DISTINCT product_id) as count'))
            ->where('type', 'color')
            ->whereHas('product', function ($q) {
                $q->where('status', 1);
            })
            ->groupBy('value')
            ->get()
            ->map(function ($item) use ($colorMap) {
                // normalize DB name too
                $normalized = strtolower(str_replace(' ', '', $item->name));
                $item->hex_code = $colorMap[$normalized] ?? null;
                return $item;
            });
        // STYLES
        $styles = Style::select('id', 'name')
            ->get()
            ->map(function ($style) {
                $count = ProductAttribute::where('type', 'style')
                    ->where('value', $style->name)
                    ->whereHas('product', function ($q) {
                        $q->where('status', 1);
                    })
                    ->distinct('product_id')
                    ->count('product_id');

                return [
                    'name'  => $style->name,
                    'count' => $count,
                ];
            });

        // BODY FITS
        $bodyFits = BodyFit::select('id', 'name')
            ->get()
            ->map(function ($fit) {
                $count = ProductAttribute::where('type', 'body_fit')
                    ->where('value', $fit->name)
                    ->whereHas('product', function ($q) {
                        $q->where('status', 1);
                    })
                    ->distinct('product_id')
                    ->count('product_id');

                return [
                    'name'  => $fit->name,
                    'count' => $count,
                ];
            });


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
            'variations',
            'defaultVariationOptions',
            'appCategory:id,slug',
            'productSizes.size',
            'attributes',
        ]);

        // Category filter including all descendants
        if ($request->filled('category_id')) {
            $category = AppCategory::with('children.children.children')->find($request->category_id);

            if ($category) {
                $categoryIds = collect([$category->id]);

                // Recursive ID collector
                $collect = function ($children) use (&$collect, &$categoryIds) {
                    foreach ($children as $child) {
                        $categoryIds->push($child->id);
                        if ($child->children->count()) {
                            $collect($child->children);
                        }
                    }
                };

                $collect($category->children);

                $query->whereIn('app_category_id', $categoryIds->unique());
            }
        }


        // Brand filter
        if ($request->filled('brand_ids')) {

            $brandIds = (array) $request->brand_ids;

            // If frontend sends only 0 → show ALL brands (skip filter)
            if (count($brandIds) == 1 && $brandIds[0] == 0) {
                // do nothing → all brands included
            } else {
                // Remove 0 if mixed values come like [0, 3]
                $brandIds = array_filter($brandIds, function ($id) {
                    return $id != 0;
                });

                if (!empty($brandIds)) {
                    $query->whereIn('brand_id', $brandIds);
                }
            }
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
