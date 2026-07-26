<?php

namespace App\Http\Controllers;

use App\Events\UserRegistered;
use App\Events\UserVerified;
use Google\Client as GoogleClient;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\DeviceToken;
use App\Models\Provider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;
use App\Services\OtpService;
use App\Services\AuthService;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    protected $authService;
    protected $otpService;
    protected $firebaseNotificationService;

    public function __construct(AuthService $authService, OtpService $otpService, FirebaseNotificationService $firebaseNotificationService)
    {
        $this->authService = $authService;
        $this->otpService = $otpService;
        $this->firebaseNotificationService = $firebaseNotificationService;
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallBack()
    {
        try {
            if (class_exists('\Firebase\JWT\JWT')) {
                \Firebase\JWT\JWT::$leeway = 60;
            }

            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'first_name' => $googleUser->offsetGet('given_name') ?? 'Google',
                    'last_name'  => $googleUser->offsetGet('family_name') ?? 'User',
                    'password'   => Hash::make(Str::random(16)),
                    'email_verified_at' => now(),
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'access_token' => $token,
                'user' => $user
            ], 200);
        } catch (Exception $e) {
            Log::error('Google Callback Error: ' . $e->getMessage());
            return response()->json(['error' => 'Auth Failed: ' . $e->getMessage()], 500);
        }
    }
    /**
     * GET /admin/users
     * جلب جميع المستخدمين أو مزودي الخدمة بناءً على فلاتر ديناميكية مرسلة من الفرونت إند.
     */
    public function getFilteredUsers(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $query = \App\Models\User::query();

            if ($request->filled('role')) {
                $query->role($request->input('role'));
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                $query->whereHas('providerProfile', function ($q) use ($status) {
                    $q->where('status', $status);
                });
            }

            // 3. الفلترة حسب التفعيل (مفعل الحساب بريد/هاتف أم لا)
            if ($request->has('verified')) {
                $isVerified = filter_var($request->input('verified'), FILTER_VALIDATE_BOOLEAN);

                if ($isVerified) {
                    $query->where(function ($q) {
                        $q->whereNotNull('email_verified_at')
                            ->orWhereNotNull('phone_verified_at');
                    });
                } else {
                    $query->whereNull('email_verified_at')
                        ->whereNull('phone_verified_at');
                }
            }

            // 4. نظام بحث ذكي (Search) بالاسم، الإيميل، أو رقم الهاتف
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // جلب البيانات مع العلاقات والـ Pagination
            $users = $query->with(['providerProfile'])
                ->latest()
                ->paginate($request->input('per_page', 15)); // إمكانية التحكم بعدد العناصر بالصفحة

            return response()->json([
                'status' => 'success',
                'data'   => $users->items(),
                'meta'   => [
                    'current_page' => $users->currentPage(),
                    'last_page'    => $users->lastPage(),
                    'total'        => $users->total(),
                    'per_page'     => $users->perPage(),
                ]
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Fetch Filtered Users Failed: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب البيانات المفلترة.',
                'debug'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    public function profile()
    {
        $provider = Provider::with([
            'user',
            'companyDetail.district'
        ])
            ->where('user_id', auth()->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $provider
        ]);
    }
    public function getProfile(Request $request)
    {
        $user = $request->user()->load(['roles', 'providerProfile']);

        return response()->json([
            'status' => 'success',
            'data'   => $user
        ], 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'identity'   => 'required',
            'password'   => 'required|string|min:8|confirmed',
            'role'       => 'nullable|string|in:client,provider,organizer',
        ]);

        $identity = $validatedData['identity'];
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        $cleanIdentity = !$isEmail ? $this->otpService->formatPhone($identity) : $identity;

        $pendingUser = User::where(function ($query) use ($cleanIdentity) {
            $query->where('email', $cleanIdentity)->orWhere('phone', $cleanIdentity);
        })
            ->whereNull('email_verified_at')
            ->whereNull('phone_verified_at')
            ->first();

        if ($pendingUser) {
            $cacheKey = $this->otpService->getCacheKey($cleanIdentity);

            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'هذا الحساب مسجل بالفعل ولكنه غير مفعل، يرجى تفعيله بكود الـ OTP المرسل إليك مسبقاً.'
                ], 400);
            }

            $pendingUser->roles()->detach();
            $pendingUser->delete();
        }

        $userData = [
            'first_name' => $validatedData['first_name'],
            'last_name'  => $validatedData['last_name'],
            'password'   => $validatedData['password'],
            'email'      => $isEmail ? $cleanIdentity : null,
            'phone'      => !$isEmail ? $cleanIdentity : null,
        ];

        $roleName = $request->input('role', 'client');

        try {
            $user = DB::transaction(function () use ($userData, $roleName) {
                $user = User::withoutEvents(function () use ($userData) {
                    return $this->authService->createUser($userData);
                });

                $user->assignRole($roleName);
                return $user;
            });

            event(new UserRegistered($user));

            $message = $isEmail
                ? 'تم إنشاء الحساب بنجاح. يرجى تفعيل حسابك عبر كود الـ OTP المرسل إلى بريدك الإلكتروني.'
                : 'تم إنشاء الحساب بنجاح. يرجى تفعيل حسابك عبر كود الـ OTP المرسل إلى واتساب هاتفك.';

            return response()->json([
                'status'  => 'success',
                'message' => $message,
                'data'    => [
                    'user' => $user->load('roles')
                ]
            ], 201);
        } catch (Exception $e) {
            Log::error("Registration Failed: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء إنشاء الحساب، يرجى المحاولة لاحقاً.'
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'identity'     => 'required',
            'password'     => 'required',
            'device_token' => 'nullable|string',
        ]);

        $result = $this->authService->login($credentials);

        if ($result['status'] === 'error') {
            if ($result['type'] === 'not_verified') {
                return response()->json([
                    'status'      => 'error',
                    'message'     => 'عذراً، يجب تفعيل الحساب أولاً عبر الرابط المرسل لبريدك أو كود الـ OTP.',
                    'is_verified' => false
                ], 403);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'بيانات الاعتماد غير صحيحة'
            ], 401);
        }

        $user = $result['user'];
        $user->update(['status' => 'active']);
        if ($request->filled('device_token')) {
            DeviceToken::updateOrCreate(
                ['device_token' => $request->input('device_token')],
                ['user_id'      => $user->id]
            );
        }


        $user->tokens()->where('expires_at', '<', now())->delete();
        $accessToken = $user->createToken('access_token', ['access-api'], now()->addHours(24))->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

        $user->load('roles');

        if ($user->hasRole('provider')) {
            $user->setAttribute('provider_type', $user->providerProfile?->provider_type ?? null);
        }

        $this->firebaseNotificationService->sendToUser(
            $user->id,
            'New Login Detected 🔒',
            'Your account was just accessed. If this wasn\'t you, please secure your account.',
            [
                'action' => 'security_alert',
                'time'   => now()->toDateTimeString()
            ]
            $user->id, 
           __('notification_login_title'), 
            __('notification_login_body'), 
         [
        'action' => 'security_alert',
        'time'   => now()->toDateTimeString()
         ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تسجيل الدخول بنجاح',
            'data'    => [
                'user'          => $user,
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in'    => 60 * 60 * 24,
            ]
        ], 200);
    }

    public function handleGoogleMobileLogin(Request $request)
    {
        $idToken = $request->input('id_token');

        if (!$idToken) {
            return response()->json(['error' => 'Token is required'], 400);
        }

        $mobileClientId = "45320069047-hsglkfoe70gvltgroni6e5ggert8v72m.apps.googleusercontent.com";
        $client = new \Google\Client(['client_id' => $mobileClientId]);
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

        try {
            $payload = $client->verifyIdToken($idToken);

            if ($payload) {
                $user = User::where('email', $payload['email'])->first();
                $isNewUser = false;

                if (!$user) {
                    $isNewUser = true;
                    $fullName = $payload['name'] ?? 'Google User';
                    $nameParts = explode(' ', $fullName, 2);

                    $userData = [
                        'first_name' => $nameParts[0],
                        'last_name'  => $nameParts[1] ?? ' ',
                        'email'      => $payload['email'],
                        'password'   => \Illuminate\Support\Str::random(24),
                        'phone'      => null,
                        'role'       => 'organizer',
                        'email_verified_at' => now(),
                    ];

                    $user = $this->authService->createUser($userData);
                    $user->assignRole('organizer');
                }
                $user->update(['status' => 'active']);

                if ($request->filled('device_token')) {
                    DeviceToken::updateOrCreate(
                        ['device_token' => $request->input('device_token')],
                        ['user_id'      => $user->id]
                    );
                }

                $token = $user->createToken('google_token')->plainTextToken;
                $user->load('roles');

                if ($isNewUser) {
                    $this->firebaseNotificationService->sendToUser(
                        $user->id,
                        __('notification_welcome_title'),
                        __('notification_welcome_body'),
                        ['action' => 'open_home']
    );
                } else {
                    $this->firebaseNotificationService->sendToUser(
                        $user->id,
                        __('notification_google_login_title'),
                        __('notification_google_login_body'),
                        ['action' => 'security_alert', 'time' => now()->toDateTimeString()]
    ); 
                }

                return response()->json([
                    'status'  => 'success',
                    'user'    => $user,
                    'access_token' => $token,
                ], 200);
            } else {
                return response()->json(['error' => 'Invalid ID Token'], 401);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Authentication failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'identity'     => 'required|string',
            'code'         => 'required_without:otp|string|min:6',
            'otp'          => 'required_without:code|string|min:6',
            'device_token' => 'nullable|string',
        ]);

        $identity = $validated['identity'];
        $otpCode = $request->input('code') ?? $request->input('otp');

        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        $cleanIdentity = !$isEmail ? $this->otpService->formatPhone($identity) : $identity;

        $isValid = $this->otpService->verifyOtp($cleanIdentity, $otpCode);
        if (!$isValid) {
            return response()->json([
                'status'  => 'error',
                'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته'
            ], 422);
        }

        $user = User::where(function ($query) use ($cleanIdentity) {
            $query->where('email', $cleanIdentity)->orWhere('phone', $cleanIdentity);
        })->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'المستخدم غير موجود'], 404);
        }

        if ($isEmail) {
            $user->email_verified_at = now();
        } else {
            $user->phone_verified_at = now();
        }
        $user->save();

        if ($request->filled('device_token')) {
            DeviceToken::updateOrCreate(
                ['device_token' => $request->input('device_token')],
                ['user_id'      => $user->id]
            );
        }

        $user->tokens()->where('expires_at', '<', now())->delete();

        $accessToken  = $user->createToken('access_token', ['access-api'], now()->addHours(24))->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

        $user->load('roles');

        $this->firebaseNotificationService->sendToUser(
            $user->id,
            'Welcome to Aura Events! 🎉',
            'Your account has been verified successfully. Welcome aboard!',
            ['action' => 'open_home', 'user_id' => $user->id]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'تم التحقق بنجاح وتفعيل الحساب.',
            'data'    => [
                'user'          => $user,
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in'    => 60 * 60 * 24,
            ]
        ], 200);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        if (!$currentToken || !$currentToken->can('issue-access-token')) {
            return response()->json([
                'status' => 'error',
                'message' => 'غير مصرح بإجراء هذه العملية باستخدام هذا مفتاح.'
            ], 403);
        }

        $user->tokens()->where('expires_at', '<', now())->delete();
        $currentToken->delete();

        $newAccessToken  = $user->createToken('access_token', ['access-api'], now()->addHours(24))->plainTextToken;
        $newRefreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'access_token'  => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'expires_in'    => 60 * 60 * 24,
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($request->has('device_token')) {
            DeviceToken::where('user_id', $user->id)
                ->where('device_token', $request->input('device_token'))
                ->delete();
        }

        $user->update(['status' => 'inactive']);

        $user->currentAccessToken()->delete();

    return response()->json([
        'status'  => 'success',
        'message' => __('logout_success') ], 200);
}

    public function resendOtp(Request $request)
    {
        $validatedData = $request->validate([
            'identity' => 'required|string',
        ]);

        $identity = $validatedData['identity'];
        $cleanIdentity = $this->otpService->formatPhone($identity);

        $user = User::where('phone', $cleanIdentity)
            ->whereNull('email_verified_at')
            ->whereNull('phone_verified_at')
            ->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'عذراً، هذا الرقم غير موجود أو تم تفعيل الحساب مسبقاً.'
            ], 442);
        }

        try {
            $newCode = $this->otpService->generateOtp($cleanIdentity);
            $isSent = $this->otpService->sendViaWhatsapp($cleanIdentity, $newCode);

            if ($isSent) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'تم إعادة إرسال كود التحقق إلى الواتس آب بنجاح، صلاحية الكود 10 دقائق.',
                ], 200);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'فشل إرسال رسالة الواتس آب، يرجى المحاولة لاحقاً.'
            ], 500);
        } catch (\Exception $e) {
            Log::error("Resend OTP Failed: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ غير متوقع أثناء إعادة إرسال الكود.',
                'debug'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
