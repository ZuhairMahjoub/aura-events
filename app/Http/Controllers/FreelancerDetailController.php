<?php

namespace App\Http\Controllers;

use App\Models\FreelancerDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreelancerDetailController extends Controller
{
    /**
     * GET /freelancer/details
     * عرض البيانات التفصيلية للفريلانسر الحالي (رقم الهوية، سنوات الخبرة).
     */
    public function show(Request $request): JsonResponse
    {
        $freelancer = $request->user()->providerProfile;

        if ($freelancer->provider_type !== 'freelancer') {
            return response()->json(['success' => false, 'message' => 'هذا الحساب ليس فريلانسر.'], 403);
        }

        $details = $freelancer->freelancerDetails;

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    /**
     * PUT /freelancer/details
     * تحديث/إنشاء البيانات التفصيلية للفريلانسر الحالي.
     * نستخدم updateOrCreate لأن الفريلانسر قد لا يملك سجل FreelancerDetail
     * بعد إذا لم يكمل هذه الخطوة وقت التسجيل.
     */
    public function update(Request $request): JsonResponse
    {
        $freelancer = $request->user()->providerProfile;

        if ($freelancer->provider_type !== 'freelancer') {
            return response()->json(['success' => false, 'message' => 'هذا الحساب ليس فريلانسر.'], 403);
        }

        $validated = $request->validate([
            'national_id' => ['required', 'string', 'max:50'],
            'experience_years' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        $details = FreelancerDetail::updateOrCreate(
            ['provider_id' => $freelancer->id],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بياناتك بنجاح.',
            'data' => $details,
        ]);
    }
}