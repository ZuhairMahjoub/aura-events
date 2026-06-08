<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    
    public function createUser(array $data): User
    {
        return User::create([
            'id'                => (string) Str::ulid(), 
            'first_name'        => $data['first_name'] ?? 'Google',
            'last_name'         => $data['last_name'] ?? 'User',
            'email'             => $data['email'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'phone_verified_at' => $data['phone_verified_at'] ?? null,
            'password'          => Hash::needsRehash($data['password']) 
                                    ? Hash::make($data['password']) 
                                    : $data['password'],
            'settings_language' => $data['settings_language'] ?? 'ar',
            'settings_theme'    => $data['settings_theme'] ?? 'light',
        ]);
    }

    
    public function formatPhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }
    
    
    public function login(array $data): array
    {
        $identity = $data['identity'] ?? null; 
        if (!$identity) {
            return ['status' => 'error', 'type' => 'invalid_credentials', 'code' => 401];
        }

        $cleanPhone = $this->formatPhone($identity); 

        // البحث عن المستخدم بالبريد الإلكتروني أو الهاتف
        $user = User::where('email', $identity)
                    ->orWhere('phone', $cleanPhone)
                    ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return ['status' => 'error', 'type' => 'invalid_credentials', 'code' => 401];
        }

        if (is_null($user->email_verified_at) && is_null($user->phone_verified_at)) {
            return ['status' => 'error', 'type' => 'not_verified', 'code' => 403];
        }

        return [
            'status' => 'success',
            'user'   => $user
        ];
    }
}