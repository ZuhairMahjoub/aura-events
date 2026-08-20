<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderPolicyController extends Controller
{
    /**
     * GET /provider/policy
     * يعرض نص سياسة الاستخدام. متاح لأي مزوّد approved/active بغض النظر
     * عن حالة الموافقة (حتى يقدر يقرأها قبل ما يوافق).
     */
    public function show(Request $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        return response()->json([
            'success' => true,
            'data' => [
                // النص الفعلي يُفضَّل تخزينه بملف config أو جدول settings بدل
                // hardcoding هون، خصوصاً لو محتاج تعدد لغات (ar/en) لاحقاً.
                'policy_text'        => config('provider.policy_text', 'نص سياسة الاستخدام...'),
                'policy_version'     => config('provider.policy_version', '1.0'),
                'already_accepted'   => $provider->hasAcceptedPolicy(),
                'accepted_at'        => $provider->policy_accepted_at,
            ],
        ]);
    }

    /**
     * POST /provider/policy/accept
     * يسجّل وقت موافقة المزوّد على السياسة. عملية idempotent: لو وافق
     * مسبقاً، ما منعيد الكتابة فوق التاريخ الأصلي.
     */
    public function accept(Request $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        if ($provider->hasAcceptedPolicy()) {
            return response()->json([
                'success' => true,
                'message' => 'تمت الموافقة على السياسة مسبقاً.',
                'data' => ['accepted_at' => $provider->policy_accepted_at],
            ]);
        }

        $provider->update(['policy_accepted_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'تمت الموافقة على السياسة بنجاح، لوحة التحكم متاحة الآن.',
            'data' => ['accepted_at' => $provider->policy_accepted_at],
        ]);
    }
}