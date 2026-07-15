<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    /**
     * لازم يكون عنده providerProfile (شركة) أصلاً حتى يشوف قائمة خدماته
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->providerProfile;
    }

    /**
     * مالك الخدمة أو admin
     */
    public function view(User $user, Service $service): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->providerProfile?->id === $service->company_id;
    }

    /**
     * لازم يكون عنده providerProfile حتى يضيف خدمة (نوع الشركة يتفحص بالـ middleware provider_type:company)
     */
    public function create(User $user): bool
    {
        return (bool) $user->providerProfile;
    }

    /**
     * مالك الخدمة أو admin
     */
    public function update(User $user, Service $service): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->providerProfile?->id === $service->company_id;
    }

    /**
     * مالك الخدمة أو admin
     */
    public function delete(User $user, Service $service): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->providerProfile?->id === $service->company_id;
    }
}