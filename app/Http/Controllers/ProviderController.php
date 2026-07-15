<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;


class ProviderController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // حماية إضافية في حال استدعاء المسار بدون توكن
            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'يجب تسجيل الدخول أولاً.'
                ], 401);
            }

            // التحقق من صلاحية دور المزود
            if (!$user->hasRole('provider')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'غير مصرح. هذه الخدمة للمزودين فقط.'
                ], 403);
            }

            // جلب السجل عبر العلاقة الجديدة والمضمونة
            $provider = $user->providerProfile;

            if (!$provider) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'لم يتم إكمال بيانات البروفايل بعد.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data'   => [
                    // --- بيانات المزود (Providers Table) ---
                    'provider_id'       => $provider->id,
                    'brand_name'        => $provider->brand_name,
                    'provider_type'     => $provider->provider_type,
                    'qr_code_url'       => $provider->qr_code_path ? asset('storage/' . $provider->qr_code_path) : null,
                    'moderation_status' => $provider->moderation_status,
                    'is_verified'       => (bool) $provider->is_verified,
                    'created_at'        => $provider->created_at,

                    // --- بيانات الحساب الأساسي (Users Table) ---
                    'user' => [
                        'id'                 => $user->id,
                        'first_name'         => $user->first_name,
                        'last_name'          => $user->last_name,
                        'full_name'          => $user->first_name . ' ' . $user->last_name,
                        'email'              => $user->email,
                        'phone'              => $user->phone,
                        'city_id'            => $user->city_id,
                        'account_status'     => $user->status, // حالة حساب المستخدم
                        'is_email_verified'  => !is_null($user->email_verified_at),
                        'is_phone_verified'  => $user->hasVerifiedPhone(), // معتمدة على دالتك في الموديل
                        'settings_language'  => $user->settings_language,
                        'settings_theme'     => $user->settings_theme,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Get Provider Profile Error: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب البيانات، يرجى المحاولة لاحقاً.'
            ], 500);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
   
    // جلب جميع المزودين مع بيانات المستخدم المرتبطة بهم
    $providers = Provider::with('user')->get();
    
    return response()->json([
        'success' => true,
        'data' => $providers
    ], 200);
}
public function getProviders()
{
    $providers = Provider::paginate(15);

    $providers->getCollection()->transform(function ($provider) {
        return [
            'id'    => (string) $provider->id,
            'name'  => $provider->brand_name,
            'type'  => $provider->provider_type,
        ];
    });

    return response()->json($providers, 200);
}
public function uploadQrCode(Request $request)
{
    // 1. التحقق من المدخلات (صورة فقط وبحجم مناسب)
    $request->validate([
        'qr_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    $user = $request->user();
    $provider = $user->providerProfile;

    if (!$provider) {
        return response()->json(['message' => 'بيانات المزود غير موجودة.'], 404);
    }

    // 2. حذف الصورة القديمة إذا كانت موجودة لتوفير المساحة
    if ($provider->qr_code_path) {
        Storage::disk('public')->delete($provider->qr_code_path);
    }

    // 3. تخزين الصورة الجديدة
    $path = $request->file('qr_image')->store('providers/qr_codes', 'public');

    // 4. تحديث المسار في قاعدة البيانات
    $provider->update(['qr_code_path' => $path]);

    return response()->json([
        'message' => 'تم رفع الـ QR بنجاح.',
        'qr_url' => asset('storage/' . $path) // هذا الرابط الذي سيستخدمه الفرونت إند لعرض الصورة
    ]);
}
}