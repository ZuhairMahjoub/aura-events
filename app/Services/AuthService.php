<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * * @param array $data
     * @return User
     */
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

    /**
     * * @param string $phone
     * @return string
     */
    public function formatPhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }
    
    /**
     * * @param array $data
     * @return User|null
     */
    public function login(array $data): ?User
    {
        $identity = $data['identity'] ?? ($data['phone'] ?? null); 
        
        if (!$identity) {
            return null;
        }

        $cleanIdentity = $this->formatPhone($identity); 

        $user = User::where('email', $identity)
                    ->orWhere('phone', $identity)
                    ->orWhere('phone', $cleanIdentity)
                    ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return null; 
        }

        return $user;
    }
}