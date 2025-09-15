<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\Request;

class SizeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sizes = Size::all();
        return response()->json($sizes);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'type' => 'nullable|string|max:50',
        ]);

        $size = Size::create($request->only('name', 'type'));

        return response()->json([
            'success' => true,
            'data' => $size,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Size $size)
    {
        return response()->json($size);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Size $size)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'type' => 'nullable|string|max:50',
        ]);

        $size->update($request->only('name', 'type'));

        return response()->json([
            'success' => true,
            'data' => $size,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Size $size)
    {
        $size->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
