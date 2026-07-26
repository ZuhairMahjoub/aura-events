<?php

namespace App\Http\Controllers;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ProviderResource;
use App\Http\Resources\UserResource;
use App\Services\ProviderService;
use App\Models\User;

class AdminProviderController extends Controller
{
    protected $providerService;

    public function __construct(ProviderService $providerService)
    {
        $this->providerService = $providerService;
    }

    public function approve($id)
    {
        $provider = Provider::findOrFail($id);
        if ($provider->moderation_status === 'approved') {
            return response()->json(['status' => 'error', 'message' => 'هذا المزود معتمد بالفعل.'], 422);
        }
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

    public function showProvider(string $id): JsonResponse
    {
        $provider = Provider::with([
            'user',
            'categories',
            'freelancerDetails',
            'companyDetails.district',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => new ProviderResource($provider),
        ]);
    }

    public function getUserDetails(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data'   => new UserResource($user)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }
    }
}
