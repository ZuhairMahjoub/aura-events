<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * إصلاح: كان فحص "نوع الحساب" (company / freelancer) مكرَّراً يدوياً داخل
 * كل ميثود من JobOfferController بثلاث نسخ غير متطابقة — بعضها يستخدم
 * trim(strtolower(...)) وبعضها لا، مما يعني أن قيمة provider_type بحالة
 * حروف مختلفة ("Company" مثلاً) قد تُقبل بمكان وتُرفض بمكان آخر لنفس
 * الحساب. توحيد الفحص هنا في مكان واحد يضمن سلوكاً متسقاً، ويزيل تكرار
 * الكود، ويسمح بإضافة provider_type جديد مستقبلاً (مثلاً 'agency') من مكان
 * واحد فقط.
 *
 * الاستخدام في routes/api.php:
 *   Route::middleware(['approved_provider', 'provider_type:company'])->group(...)
 *   Route::middleware(['approved_provider', 'provider_type:freelancer'])->group(...)
 */
class EnsureProviderType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $provider = $request->user()?->providerProfile;

        $actualType = $provider ? trim(strtolower($provider->provider_type ?? '')) : null;

        if ($actualType !== trim(strtolower($type))) {
            return response()->json([
                'success' => false,
                'message' => $type === 'company'
                    ? 'عذراً، هذا الإجراء متاح فقط لحسابات الشركات.'
                    : 'يجب أن يكون حسابك من نوع فريلانسر للقيام بهذا الإجراء.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}