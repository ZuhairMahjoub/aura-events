<?php

namespace App\Services;

use App\Events\UserRegistered;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    public function createUser(array $data, array $roles = []): User
    {
        $user = User::create([
            'id'                   => (string) Str::ulid(),
            'first_name'           => $data['first_name'],
            'last_name'            => $data['last_name'],
            'email'                => $data['email'] ?? null,
            'phone'                => $data['phone'] ?? null,
            'password'             => Hash::make($data['password']),
            'is_profile_completed' => false,
            'settings_language'    => $data['settings_language'] ?? 'ar',
            'settings_theme'       => $data['settings_theme'] ?? 'light',
        ]);

        if (!empty($roles)) {
            $user->assignRole($roles);
        }

        return $user;
    }

 public function register(array $data, array $roles): User|array
{
    $identity = $data['identity'];
    $isEmail  = filter_var($identity, FILTER_VALIDATE_EMAIL);

    $existingUser = $isEmail
        ? User::where('email', $identity)->first()
        : User::where('phone', $this->formatPhone($identity))->first();

    if ($existingUser) {
        $isVerified = $existingUser->hasVerifiedEmail()
                   || !is_null($existingUser->phone_verified_at);

        if ($isVerified) {
        throw new Exception('البيانات مسجلة مسبقاً.', 409);
        }

        // حساب عالق - نرجعه مع flag عشان الـ Controller يتعامل معه
        return ['pending_user' => $existingUser];
    }

    $userData = [
        'first_name' => $data['first_name'],
        'last_name'  => $data['last_name'],
        'email'      => $isEmail ? $identity : null,
        'phone'      => !$isEmail ? $this->formatPhone($identity) : null,
        'password'   => $data['password'],
    ];

    $user = $this->createUser($userData, $roles);

    event(new UserRegistered($user));

    return $user;
}
    public function findOrCreateGoogleUser(string $email, string $firstName, string $lastName): User
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = $this->createUser([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'password'   => Str::random(24),
            ], ['organizer']);
        }

        if (is_null($user->email_verified_at)) {
            $user->update(['email_verified_at' => now()]);
        }

        if ($user->roles->isEmpty()) {
            $user->assignRole('organizer');
        }

        return $user;
    }

    public function login(array $data): ?array
    {
        $identity = $data['identity'] ?? null;
        if (!$identity) return null;

        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        $isPhone = preg_match('/^(?:\+963|00963|0)?9[0-9]{8}$/', $identity);

        if (!$isEmail && !$isPhone) return null;

        if ($isEmail) {
            $user = User::where('email', $identity)->first();
        } else {
            $formatted = $this->formatPhone($identity);
            $user = User::where('phone', $formatted)->first();
        }

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return null;
        }

        if (is_null($user->phone_verified_at) && is_null($user->email_verified_at)) {
            return ['error' => 'unverified', 'user' => $user];
        }

        $user->tokens()->where('name', 'access_token')->delete();

        return ['user' => $user];
    }

    public function formatPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '00963') && strlen($cleaned) === 14) {
            $cleaned = substr($cleaned, 5);
        } elseif (str_starts_with($cleaned, '963') && strlen($cleaned) === 12) {
            $cleaned = substr($cleaned, 3);
        } elseif (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
            $cleaned = substr($cleaned, 1);
        }

        if (strlen($cleaned) === 9 && str_starts_with($cleaned, '9')) {
            return $cleaned;
        }

        return $cleaned;
    }
}