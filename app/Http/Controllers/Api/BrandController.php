<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    public function index()
    {
        // Fetch only "name" column
        $brands = Brand::pluck('name','id');

        return response()->json([
            'status' => true,
            'brands' => $brands,
        ]);
    }
}
