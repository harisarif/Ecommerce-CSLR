<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\UserBrand;
use App\Models\UserSize;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
class ProductController extends Controller
{
    public function ProductList(Request $request)
    {
        $productId = $request->query('product_id');

        if ($productId) {
            $product = Product::with([
                'details', 'licenseKeys', 'searchIndexes','appCategory',
                'user', 'images', 'variations', 'defaultVariationOptions', 'mainImage'
            ])->find($productId);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'product' => $product
            ]);
        }

        // Otherwise return paginated list
        $products = Product::with([
            'details', 'licenseKeys', 'searchIndexes', 'appCategory',
            'user', 'images', 'variations', 'defaultVariationOptions', 'mainImage'
        ])->paginate(10);

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }


    public function getSpecialOfferProducts()
    {
        $product = Product::with(['details', 'licenseKeys', 'searchIndexes','appCategory','user', 'images', 'variations', 'defaultVariationOptions', 'mainImage'])
        ->specialOffers()
        ->orderBy('special_offer_date', 'DESC')
        ->paginate(10);
        return response()->json($product);
    }

    public function getPromotedProducts()
    {
        $product = Product::with(['details', 'licenseKeys', 'searchIndexes', 'appCategory', 'user', 'images', 'variations', 'defaultVariationOptions', 'mainImage'])
        ->promoted()
        ->paginate(10);
        return response()->json($product);
    }

    public function getProductsByCategory(Request $request, $category_id)
    {
        $isApp = $request->query('app', false); // /products/category/1?app=1

        $query = Product::with([
            'details', 'licenseKeys', 'searchIndexes',
            'appCategory',
            'user', 'images', 'variations',
            'defaultVariationOptions', 'mainImage', 'sizes'
        ]);

        if ($isApp) {
            $query->where('app_category_id', $category_id);
        } else {
            $query->where('category_id', $category_id);
        }

        $products = $query->paginate(10);

        return response()->json($products);
    }

    public function search(Request $request)
    {
        $keyword = $request->query('keyword');

        if (!$keyword) {
            return response()->json([
                'success' => false,
                'message' => 'Keyword is required'
            ], 400);
        }

        $products = Product::whereHas('details', function ($query) use ($keyword) {
                $query->where('title', 'LIKE', "%{$keyword}%");
            })
            ->with([
                'details', 'licenseKeys', 'searchIndexes', 'appCategory',
                'user', 'images', 'variations', 'defaultVariationOptions', 'mainImage'
            ])
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:255|unique:products,slug',
            'product_type' => 'required|string',
            'listing_type' => 'required|string',
            'sku' => 'required|string|max:100|unique:products,sku',
            'price' => 'required|integer',
            'price_discounted' => 'nullable|integer',
            'currency' => 'required|string|max:10',
            'discount_rate' => 'nullable|integer',
            'vat_rate' => 'nullable|numeric',
            'user_id' => 'required|exists:users,id',
            'status' => 'boolean',
            'stock' => 'integer',
            'brand_id' => 'nullable|exists:brands,id',
            'app_category_id' => 'nullable|exists:app_categories,id',

            // 👇 extra validation for sizes
            'size_ids' => 'array',
            'size_ids.*' => 'exists:sizes,id',

            'attributes' => 'array',
            'attributes.*.type' => 'required|string|max:50',
            'attributes.*.value' => 'required|string|max:100',
        ]);

        // create product
        $product = Product::create($validated);

        // attach sizes if provided
        if ($request->has('size_ids')) {
            $product->sizes()->attach($validated['size_ids']);
        }

        if ($request->has('attributes')) {
            foreach ($validated['attributes'] as $attr) {
                $product->attributes()->create($attr);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $product->load('sizes') // return with sizes
        ]);
    }



public function getUserProducts(Request $request)
{
    $user = $request->user();

    // User preferences
    $userSizes = UserSize::where('user_id', $user->id)
        ->get(['app_category_id', 'size_id']); // app_category + size
    $userBrands = UserBrand::where('user_id', $user->id)
        ->pluck('brand_id')
        ->toArray();

    $productsQuery = Product::query()
        ->with([
            'brand:id,name',
            'appCategory:id,slug',
            'productSizes.size',
            'images',
            'variations',
            'defaultVariationOptions',
            'mainImage'
        ]); // in-stock only

    // Filter by user's favorite brands
    if (!empty($userBrands)) {
        $productsQuery->whereIn('brand_id', $userBrands);
    }

    // Filter by user's favorite categories + sizes
    if ($userSizes->isNotEmpty()) {
        $productsQuery->where(function ($query) use ($userSizes) {
            foreach ($userSizes as $us) {
                $query->orWhere(function ($q) use ($us) {
                    $q->where('app_category_id', $us->app_category_id)
                      ->whereHas('productSizes', function ($q2) use ($us) {
                          $q2->where('size_id', $us->size_id);
                      });
                });
            }
        });
    }

    // If no sizes but user has category preferences, still match category
    if ($userSizes->isEmpty() && !empty($userBrands)) {
        // already handled by brand filter above
    } elseif ($userSizes->isEmpty()) {
        // fallback: if user has no size selection, get all products by category
        $favCategories = UserSize::where('user_id', $user->id)
            ->pluck('app_category_id')
            ->toArray();

        if (!empty($favCategories)) {
            $productsQuery->orWhereIn('app_category_id', $favCategories);
        }
    }

    $products = $productsQuery->get();

    return response()->json([
        'success' => true,
        'count'   => $products->count(),
        'data'    => $products,
    ]);
}







}
