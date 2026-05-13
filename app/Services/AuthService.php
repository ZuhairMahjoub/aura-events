<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    /**
     */
    public function createUser(array $data): User
    {
        return User::create([
            'id'                => (string) Str::ulid(), 
            
            'first_name'        => $data['first_name'] ?? 'Google',
            'last_name'         => $data['last_name'] ?? 'User',
            
            'email'             => $data['email'] ?? null,
            'phone'             => $data['phone'] ?? null,
            
            'password'          => Hash::needsRehash($data['password']) 
                                    ? Hash::make($data['password']) 
                                    : $data['password'],
            
            'settings_language' => $data['settings_language'] ?? 'ar',
            'settings_theme'    => $data['settings_theme'] ?? 'light',
        ]);
    }

    /**
     */
    public function formatPhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }
    
    /**
     */
    public function login(array $data)
    {
        $identity = $data['identity'] ?? ($data['phone'] ?? null); 
        
        if (!$identity) return null;

        $cleanIdentity = preg_replace('/\D/', '', $identity); 

        $user = User::where('email', $identity)
                    ->orWhere('phone', $identity)
                    ->orWhere('phone', $cleanIdentity)
                    ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return null; 
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token', ['*'], now()->addMonth())->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token
        ];
    }
}