<?php

namespace App\Http\Controllers;

use App\Models\District;
use Illuminate\Http\JsonResponse;

class DistrictsController extends Controller
{
    // في DistrictsController.php
public function index(): JsonResponse
{
    $districts = District::all();

    // هنا نحن نطلب 'name' فقط، والمكتبة ستقوم بجلب القيمة 
    // بناءً على اللغة التي حددها الـ middleware تلقائياً
    $data = $districts->map(function ($district) {
        return [
            'id'   => $district->id,
            'name' => $district->name, // ستتغير تلقائياً بناءً على الـ Accept-Language
        ];
    });

    return response()->json([
        'status'  => true,
        'message' => 'Districts retrieved successfully.',
        'data'    => $data
    ], 200);
}
}