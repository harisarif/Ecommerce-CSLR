<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\UserBrand;
use App\Models\UserSize;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function ProductList(Request $request)
    {
        $productId = $request->query('product_id');

        if ($productId) {
            $product = Product::with([
                'details', 'licenseKeys', 'searchIndexes','appCategory',
                'user', 'images', 'variations', 'defaultVariationOptions', 'mainImage','gallery'
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
            'user', 'images', 'variations', 'defaultVariationOptions', 'mainImage','gallery'
        ])->paginate(10);

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }


    public function getSpecialOfferProducts()
    {
        $product = Product::with(['details', 'licenseKeys', 'searchIndexes','appCategory','user', 'images', 'variations', 'defaultVariationOptions', 'mainImage','gallery'])
        ->specialOffers()
        ->orderBy('special_offer_date', 'DESC')
        ->paginate(10);
        return response()->json($product);
    }

    public function getPromotedProducts()
    {
        $product = Product::with(['details', 'licenseKeys', 'searchIndexes', 'appCategory', 'user', 'images', 'variations', 'defaultVariationOptions', 'mainImage','gallery'])
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
            'user',  'variations',
            'defaultVariationOptions', 'sizes','images' ,'mainImage','gallery'
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
                'user',  'variations', 'defaultVariationOptions','images', 'mainImage','gallery'
            ])
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }


    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'slug' => 'required|string|max:255|unique:products,slug',
    //         'product_type' => 'required|string',
    //         'listing_type' => 'required|string',
    //         'sku' => 'required|string|max:100|unique:products,sku',
    //         'price' => 'required|integer',
    //         'price_discounted' => 'nullable|integer',
    //         'currency' => 'required|string|max:10',
    //         'discount_rate' => 'nullable|integer',
    //         'vat_rate' => 'nullable|numeric',
    //         'user_id' => 'required|exists:users,id',
    //         'status' => 'boolean',
    //         'stock' => 'integer',
    //         'brand_id' => 'nullable|exists:brands,id',
    //         'app_category_id' => 'nullable|exists:app_categories,id',

    //         // 👇 extra validation for sizes
    //         'size_ids' => 'array',
    //         'size_ids.*' => 'exists:sizes,id',

    //         'attributes' => 'array',
    //         'attributes.*.type' => 'required|string|max:50',
    //         'attributes.*.value' => 'required|string|max:100',
    //     ]);

    //     // create product
    //     $product = Product::create($validated);

    //     // attach sizes if provided
    //     if ($request->has('size_ids')) {
    //         $product->sizes()->attach($validated['size_ids']);
    //     }

    //     if ($request->has('attributes')) {
    //         foreach ($validated['attributes'] as $attr) {
    //             $product->attributes()->create($attr);
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => $product->load('sizes') // return with sizes
    //     ]);
    // }



    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'product_type' => 'required|string',
            'listing_type' => 'required|string',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'price' => 'required|integer',
            'price_discounted' => 'nullable|integer',
            'currency' => 'required|string|max:10',
            'discount_rate' => 'nullable|integer',
            'vat_rate' => 'nullable|numeric',
            'status' => 'boolean',
            'stock' => 'integer',
            'brand_id' => 'nullable|exists:brands,id',
            'app_category_id' => 'nullable|exists:app_categories,id',

            // 🧩 Sizes
            'size_ids' => 'array',
            'size_ids.*' => 'exists:sizes,id',

            // 🧩 Attributes
            'attributes' => 'array',
            'attributes.*.type' => 'required|string|max:50',
            'attributes.*.value' => 'required|string|max:100',

            // 📸 Multiple images
            'images' => 'array',
            'images.*' => 'file|image|mimes:jpeg,png,jpg,webp|max:2048',

            // 🧩 Optional: measurements
            'measurement_width' => 'nullable|numeric',
            'measurement_length' => 'nullable|numeric',

            // 🧩 Optional: condition
            'condition' => 'nullable|string|in:new_with_tags,new_without_tags,like_new,excellent_condition,good_condition,fair_condition,vintage_pre_loved,for_parts_repair',
        ]);

        try {
            DB::beginTransaction();

            // auto generate slug if not provided
            $slug = $validated['slug'] ?? Str::slug($validated['title']);

            // auto SKU if missing
            $sku = $validated['sku'] ?? strtoupper(Str::random(10));
            $user = auth()->user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if (!$user->shop) {
                return response()->json(['message' => 'Shop not found for this user.'], 404);
            }

            // 💾 Create product
            $product = Product::create([
                'slug' => $slug,
                'product_type' => $validated['product_type'],
                'listing_type' => $validated['listing_type'],
                'sku' => $sku,
                'price' => $validated['price'],
                'price_discounted' => $validated['price_discounted'] ?? null,
                'currency' => $validated['currency'],
                'discount_rate' => $validated['discount_rate'] ?? null,
                'vat_rate' => $validated['vat_rate'] ?? null,
                'user_id' => $user->id,
                'shop_id' => $user->shop->id,
                'status' => $validated['status'] ?? true,
                'stock' => $validated['stock'] ?? 0,
                'brand_id' => $validated['brand_id'] ?? null,
                'app_category_id' => $validated['app_category_id'] ?? null,
            ]);

            // 📝 Create product details (multi-language ready)
            $product->details()->create([
                'lang_id' => 1, 
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
            ]);

            // 🧩 Attach sizes
            if (!empty($validated['size_ids'])) {
                foreach ($validated['size_ids'] as $sizeId) {
                    \App\Models\ProductSize::create([
                        'product_id' => $product->id,
                        'size_id' => $sizeId,
                        'stock' => $validated['stock'] ?? 0,
                    ]);
                }
            }

            // 🧩 Handle attributes (color, material, etc.)
            if (!empty($validated['attributes'])) {
                foreach ($validated['attributes'] as $attr) {
                    \App\Models\ProductAttribute::create([
                        'product_id' => $product->id,
                        'type' => $attr['type'],
                        'value' => $attr['value'],
                    ]);
                }
            }

            // 🧩 Add measurements if provided
            if (!empty($validated['measurement_width'])) {
                \App\Models\ProductAttribute::create([
                    'product_id' => $product->id,
                    'type' => 'measurement_width',
                    'value' => $validated['measurement_width'] . ' in',
                ]);
            }
            if (!empty($validated['measurement_length'])) {
                \App\Models\ProductAttribute::create([
                    'product_id' => $product->id,
                    'type' => 'measurement_length',
                    'value' => $validated['measurement_length'] . ' in',
                ]);
            }

            // 🧩 Add product condition if provided
            if (!empty($validated['condition'])) {
                \App\Models\ProductAttribute::create([
                    'product_id' => $product->id,
                    'type' => 'condition',
                    'value' => $validated['condition'],
                ]);
            }

            if ($request->hasFile('images')) {
                $images = $request->file('images');
                $folderPath = 'uploads/' . date('Ym');
                $destinationPath = public_path($folderPath);

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $imagePaths = [];

                foreach ($images as $index => $image) {
                    $filename = uniqid() . '.' . $image->getClientOriginalExtension();
                    $image->move($destinationPath, $filename);
                    $relativePath = date('Ym') . '/' . $filename;

                    if ($index === 0) {
                    // 🖼 Save the first image as the main product image
                        \App\Models\Image::create([
                            'product_id' => $product->id,
                            'image_default' => $relativePath,
                            'is_main' => true,
                            'storage' => 'local',
                        ]);
                    } else {
                        $imagePaths[] = $relativePath;
                    }
                }

                if (!empty($imagePaths)) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_paths' => $imagePaths,
                        'count' => count($imagePaths),
                    ]);
                }
            }


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load(['sizes.size', 'attributes', 'mainImage' ,'gallery'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Product creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    // public function getUserProducts(Request $request)
    // {
    //     $user = $request->user();

    //     // Fetch user-selected sizes and brands
    //     $userSizes = UserSize::where('user_id', $user->id)
    //         ->get(['app_category_id', 'size_id']); // updated to app_category_id

    //     $userBrands = UserBrand::where('user_id', $user->id)
    //         ->pluck('brand_id')
    //         ->toArray();

    //     $productsQuery = Product::query()
    //         ->with([
    //             'brand:id,name',
    //             'appCategory:id,slug',   // updated: relation to app_categories
    //             'productSizes.size', 'images', 'variations', 'defaultVariationOptions', 'mainImage'
    //         ]);

    //     // Filter by brands if any
    //     if (!empty($userBrands)) {
    //         $productsQuery->whereIn('brand_id', $userBrands);
    //     }

    //     // Filter by user-selected sizes + app categories
    //     if ($userSizes->isNotEmpty()) {
    //         $productsQuery->where(function ($query) use ($userSizes) {
    //             foreach ($userSizes as $us) {
    //                 $query->orWhere(function ($q) use ($us) {
    //                     $q->where('app_category_id', $us->app_category_id) // updated
    //                     ->whereHas('productSizes', function ($q2) use ($us) {
    //                         $q2->where('size_id', $us->size_id);
    //                     });
    //                 });
    //             }
    //         });
    //     }

    //     $products = $productsQuery->get();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $products,
    //     ]);
    // }

    public function getUserProducts(Request $request)
    {
        $user = $request->user();

        // User preferences
        $userSizes = UserSize::where('user_id', $user->id)
            ->get(['app_category_id', 'size_id']);
        $userBrands = UserBrand::where('user_id', $user->id)
            ->pluck('brand_id')
            ->toArray();
        $favCategories = $userSizes->pluck('app_category_id')->filter()->unique()->toArray();

        $productsQuery = Product::query()
            ->with([
                'brand:id,name',
                'appCategory:id,slug',
                'productSizes.size',
                'images',
                'mainImage',
                'gallery'
            ])
            ->where('status', 1) // only active
            ->where('stock', '>', 0); // only in-stock

        // Group filters so user gets products by brand OR category/size
        $productsQuery->where(function ($query) use ($userBrands, $userSizes, $favCategories) {
            // Brand filter
            if (!empty($userBrands)) {
                $query->orWhereIn('brand_id', $userBrands);
            }

            // Category + Size filter
            if ($userSizes->isNotEmpty()) {
                foreach ($userSizes as $us) {
                    $query->orWhere(function ($q) use ($us) {
                        $q->where('app_category_id', $us->app_category_id)
                        ->whereHas('productSizes', function ($q2) use ($us) {
                            $q2->where('size_id', $us->size_id);
                        });
                    });
                }
            }

            // Fallback: only categories if no sizes
            if (empty($userSizes) && !empty($favCategories)) {
                $query->orWhereIn('app_category_id', $favCategories);
            }
        });

        $products = $productsQuery->get();

        return response()->json([
            'success' => true,
            'count'   => $products->count(),
            'data'    => $products,
        ]);
    }


    public function getProductWithShop($id)
    {
        $product = Product::with([
            'details',
            'appCategory',
            'user',
            'sizes',
            'shop',
            'images',
            'mainImage',
            'gallery'
        ])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.'
            ], 404);
        }

        // ✅ Fetch related products
        $relatedProducts = Product::with([
            'mainImage',
            'gallery',
            'shop'
        ])
        ->where('id', '!=', $product->id)
        ->where(function ($q) use ($product) {
            if ($product->category_id) {
                $q->where('category_id', $product->category_id);
            } elseif ($product->app_category_id) {
                $q->where('app_category_id', $product->app_category_id);
            }
        })
        ->limit(6)
        ->get();

        // ✅ Suggested prices (all lower than actual)
        $actualPrice = $product->price;

        $suggestedPrices = [
            [
                'price' => round($actualPrice * 0.95), // 5% off
                'recommended' => false
            ],
            [
                'price' => round($actualPrice * 0.9), // 10% off (recommended)
                'recommended' => true
            ],
            [
                'price' => round($actualPrice * 0.85), // 15% off
                'recommended' => false
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $product,
            'related_products' => $relatedProducts,
            'suggested_prices' => $suggestedPrices
        ]);
    }









}
