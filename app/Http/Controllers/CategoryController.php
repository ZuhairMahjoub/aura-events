<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
   
    public function index(): JsonResponse
    {
        $categories = Category::select('id', 'name_ar', 'name_en')->get();

        return response()->json([
            'status'  => true,
            'message' => 'Categories retrieved successfully.',
            'data'    => $categories
        ], 200);
    }
}