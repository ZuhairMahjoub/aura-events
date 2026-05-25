<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteProfileRequest;
use App\Services\ProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProviderAuthController extends Controller
{
    protected $providerService;

    public function __construct(ProviderService $providerService)
    {
        $this->providerService = $providerService;
    }

   public function store(CompleteProfileRequest $request): JsonResponse
{
    try {
        // من التوكن مو من الـ Body
        $user = $request->user();

        // تحقق إن عنده دور provider فقط
        if (!$user->hasRole('provider')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'غير مصرح. هذه الخدمة للمزودين فقط.'
            ], 403);
        }

        // تحقق إن ما كمّل البروفايل مسبقاً
        if ($user->is_profile_completed) {
            return response()->json([
                'status'  => 'error',
                'message' => 'تم إكمال البروفايل مسبقاً.'
            ], 409);
        }

        $provider = $this->providerService->completeProfile(
            $user,
            $request->validated()
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'تم حفظ بياناتك بنجاح. حسابك الآن بانتظار مراجعة الإدارة.',
            'data'    => [
                'provider_id' => $provider->id,
                'is_verified' => false
            ]
        ], 201);

    } catch (\Exception $e) {
        Log::error("Provider Profile Error: " . $e->getMessage());
        return response()->json([
            'status'  => 'error',
            'message' => 'حدث خطأ أثناء معالجة البيانات، يرجى المحاولة لاحقاً.'
        ], 500);
    }
}
}