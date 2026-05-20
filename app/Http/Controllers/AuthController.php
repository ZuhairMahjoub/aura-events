<?php

namespace App\Http\Controllers;

use App\Events\UserRegistered;
use Google\Client as GoogleClient;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite; 
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\Hash;
use App\Services\OtpService;
use App\Services\AuthService;

class AuthController extends Controller
{
    protected $authService;
    protected $otpService;

    public function __construct(AuthService $authService, OtpService $otpService) 
    {
        $this->authService = $authService;
        $this->otpService = $otpService;
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

public function store(Request $request)
{
    if ($request->has('identity')) {
        $identity = $request->input('identity');
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        $cleanIdentity = !$isEmail ? $this->authService->formatPhone($identity) : $identity;

        $pendingUser = \App\Models\User::where(function($query) use ($cleanIdentity) {
                            $query->where('email', $cleanIdentity)
                                  ->orWhere('phone', $cleanIdentity);
                        })
                        ->whereNull('email_verified_at')
                        ->whereNull('phone_verified_at')
                        ->first();

        if ($pendingUser) {
            $cacheKey = 'otp_' . $cleanIdentity;
            $hasExpiredOtp = !\Illuminate\Support\Facades\Cache::has($cacheKey);
            $isOldAccount = $pendingUser->created_at->addMinutes(10)->isPast();

            if ($hasExpiredOtp && $isOldAccount) {
                $pendingUser->roles()->detach();  
                $pendingUser->delete();         
            }
        }
    }
    $validatedData = $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name'  => 'required|string|max:255',
        'identity'   => 'required', 
        'password'   => 'required|string|min:8|confirmed',
        'role'       => 'nullable|string|in:client,provider,organizer', // الأدوار المسموح بها
    ]);

    $identity = $validatedData['identity'];
    $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
    $cleanIdentity = !$isEmail ? $this->authService->formatPhone($identity) : $identity;

    $userData = [
        'first_name' => $validatedData['first_name'],
        'last_name'  => $validatedData['last_name'],
        'password'   => $validatedData['password'],
        'email'      => $isEmail ? $cleanIdentity : null,
        'phone'      => !$isEmail ? $cleanIdentity : null,
    ];

    $roleName = $request->input('role', 'client'); 

    try {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($userData, $isEmail, $roleName) {
            
            $user = $this->authService->createUser($userData);

            $role = \App\Models\Role::where('name', $roleName)->where('guard_name', 'api')->first();
            
            if ($role) {
                $user->assignRole($role);
            } else {
                $user->assignRole($roleName); 
            }

            event(new \App\Events\UserRegistered($user));

            return response()->json([
                'status'  => 'success',
                'message' => 'تم إنشاء الحساب بنجاح وإسناد الصلاحيات. يرجى تفعيل حسابك عبر الكود المرسل إلى ' . ($isEmail ? 'بريدك' : 'هاتفك'),
                'data'    => [
                    'user' => $user->load('roles') 
                ]
            ], 201);
        });

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error("Registration Failed: " . $e->getMessage());

