<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;

class AdminProviderController extends Controller
{
    public function approve($id)
    {
        $provider = Provider::findOrFail($id);
        
        $provider->update([
            'moderation_status' => 'approved',
            'is_active' => true
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم قبول مزود الخدمة بنجاح، وتفعيل حسابه.',
            'data' => $provider
        ], 200);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        $provider = Provider::findOrFail($id);
        
        $provider->update([
            'moderation_status' => 'rejected',
            'is_active' => false,
            'rejection_reason' => $request->rejection_reason // تمت إضافة الفاصلة هنا
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم رفض طلب مزود الخدمة وإلغاء تنشيطه.',
            'data' => $provider
        ], 200);
    }
}