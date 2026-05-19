<?php

namespace App\Services;

use App\Events\UserRegistered;
use App\Jobs\DeleteUnverifiedUsersJob;
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

public function register(array $data, array $roles): User
{
    $identity = $data['identity'];
    $isEmail  = filter_var($identity, FILTER_VALIDATE_EMAIL);

    $existingUser = $isEmail
        ? User::where('email', $identity)->first()
        : User::where('phone', $this->formatPhone($identity))->first();

  
    $userData = [
        'first_name' => $data['first_name'],
        'last_name'  => $data['last_name'],
        'email'      => $isEmail ? $identity : null,
        'phone'      => !$isEmail ? $this->formatPhone($identity) : null,
        'password'   => $data['password'],
    ];

    $user = $this->createUser($userData, $roles);

    event(new UserRegistered($user));


    DeleteUnverifiedUsersJob::dispatch($user->id)
    ->delay(now()->addMinutes(1));

    return $user;
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
public function login(array $data): array
    {
        $identity = $data['identity'];
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);

        $user = $isEmail
            ? User::where('email', $identity)->first()
            : User::where('phone', $this->formatPhone($identity))->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new Exception('بيانات الاعتماد غير صحيحة.', 401);
        }

        $user->tokens()->delete();

        $isVerified = !is_null($user->phone_verified_at) || !is_null($user->email_verified_at);

        return [
            'user'        => $user,
            'is_verified' => $isVerified,
        ];
    }}