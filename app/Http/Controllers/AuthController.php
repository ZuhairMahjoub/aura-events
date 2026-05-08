<?php

namespace App\Http\Controllers;

use Google\Client as GoogleClient;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite; 
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT; 


class AuthController extends Controller
{
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

    public function handleGoogleMobileLogin(Request $request)
    {
        $idToken = $request->input('access_token');

        if (!$idToken) {
            return response()->json(['error' => 'Token not provided'], 400);
        }

        try {
            if (class_exists('\Firebase\JWT\JWT')) {
                \Firebase\JWT\JWT::$leeway = 60; 
            }

            $client = new GoogleClient(['client_id' => config('services.google.android')]); 
            $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
            
            $payload = $client->verifyIdToken($idToken);

            if ($payload) {
                $user = User::updateOrCreate(
                    ['email' => $payload['email']],
                    [
                        'first_name' => $payload['given_name'] ?? 'Google',
                        'last_name'  => $payload['family_name'] ?? 'User',
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
            }

            return response()->json(['error' => 'Invalid Token'], 401);

        } catch (Exception $e) {
            return response()->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }


public function store(Request $request)
{
    $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = User::create([
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        // اترك email_verified_at فارغاً ليتم إرسال الإيميل
    ]);

    // إرسال إشعار التحقق
    event(new Registered($user));

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'User registered. Please verify your email.',
        'access_token' => $token,
        'token_type' => 'Bearer',
    ]);
}
public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'access_token' => $token,
        'token_type' => 'Bearer',
    ]);
}

}
