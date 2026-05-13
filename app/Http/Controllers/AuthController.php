<?php

namespace App\Http\Controllers;
use App\Events\UserRegistered;
use Google\Client as GoogleClient;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite; 
use App\Models\User;
//use Google\Utils\Jwt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\Hash;
use App\Services\OtpService;
use App\Services\AuthService;



class AuthController extends Controller
{
    protected $authService;
  protected $otpService;
// حقن الخدمة عبر الـ Constructor
public function __construct(
        \App\Services\AuthService $authService, 
        \App\Services\OtpService $otpService
    ) {
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
    $validatedData = $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name'  => 'required|string|max:255',
        'identity'   => 'required', 
        'password'   => 'required|string|min:8|confirmed',
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

    $user = $this->authService->createUser($userData);

    event(new UserRegistered($user));

    return response()->json([
        'status'  => 'success',
        'message' => 'تم إنشاء الحساب بنجاح. يرجى تفعيل حسابك عبر الكود المرسل إلى ' . ($isEmail ? 'بريدك' : 'هاتفك'),
        'data'    => [
            'user' => $user
        ]
    ], 201);
}

    
    public function login(Request $request)
{
    $credentials = $request->validate([
        'identity' => 'required', 
        'password' => 'required',
    ]);

    $result = $this->authService->login([
        'identity' => $credentials['identity'], 
        'password' => $credentials['password']
    ]);

    if (!$result) {
        return response()->json([
            'status'  => 'error',
            'message' => 'بيانات الاعتماد غير صحيحة'
        ], 401);
    }

    $user = $result['user'];

    
    $accessToken = $user->createToken('access_token', ['access-api'], now()->addMinutes(15))->plainTextToken;

    $refreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

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




//  public function handleGoogleMobileLogin(Request $request)
// {
//     $idToken = $request->input('id_token');

//     if (!$idToken) {
//         return response()->json(['error' => 'Token is required'], 400);
//     }

//     $webClientId = config('services.google.client_id');
//     $mobileClientId = config('services.google.android');

//     $client = new \Google\Client(['client_id' => $mobileClientId]);
    
//     $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

//     try {
//         $payload = $client->verifyIdToken($idToken);

//         if ($payload) {
            
            
//             if ($payload['aud'] !== $webClientId && $payload['aud'] !== $mobileClientId) {
//                  return response()->json(['error' => 'Token was not issued for this application'], 401);
//             }

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
//                 ];

//                 $user = $this->authService->createUser($userData);
//             }

//             $token = $user->createToken('google_token')->plainTextToken;

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
                ];

                $user = $this->authService->createUser($userData);
            }

            $token = $user->createToken('google_token')->plainTextToken;

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

    $user->tokens()->delete();

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
}
