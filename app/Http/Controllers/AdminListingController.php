<?php

namespace App\Http\Controllers; // 👈 تعديل السطر هاد ليصير المجلد الرئيسي

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminListingController extends Controller
{
    public function approve($id): JsonResponse
    {
        $listing = Listing::findOrFail($id);
        
        $listing->update([
            'moderation_status' => 'approved',
            'rejection_reason'  => null
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تم قبول الإعلان بنجاح، وهو الآن نشط على المنصة.',
            'data'    => $listing
        ], 200);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000']
        
        ]);

        $listing = Listing::findOrFail($id);
        
        $listing->update([
            'moderation_status' => 'rejected',
            'rejection_reason'  => $request->rejection_reason
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تم رفض الإعلان بنجاح، وتم تسجيل سبب الرفض.',
            'data'    => $listing
        ], 200);
    }
}