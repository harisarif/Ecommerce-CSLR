<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppCategory;

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



}