        return response()->json([
            'status'  => 'error',
            'message' => 'حدث خطأ أثناء إنشاء الحساب، يرجى المحاولة لاحقاً.',
            'debug'   => config('app.debug') ? $e->getMessage() : null 
        ], 500);
    }
}
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'identity' => 'required', 
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['identity'])
                    ->orWhere('phone', $this->authService->formatPhone($credentials['identity']))
                    ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'بيانات الاعتماد غير صحيحة'
            ], 401);
        }

        if (is_null($user->email_verified_at) && is_null($user->phone_verified_at)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'عذراً، يجب تفعيل الحساب أولاً عبر الكود المرسل إليك.',
                'is_verified' => false      
            ], 403); 
        }

        $user->tokens()->delete();

        $accessToken = $user->createToken('access_token', ['access-api'], now()->addMinutes(15))->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

        $user->load('roles');

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تسجيل الدخول بنجاح',
            'data'    => [
                'user'          => $user,
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in'    => 15 * 60, 
            ]
        ], 200);
    }

    // public function handleGoogleMobileLogin(Request $request)
    // {
    //     $idToken = $request->input('id_token');

    //     if (!$idToken) {
    //         return response()->json(['error' => 'Token is required'], 400);
    //     }

    //     $mobileClientId = "45320069047-hsglkfoe70gvltgroni6e5ggert8v72m.apps.googleusercontent.com";

    //     $client = new \Google\Client(['client_id' => $mobileClientId]);
    //     $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

    //     try {
    //         $payload = $client->verifyIdToken($idToken);

    //         if ($payload) {
    //             $user = User::where('email', $payload['email'])->first();

    //             if (!$user) {
    //                 $fullName = $payload['name'] ?? 'Google User';
    //                 $nameParts = explode(' ', $fullName, 2);
                    
    //                 $userData = [
    //                     'first_name' => $nameParts[0],
    //                     'last_name'  => $nameParts[1] ?? ' ',
    //                     'email'      => $payload['email'],
    //                     'password'   => \Illuminate\Support\Str::random(24),
    //                     'phone'      => null,
    //                     'role'       => 'organizer'
    //                 ];

    //                 $user = $this->authService->createUser($userData);
    //             }

    //             $token = $user->createToken('google_token')->plainTextToken;
    //             $user->load('roles');

    //             return response()->json([
    //                 'status'  => 'success',
    //                 'user'    => $user,
    //                 'access_token' => $token,
    //             ], 200);

    //         } else {
    //             return response()->json(['error' => 'Invalid ID Token'], 401);
    //         }

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'error'   => 'Authentication failed',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }
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

                if (!$user) {
                    $fullName = $payload['name'] ?? 'Google User';
                    $nameParts = explode(' ', $fullName, 2);
                    
                    $userData = [
                        'first_name' => $nameParts[0],
                        'last_name'  => $nameParts[1] ?? ' ',
                        'email'      => $payload['email'],
                        'password'   => \Illuminate\Support\Str::random(24),
                        'phone'      => null,
                        'role'       => 'organizer'
                    ];

                    $user = $this->authService->createUser($userData);
                }

                $token = $user->createToken('google_token')->plainTextToken;
                $user->load('roles');

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
            'phone' => 'required|string',
            'code'  => 'required|string|min:6',
        ]);

        $isValid = $this->otpService->verifyOtp($validated['phone'], $validated['code']);

        if (!$isValid) {
            return response()->json([
                'status'  => 'error',
                'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته'
            ], 422);
        }

        $formattedPhone = $this->otpService->formatPhone($validated['phone']);
        $user = User::where('phone', $formattedPhone)->first();
        
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'المستخدم غير موجود'], 404);
        }

        $user->phone_verified_at = now(); 
        $user->save();

        $accessToken = $user->createToken('access_token', ['access-api'], now()->addMinutes(15))->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

        $user->load('roles');

        return response()->json([
            'status'  => 'success',
            'message' => 'تم التحقق بنجاح',
            'data'    => [
                'user'          => $user,
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in'    => 15 * 60,
            ]
        ], 200);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();
        }

        $newAccessToken = $user->createToken('access_token', ['access-api'], now()->addMinutes(15))->plainTextToken;
        $newRefreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الخروج بنجاح وإبطال جميع المفاتيح'
        ], 200);
    }
    public function resendOtp(Request $request)
{
    $validatedData = $request->validate([
        'identity' => 'required|string', 
    ]);

    $identity = $validatedData['identity'];
    
    $cleanIdentity = $this->otpService->formatPhone($identity);

    $user = \App\Models\User::where('phone', $cleanIdentity)
                    ->whereNull('phone_verified_at')
                    ->first();

    if (!$user) {
        return response()->json([
            'status'  => 'error',
            'message' => 'عذراً، هذا الرقم غير موجود أو تم تفعيله مسبقاً.'
        ], 442);
    }

    try {
        $newCode = $this->otpService->generateForPhone($cleanIdentity);

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
        \Illuminate\Support\Facades\Log::error("Resend OTP Failed: " . $e->getMessage());

        return response()->json([
            'status'  => 'error',
            'message' => 'حدث خطأ غير متوقع أثناء إعادة إرسال الكود.',
            'debug'   => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
}