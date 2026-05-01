<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite; 
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    
    public function redirectToGoogle()
{
    
    return Socialite::driver('google')->stateless()->redirect();
}


public function handleGoogleCallBack()
{
    try {
        
        $googleUser = Socialite::driver('google')->stateless()->user();

        
        $nameParts = explode(' ', $googleUser->name, 2);
        $user = User::updateOrCreate(
            ['email' => $googleUser->email],
            [
                'first_name'  => $nameParts[0],
                'last_name'   => $nameParts[1] ?? '',
                'provider'    => 'google',
                'provider_id' => $googleUser->id,
                'password'    => Hash::make(Str::random(24)),
            ]
        );

        
        
        $token = $user->createToken('auth_token')->plainTextToken;

        
        return response()->json([
            'status' => 'Success',
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'user'         => [
                    'id'    => $user->id,
                    'name'  => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                ]
            ]
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status' => 'Error',
            'message' => 'حدث خطأ أثناء الاتصال بجوجل',
            'error' => $e->getMessage()
        ], 401);
    }
}
}