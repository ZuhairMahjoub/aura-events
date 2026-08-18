<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

                        'categories'        => $provider->categories->map(fn($c) => [
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
    /**
 * جلب رابط الـ QR Code الخاص بالمزوّد الحالي (المستخدم المسجّل دخوله).
 */
public function getQrCode(Request $request): JsonResponse
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

        $provider = $user->providerProfile;

        if (!$provider) {
            return response()->json([
                'status'  => 'error',
                'message' => 'لم يتم إكمال بيانات البروفايل بعد.'
            ], 404);
        }

        if (!$provider->qr_code_path) {
            return response()->json([
                'status'  => 'error',
                'message' => 'لا يوجد QR Code مرفوع حتى الآن.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'qr_url' => asset('storage/' . $provider->qr_code_path),
            ],
        ]);
    } catch (\Exception $e) {
        Log::error("Get Provider QR Code Error: " . $e->getMessage());
        return response()->json([
            'status'  => 'error',
            'message' => 'حدث خطأ أثناء جلب رمز QR، يرجى المحاولة لاحقاً.'
        ], 500);
    }
}
    /**
     * تحديث بروفايل المزوّد (شركة أو فريلانسر) عبر 3 جداول دفعة واحدة:
     * users (اسم/إعدادات) + providers (brand_name) + company_details أو
     * freelancer_details حسب النوع. كل شي جوا transaction واحدة حتى لا
     * ينحدّث جدول وينفشل تاني بمنتصف الطريق.
     */
    public function update(\App\Http\Requests\UpdateProviderProfileRequest $request): JsonResponse
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

            $provider = $user->providerProfile;

            if (!$provider) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'لم يتم إكمال بيانات البروفايل بعد.'
                ], 404);
            }

            $validated = $request->validated();

            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $provider, $validated) {
                // 1) users: بدون city_id — العمود غير موجود فعلياً بجدول
                // users رغم وجوده بـ User::$fillable (راجع ملاحظة profile()).
                $userData = collect($validated)
                    ->only(['first_name', 'last_name', 'phone', 'email', 'settings_language', 'settings_theme'])
                    ->toArray();

                if (!empty($userData)) {
                    // لو تغيّر phone أو email فعلياً عن القيمة القديمة، لازم
                    // نصفّر توثيقه — التوثيق القديم كان لرقم/إيميل مختلف،
                    // فبقاؤه verified بعد التغيير مضلّل وغير آمن.
                    if (array_key_exists('phone', $userData) && $userData['phone'] !== $user->phone) {
                        $userData['phone_verified_at'] = null;
                    }
                    if (array_key_exists('email', $userData) && $userData['email'] !== $user->email) {
                        $userData['email_verified_at'] = null;
                    }

                    $user->update($userData);
                }

                // 2) providers: brand_name فقط قابل للتعديل هون
                $providerData = collect($validated)->only(['brand_name'])->toArray();

                if (!empty($providerData)) {
                    $provider->update($providerData);
                }

                // 3) تفاصيل خاصة بالنوع
                if ($provider->provider_type === 'company') {
                    $companyData = collect($validated)
                        ->only(['tax_number', 'registration_no', 'district_id', 'address_details'])
                        ->toArray();

                    if (!empty($companyData)) {
                        // updateOrCreate: بعض الشركات القديمة ممكن ماعندهاش
                        // سجل company_details أصلاً بعد (تسجيل ناقص).
                        $provider->companyDetails()->updateOrCreate(
                            ['provider_id' => $provider->id],
                            $companyData
                        );
                    }
                } elseif ($provider->provider_type === 'freelancer') {
                    $freelancerData = collect($validated)
                        ->only(['national_id', 'experience_years'])
                        ->toArray();

                    if (!empty($freelancerData)) {
                        $provider->freelancerDetails()->updateOrCreate(
                            ['provider_id' => $provider->id],
                            $freelancerData
                        );
                    }
                }

                // 4) التصنيفات: sync كامل — القائمة المرسلة تحل محل الحالية
                // بالكامل (مو إضافة تراكمية)، مشترك بين النوعين.
                if (array_key_exists('category_ids', $validated)) {
                    $provider->categories()->sync($validated['category_ids']);
                }
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تحديث البروفايل بنجاح.',
                'data'    => $this->profile($request)->getData(true)['data'],
            ], 200);
        } catch (\Exception $e) {
            Log::error("Update Provider Profile Error: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء تحديث البيانات، يرجى المحاولة لاحقاً.'
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
     * جلب رصيد محفظة المزوّد الحالي.
     */
    public function wallet(Request $request): JsonResponse
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
                    'wallet_balance' => (float) $provider->wallet_balance,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error("Get Provider Wallet Error: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب تفاصيل المحفظة، يرجى المحاولة لاحقاً.'
            ], 500);
        }
    }
}
