<?php

namespace App\Http\Controllers;

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

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'يجب تسجيل الدخول أولاً.'
            ], 401);
        }

        if (!$user->hasRole('provider')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'غير مصرح. هذه الخدمة للمزودين فقط.'
            ], 403);
        }

        $provider = $user->providerProfile()->with(['companyDetails', 'freelancerDetails', 'categories'])->first();

        if (!$provider) {
            return response()->json([
                'status'  => 'error',
                'message' => 'لم يتم إكمال بيانات البروفايل بعد.'
            ], 404);
        }

        // ⚠️ إصلاح: district_id وaddress_details مش أعمدة موجودة بجدول
        // providers أصلاً (Provider::$fillable فيها الاسمين غلط، بس قاعدة
        // البيانات ما فيها العمودين). المصدر الحقيقي لهم هو company_details
        // فقط (الفريلانسر ماله district/address إطلاقاً بالبنية الحالية).
        $providerDetails = $provider->provider_type === 'company'
            ? $provider->companyDetails
            : $provider->freelancerDetails;

        return response()->json([
            'status' => 'success',
            'data'   => [
                // ─── الجدول 1: المستخدم كاملاً (Users Table) ───────────
                'user' => [
                    'id'                => $user->id,
                    'first_name'        => $user->first_name,
                    'last_name'         => $user->last_name,
                    'full_name'         => $user->first_name . ' ' . $user->last_name,
                    'email'             => $user->email,
                    'phone'             => $user->phone,
                    // ⚠️ حذفت city_id: العمود مش موجود بجدول users إطلاقاً
                    // (كان دايماً بيرجع null). لو فعلاً محتاج مدينة/منطقة
                    // للمستخدم، لازم تُضاف migration جديدة تعمل الحقل هذا
                    // فعلياً، أو تعتمد على district_id تبع company_details
                    // تحت (وهو الموجود فعلياً حالياً).
                    'account_status'    => $user->status,
                    'is_email_verified' => !is_null($user->email_verified_at),
                    'is_phone_verified' => $user->hasVerifiedPhone(),
                    'settings_language' => $user->settings_language,
                    'settings_theme'    => $user->settings_theme,
                    'created_at'        => $user->created_at,
                ],

                // ─── الجدول 2: المزود كاملاً (Providers Table) ─────────
                'provider' => [
                    'id'                => $provider->id,
                    'brand_name'        => $provider->brand_name,
                    'provider_type'     => $provider->provider_type,
                    'rating'            => (float) $provider->rating,
                    'is_verified'       => (bool) $provider->is_verified,
                    'is_active'         => (bool) $provider->is_active,
                    'moderation_status' => $provider->moderation_status,
                    'rejection_reason'  => $provider->rejection_reason,
                   
                    'categories'        => $provider->categories->map(fn ($c) => [
                        'id' => $c->id,
              
                        'name_ar' => $c->getTranslation('name', 'ar'),
                        'name_en' => $c->getTranslation('name', 'en'),
                    ]),
                    'created_at'        => $provider->created_at,
                ],

                // ─── الجدول 3: تفاصيل الشركة أو الفريلانسر (حسب النوع) ──
                'provider_details' => $provider->provider_type === 'company'
                    ? [
                        'tax_number'      => $providerDetails?->tax_number,
                        'registration_no' => $providerDetails?->registration_no,
                        // ✅ ضفتهم هون: هاد المصدر الحقيقي الوحيد لـ
                        // district_id/address_details بكل قاعدة البيانات.
                        'district_id'     => $providerDetails?->district_id,
                        'address_details' => $providerDetails?->address_details,
                    ]
                    : [
                        'national_id'      => $providerDetails?->national_id,
                        'experience_years' => $providerDetails?->experience_years,
                        // ملاحظة: الفريلانسر ماله district/address بالبنية
                        // الحالية إطلاقاً (freelancer_details ماله هالأعمدة).
                        // لو محتاجينها، لازم migration جديدة تضيفها لهالجدول.
                    ],
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
/**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        try {
            $provider = Provider::with(['user', 'companyDetails', 'freelancerDetails', 'categories'])->find($id);

            if (!$provider) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'المزود غير موجود.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'user' => [
                        'id'         => $provider->user?->id,
                        'full_name'  => $provider->user?->first_name . ' ' . $provider->user?->last_name,
                        'email'      => $provider->user?->email,
                        'phone'      => $provider->user?->phone,
                        'account_status' => $provider->user?->status,
                    ],
                    'provider' => [
                        'id'                => $provider->id,
                        'brand_name'        => $provider->brand_name,
                        'provider_type'     => $provider->provider_type,
                        'rating'            => (float) $provider->rating,
                        'is_verified'       => (bool) $provider->is_verified,
                        'is_active'         => (bool) $provider->is_active,
                        'moderation_status' => $provider->moderation_status,
                        'categories'        => $provider->categories->map(fn ($c) => [
                            'id' => $c->id,
                            'name_ar' => $c->getTranslation('name', 'ar'),
                            'name_en' => $c->getTranslation('name', 'en'),
                        ]),
                    ],
                    'provider_details' => $provider->provider_type === 'company'
                        ? [
                            'tax_number'      => $provider->companyDetails?->tax_number,
                            'registration_no' => $provider->companyDetails?->registration_no,
                            'district_id'     => $provider->companyDetails?->district_id,
                            'address_details' => $provider->companyDetails?->address_details,
                        ]
                        : [
                            'national_id'      => $provider->freelancerDetails?->national_id,
                            'experience_years' => $provider->freelancerDetails?->experience_years,
                        ],
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error("Show Provider Error: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب البيانات، يرجى المحاولة لاحقاً.'
            ], 500);
        }
    }
}