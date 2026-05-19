<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIfAccountExists
{
    protected $authService;

    // حقن السيرفيس لاستخدام دالة الفرمتة الموحدة للقم الهاتف
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 💡 قراءة حقل الـ identity الموحد القادم من الفرونت-إند (Flutter)
        $identity = $request->input('identity');
        
        // إذا لم يتم إرسال الهوية، نترك الطلب يمر لتلتقطه قواعد الـ Validation في الـ Form Request
        if (!$identity) {
            return $next($request);
        }

        // 1. فحص نوع الهوية الممررة (هل هي إيميل أم رقم هاتف؟)
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        
        // 2. تجهيز القيمة المصفاة للبحث (فرمتة الهاتف إذا لم يكن إيميلاً)
        $formattedIdentity = $isEmail ? $identity : $this->authService->formatPhone($identity);

        // 3. الاستعلام عن المستخدم بناءً على النوع المستنتج
        $user = $isEmail 
            ? User::where('email', $formattedIdentity)->first()
            : User::where('phone', $formattedIdentity)->first();

        // 4. إذا وُجد الحساب مسبقاً، نقوم بمعالجته فوراً وحظر عملية التسجيل المكررة
        if ($user) {
            $isVerified = !is_null($user->phone_verified_at) || !is_null($user->email_verified_at);

            // ⏳ الحالة الأولى: الحساب مسجل وموجود ولكنه غير مفعّل بعد
            if (!$isVerified) {
                // توليد توكن تفعيل مؤقت (15 دقيقة) لعملية الـ OTP
                $verifyToken = $user->createToken('verification_token', ['verify-account'], now()->addMinutes(15))->plainTextToken;

                return response()->json([
                    'status'  => 'error',
                    'message' => 'هذا الحساب مسجل مسبقاً ولكن لم يتم تفعيله، يرجى تأكيد الحساب عبر رمز الـ OTP.',
                    'data'    => [
                        'requires_verification' => true,
                        'verification_token'    => $verifyToken,
                    ]
                ], 403); // 403 Forbidden الحارس للحسابات غير المؤكدة
            }

            // ✅ الحالة الثانية: الحساب شغال، مؤكد، ومفعّل بالكامل في قاعدة البيانات
            return response()->json([
                'status'  => 'error',
                'message' => 'هذا الحساب مسجل ومفعّل بالفعل لدينا، يرجى التوجه إلى واجهة تسجيل الدخول مباشرة.'
            ], 409); // 409 Conflict لمنع التكرار الجسيم للبيانات
        }

        // إذا كان الحساب جديداً كلياً ولم يعثر عليه، يمر الطلب بسلام ليتم إنشاؤه عبر الـ Controller
        return $next($request);
    }
}