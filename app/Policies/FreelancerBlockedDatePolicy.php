<?php

namespace App\Policies;

use App\Models\FreelancerBlockedDate;
use App\Models\User;

class FreelancerBlockedDatePolicy
{
    /**
     * لازم يكون عنده providerProfile من نوع freelancer حتى يشوف تواريخه
     */
    public function viewAny(User $user): bool
    {
        return $user->providerProfile?->provider_type === 'freelancer';
    }

    /**
     * لازم يكون عنده providerProfile من نوع freelancer حتى يضيف تاريخ محجوز
     */
    public function create(User $user): bool
    {
        return $user->providerProfile?->provider_type === 'freelancer';
    }

    /**
     * مالك التاريخ المحجوز أو admin
     */
    public function delete(User $user, FreelancerBlockedDate $blockedDate): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->providerProfile?->id === $blockedDate->freelancer_id;
    }
}