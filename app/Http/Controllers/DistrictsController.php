<?php

namespace App\Http\Controllers;

use App\Models\District;
use Illuminate\Http\JsonResponse;

class DistrictsController extends Controller
{
    
    public function index(): JsonResponse
    {
        $districts = District::select('id', 'name_ar', 'name_en')->get();

        return response()->json([
            'status'  => true,
            'message' => 'Districts retrieved successfully.',
            'data'    => $districts
        ], 200);
    }
}