<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing records

        // --- Parents ---
        $menId = DB::table('app_categories')->insertGetId([
            'slug' => 'men',
            'parent_id' => 0,
            'level' => 1,
            'title_meta_tag' => 'Men',
            'description' => 'All products for men',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $womenId = DB::table('app_categories')->insertGetId([
            'slug' => 'women',
            'parent_id' => 0,
            'level' => 1,
            'title_meta_tag' => 'Women',
            'description' => 'All products for women',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kidsId = DB::table('app_categories')->insertGetId([
            'slug' => 'kids',
            'parent_id' => 0,
            'level' => 1,
            'title_meta_tag' => 'Kids',
            'description' => 'All products for kids',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | MEN CATEGORIES
        |--------------------------------------------------------------------------
        */
        $menTopsId = DB::table('app_categories')->insertGetId([
            'slug' => 'men-tops',
            'parent_id' => $menId,
            'level' => 2,
            'title_meta_tag' => 'Men Tops',
            'description' => 'Tops for men',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menBottomsId = DB::table('app_categories')->insertGetId([
            'slug' => 'men-bottoms',
            'parent_id' => $menId,
            'level' => 2,
            'title_meta_tag' => 'Men Bottoms',
            'description' => 'Bottoms for men',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menShoesId = DB::table('app_categories')->insertGetId([
            'slug' => 'men-shoes',
            'parent_id' => $menId,
            'level' => 2,
            'title_meta_tag' => 'Men Shoes',
            'description' => 'Shoes for men',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Men sub-children
        DB::table('app_categories')->insert([
            [
                'slug' => 'men-tshirts',
                'parent_id' => $menTopsId,
                'level' => 3,
                'title_meta_tag' => 'Men T-Shirts',
                'description' => 'T-shirts for men',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'men-shirts',
                'parent_id' => $menTopsId,
                'level' => 3,
                'title_meta_tag' => 'Men Shirts',
                'description' => 'Shirts for men',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'men-jeans',
                'parent_id' => $menBottomsId,
                'level' => 3,
                'title_meta_tag' => 'Men Jeans',
                'description' => 'Jeans for men',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'men-shorts',
                'parent_id' => $menBottomsId,
                'level' => 3,
                'title_meta_tag' => 'Men Shorts',
                'description' => 'Shorts for men',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'men-sneakers',
                'parent_id' => $menShoesId,
                'level' => 3,
                'title_meta_tag' => 'Men Sneakers',
                'description' => 'Sneakers for men',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'men-boots',
                'parent_id' => $menShoesId,
                'level' => 3,
                'title_meta_tag' => 'Men Boots',
                'description' => 'Boots for men',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | WOMEN CATEGORIES
        |--------------------------------------------------------------------------
        */
        $womenTopsId = DB::table('app_categories')->insertGetId([
            'slug' => 'women-tops',
            'parent_id' => $womenId,
            'level' => 2,
            'title_meta_tag' => 'Women Tops',
            'description' => 'Tops for women',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $womenBottomsId = DB::table('app_categories')->insertGetId([
            'slug' => 'women-bottoms',
            'parent_id' => $womenId,
            'level' => 2,
            'title_meta_tag' => 'Women Bottoms',
            'description' => 'Bottoms for women',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $womenShoesId = DB::table('app_categories')->insertGetId([
            'slug' => 'women-shoes',
            'parent_id' => $womenId,
            'level' => 2,
            'title_meta_tag' => 'Women Shoes',
            'description' => 'Shoes for women',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Women sub-children
        DB::table('app_categories')->insert([
            [
                'slug' => 'women-tshirts',
                'parent_id' => $womenTopsId,
                'level' => 3,
                'title_meta_tag' => 'Women T-Shirts',
                'description' => 'T-shirts for women',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'women-shirts',
                'parent_id' => $womenTopsId,
                'level' => 3,
                'title_meta_tag' => 'Women Shirts',
                'description' => 'Shirts for women',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'women-jeans',
                'parent_id' => $womenBottomsId,
                'level' => 3,
                'title_meta_tag' => 'Women Jeans',
                'description' => 'Jeans for women',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'women-skirts',
                'parent_id' => $womenBottomsId,
                'level' => 3,
                'title_meta_tag' => 'Women Skirts',
                'description' => 'Skirts for women',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'women-heels',
                'parent_id' => $womenShoesId,
                'level' => 3,
                'title_meta_tag' => 'Women Heels',
                'description' => 'Heels for women',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'women-sneakers',
                'parent_id' => $womenShoesId,
                'level' => 3,
                'title_meta_tag' => 'Women Sneakers',
                'description' => 'Sneakers for women',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | KIDS CATEGORIES
        |--------------------------------------------------------------------------
        */
        $kidsTopsId = DB::table('app_categories')->insertGetId([
            'slug' => 'kids-tops',
            'parent_id' => $kidsId,
            'level' => 2,
            'title_meta_tag' => 'Kids Tops',
            'description' => 'Tops for kids',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kidsBottomsId = DB::table('app_categories')->insertGetId([
            'slug' => 'kids-bottoms',
            'parent_id' => $kidsId,
            'level' => 2,
            'title_meta_tag' => 'Kids Bottoms',
            'description' => 'Bottoms for kids',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kidsShoesId = DB::table('app_categories')->insertGetId([
            'slug' => 'kids-shoes',
            'parent_id' => $kidsId,
            'level' => 2,
            'title_meta_tag' => 'Kids Shoes',
            'description' => 'Shoes for kids',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kids sub-children
        DB::table('app_categories')->insert([
            [
                'slug' => 'kids-tshirts',
                'parent_id' => $kidsTopsId,
                'level' => 3,
                'title_meta_tag' => 'Kids T-Shirts',
                'description' => 'T-shirts for kids',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'kids-shirts',
                'parent_id' => $kidsTopsId,
                'level' => 3,
                'title_meta_tag' => 'Kids Shirts',
                'description' => 'Shirts for kids',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'kids-jeans',
                'parent_id' => $kidsBottomsId,
                'level' => 3,
                'title_meta_tag' => 'Kids Jeans',
                'description' => 'Jeans for kids',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'kids-shorts',
                'parent_id' => $kidsBottomsId,
                'level' => 3,
                'title_meta_tag' => 'Kids Shorts',
                'description' => 'Shorts for kids',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'kids-sneakers',
                'parent_id' => $kidsShoesId,
                'level' => 3,
                'title_meta_tag' => 'Kids Sneakers',
                'description' => 'Sneakers for kids',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'kids-boots',
                'parent_id' => $kidsShoesId,
                'level' => 3,
                'title_meta_tag' => 'Kids Boots',
                'description' => 'Boots for kids',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);


         $now = Carbon::now();

        // --- Insert Products without hardcoded IDs ---
        $products = [
            [
                'app_category_id' => 7,
                'slug' => 'men-casual-tshirt',
                'product_type' => 'physical',
                'listing_type' => 'sell_on_site',
                'sku' => 'SKU-MEN-TSHIRT-001',
                'price' => 1500,
                'price_discounted' => 1200,
                'currency' => 'PKR',
                'user_id' => 1,
                'brand_id' => 12,
                'status' => 1,
                'created_at' => $now,
            ],
            [
                'app_category_id' => 7,
                'slug' => 'men-sport-tshirt',
                'product_type' => 'physical',
                'listing_type' => 'sell_on_site',
                'sku' => 'SKU-MEN-TSHIRT-002',
                'price' => 1800,
                'price_discounted' => 1500,
                'currency' => 'PKR',
                'user_id' => 1,
                'brand_id' => 12,
                'status' => 1,
                'created_at' => $now,
            ],
            [
                'app_category_id' => 9,
                'slug' => 'men-slim-fit-jeans',
                'product_type' => 'physical',
                'listing_type' => 'sell_on_site',
                'sku' => 'SKU-MEN-JEANS-001',
                'price' => 2500,
                'currency' => 'PKR',
                'user_id' => 1,
                'brand_id' => 12,
                'status' => 1,
                'created_at' => $now,
            ],
            [
                'app_category_id' => 9,
                'slug' => 'men-regular-jeans',
                'product_type' => 'physical',
                'listing_type' => 'sell_on_site',
                'sku' => 'SKU-MEN-JEANS-002',
                'price' => 2200,
                'currency' => 'PKR',
                'user_id' => 1,
                'brand_id' => 12,
                'status' => 1,
                'created_at' => $now,
            ],
            [
                'app_category_id' => 17,
                'slug' => 'women-cotton-shirt',
                'product_type' => 'physical',
                'listing_type' => 'sell_on_site',
                'sku' => 'SKU-WOMEN-SHIRT-001',
                'price' => 2000,
                'currency' => 'PKR',
                'user_id' => 1,
                'brand_id' => 12,
                'status' => 1,
                'created_at' => $now,
            ],
            [
                'app_category_id' => 17,
                'slug' => 'women-silk-shirt',
                'product_type' => 'physical',
                'listing_type' => 'sell_on_site',
                'sku' => 'SKU-WOMEN-SHIRT-002',
                'price' => 3000,
                'currency' => 'PKR',
                'user_id' => 1,
                'brand_id' => 12,
                'status' => 1,
                'created_at' => $now,
            ],
            [
                'app_category_id' => 29,
                'slug' => 'kids-sport-sneakers',
                'product_type' => 'physical',
                'listing_type' => 'sell_on_site',
                'sku' => 'SKU-KIDS-SNEAKERS-001',
                'price' => 1800,
                'currency' => 'PKR',
                'user_id' => 1,
                'brand_id' => 1,
                'status' => 1,
                'created_at' => $now,
            ],
            [
                'app_category_id' => 29,
                'slug' => 'kids-casual-sneakers',
                'product_type' => 'physical',
                'listing_type' => 'sell_on_site',
                'sku' => 'SKU-KIDS-SNEAKERS-002',
                'price' => 1600,
                'currency' => 'PKR',
                'user_id' => 1,
                'brand_id' => 1,
                'status' => 1,
                'created_at' => $now,
            ],
        ];

        // Insert products and capture their IDs
        foreach ($products as $product) {
            $productId = DB::table('products')->insertGetId($product);

            // Insert related sizes dynamically
            $sizes = match ($product['slug']) {
                'men-casual-tshirt' => [
                    ['size_id' => 3, 'stock' => 50],
                    ['size_id' => 4, 'stock' => 50],
                    ['size_id' => 5, 'stock' => 40],
                    ['size_id' => 6, 'stock' => 30],
                    ['size_id' => 7, 'stock' => 20],
                ],
                'men-sport-tshirt' => [
                    ['size_id' => 3, 'stock' => 40],
                    ['size_id' => 4, 'stock' => 45],
                    ['size_id' => 5, 'stock' => 35],
                    ['size_id' => 6, 'stock' => 25],
                    ['size_id' => 7, 'stock' => 15],
                ],
                'men-slim-fit-jeans' => [
                    ['size_id' => 35, 'stock' => 30],
                    ['size_id' => 36, 'stock' => 25],
                    ['size_id' => 37, 'stock' => 20],
                    ['size_id' => 38, 'stock' => 15],
                ],
                'men-regular-jeans' => [
                    ['size_id' => 35, 'stock' => 35],
                    ['size_id' => 36, 'stock' => 28],
                    ['size_id' => 37, 'stock' => 22],
                    ['size_id' => 38, 'stock' => 18],
                ],
                'women-cotton-shirt' => [
                    ['size_id' => 4, 'stock' => 25],
                    ['size_id' => 5, 'stock' => 20],
                    ['size_id' => 6, 'stock' => 15],
                    ['size_id' => 7, 'stock' => 10],
                ],
                'women-silk-shirt' => [
                    ['size_id' => 4, 'stock' => 20],
                    ['size_id' => 5, 'stock' => 18],
                    ['size_id' => 6, 'stock' => 12],
                    ['size_id' => 7, 'stock' => 8],
                ],
                'kids-sport-sneakers' => [
                    ['size_id' => 11, 'stock' => 20],
                    ['size_id' => 12, 'stock' => 18],
                    ['size_id' => 13, 'stock' => 15],
                    ['size_id' => 14, 'stock' => 12],
                ],
                'kids-casual-sneakers' => [
                    ['size_id' => 11, 'stock' => 22],
                    ['size_id' => 12, 'stock' => 19],
                    ['size_id' => 13, 'stock' => 14],
                    ['size_id' => 14, 'stock' => 10],
                ],
                default => [],
            };

            foreach ($sizes as $size) {
                DB::table('product_sizes')->insert([
                    'product_id' => $productId,
                    'size_id'    => $size['size_id'],
                    'stock'      => $size['stock'],
                    'created_at' => $now,
                ]);
            }
        }
    }
}
