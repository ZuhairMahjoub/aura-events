<?php
namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view listings');
    }

    public function view(User $user, Listing $listing): bool
    {
        return $user->hasPermissionTo('view listings');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create listings');
    }

    public function update(User $user, Listing $listing): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

return $user->hasPermissionTo('update listings') && $user->providerProfile?->id === $listing->provider_id;    }

    public function delete(User $user, Listing $listing): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

return $user->hasPermissionTo('delete listings') && $user->providerProfile?->id === $listing->provider_id;
    }
}