<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Districts;
use Illuminate\Http\JsonResponse;

class DistrictController extends Controller
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