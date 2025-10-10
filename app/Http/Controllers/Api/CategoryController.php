<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppCategory;
use App\Models\Brand;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = AppCategory::paginate(10);
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:255|unique:app_categories,slug',
            'parent_id' => 'nullable|integer|min:0',
        ]);

        // If parent_id = 0 → make it null before saving
        if (isset($validated['parent_id']) && $validated['parent_id'] == 0) {
            $validated['parent_id'] = null;
        }

        $category = AppCategory::create($validated);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function tree()
    {
        $categories = AppCategory::with('children.children.children')
            ->where('parent_id', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function update(Request $request, AppCategory $category)
    {
        $validated = $request->validate([
            'slug' => 'sometimes|string|max:255|unique:app_categories,slug,' . $category->id,
            'parent_id' => 'nullable|integer|min:0',
        ]);
        // If parent_id = 0 → make it null before saving
        if (isset($validated['parent_id']) && $validated['parent_id'] == 0) {
            $validated['parent_id'] = null;
        }
        $category->update($validated);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function destroy(AppCategory $category)
    {
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    public function getCategoriesWithSizes(Request $request)
    {
        $type = strtolower($request->get('type', ''));

        // fetch all parents (tabs)
        $parents = AppCategory::where('parent_id', 0)->get(['id', 'slug']);

        if ($parents->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No parent categories found',
            ], 404);
        }

        // if type provided → filter only that parent, else take all parents
        $targetParents = $type
            ? $parents->where('slug', $type)
            : $parents;

        $data = $targetParents->map(function ($parent) {
            $category = AppCategory::with([
                'children.children.products.productSizes.size'
            ])->where('id', $parent->id)
                ->first(['id', 'slug', 'parent_id']);

            if ($category) {
                $category->children->transform(function ($child) {
                    $sizes = collect();

                    // sizes from products directly under child
                    $sizes = $sizes->merge(
                        $child->products->flatMap(fn($p) => $p->productSizes->pluck('size'))
                    );

                    // sizes from grandchild categories
                    foreach ($child->children as $grandchild) {
                        $sizes = $sizes->merge(
                            $grandchild->products->flatMap(fn($p) => $p->productSizes->pluck('size'))
                        );
                    }

                    $child->sizes = $sizes->unique('id')->values();

                    return $child->only(['id', 'slug', 'parent_id', 'sizes']);
                });

                $category->categories = $category->children;
                unset($category->children);
            }

            return $category;
        });

        return response()->json([
            'success' => true,
            'parents' => $parents,
            'data'    => $data->values(), // all or filtered
        ]);
    }



    public function getProductMeta(Request $request)
    {
        $parents = AppCategory::where('parent_id', 0)
            ->get(['id', 'slug', 'title_meta_tag', 'parent_id']);

        $categories = $parents->map(function ($parent) {
            $category = AppCategory::with('children.children')
                ->where('id', $parent->id)
                ->first(['id', 'slug', 'title_meta_tag', 'parent_id']);

            if ($category) {
                $attachSizes = function ($cat, $level = 1) use (&$attachSizes) {
                    $slug = strtolower($cat->slug);
                    $type = null;

                    // Detect type by slug
                    if (str_contains($slug, 'tshirt') || str_contains($slug, 'shirt') || str_contains($slug, 'top')) {
                        $type = 'clothing';
                    } elseif (str_contains($slug, 'jean') || str_contains($slug, 'pant') || str_contains($slug, 'short')) {
                        $type = 'pants';
                    } elseif (str_contains($slug, 'shoe') || str_contains($slug, 'boot') || str_contains($slug, 'sneaker')) {
                        $type = 'shoes';
                    } elseif (str_contains($slug, 'ring')) {
                        $type = 'rings';
                    } elseif (str_contains($slug, 'hat') || str_contains($slug, 'cap')) {
                        $type = 'hats';
                    } elseif (str_contains($slug, 'bag')) {
                        $type = 'bag';
                    }

                    // ✅ Only attach sizes for leaf categories
                    $sizes = collect();
                    if (!$cat->children || $cat->children->isEmpty()) {
                        if ($type) {
                            $sizes = \App\Models\Size::where('type', $type)
                                ->get(['id', 'name', 'type']);
                        }
                    }

                    // recursively attach to children
                    $children = [];
                    if ($cat->children && $cat->children->count()) {
                        $children = $cat->children->map(
                            fn($child) => $attachSizes($child, $level + 1)
                        )->filter()->values();
                    }

                    // ✅ Dynamic naming (first = children, second = sub_children)
                    $childKey = $level === 1 ? 'children' : 'sub_children';

                    $data = [
                        'id' => $cat->id,
                        'slug' => $cat->slug,
                        'title_meta_tag' => $cat->title_meta_tag,
                    ];

                    if ($sizes->isNotEmpty()) {
                        $data['sizes'] = $sizes;
                    }

                    if (!empty($children)) {
                        $data[$childKey] = $children;
                    }

                    return $data;
                };

                $category = $attachSizes($category);
            }

            return $category;
        });
        // ✅ Fetch brands, colors, etc. same as before
        $brands = Brand::select('id', 'name', 'image_path')->get();
        $colors = collect(config('colors'))->map(fn($hex, $name) => ['name' => $name, 'hex' => $hex])->values();

        $conditions = [
            ['key' => 'new_with_tags', 'label' => 'New with Tags', 'description' => 'Brand new, never worn, original tags still attached.'],
            ['key' => 'new_without_tags', 'label' => 'New without Tags', 'description' => 'Brand new and never worn, but no tags.'],
            ['key' => 'like_new', 'label' => 'Like New', 'description' => 'Worn once or twice, no signs of wear.'],
            ['key' => 'excellent', 'label' => 'Excellent Condition', 'description' => 'Very lightly worn, no flaws or damage.'],
            ['key' => 'good', 'label' => 'Good Condition', 'description' => 'Gently used, may show light wear (e.g., minor fading).'],
            ['key' => 'fair', 'label' => 'Fair Condition', 'description' => 'Clearly used, visible wear or small flaws, still wearable.'],
            ['key' => 'vintage', 'label' => 'Vintage / Pre-loved', 'description' => 'Older item with character, may show signs of age.'],
            ['key' => 'repair', 'label' => 'For Parts / Repair', 'description' => 'Damaged, stained, or needs fixing — sold as is.'],
        ];

        $materials = [
            ['key' => 'acrylic', 'label' => 'Acrylic'],
            ['key' => 'alpaca', 'label' => 'Alpaca'],
            ['key' => 'bamboo', 'label' => 'Bamboo'],
            ['key' => 'canvas', 'label' => 'Canvas'],
            ['key' => 'cardboard', 'label' => 'Cardboard'],
            ['key' => 'cashmere', 'label' => 'Cashmere'],
            ['key' => 'ceramic', 'label' => 'Ceramic'],
            ['key' => 'chiffon', 'label' => 'Chiffon'],
            ['key' => 'corduroy', 'label' => 'Corduroy'],
            ['key' => 'cotton', 'label' => 'Cotton'],
            ['key' => 'denim', 'label' => 'Denim'],
            ['key' => 'down', 'label' => 'Down'],
            ['key' => 'elastane', 'label' => 'Elastane'],
            ['key' => 'faux_fur', 'label' => 'Faux Fur'],
            ['key' => 'faux_leather', 'label' => 'Faux Leather'],
            ['key' => 'felt', 'label' => 'Felt'],
            ['key' => 'flannel', 'label' => 'Flannel'],
            ['key' => 'fleece', 'label' => 'Fleece'],
            ['key' => 'foam', 'label' => 'Foam'],
            ['key' => 'glass', 'label' => 'Glass'],
            ['key' => 'gold', 'label' => 'Gold'],
            ['key' => 'jute', 'label' => 'Jute'],
        ];

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'brands' => $brands,
            'conditions' => $conditions,
            'colors' => $colors,
            'materials' => $materials,
        ]);
    }
}
