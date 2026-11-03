<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        // Peran non-Field boleh melihat semua job/trip di company-nya
        if ($user->hasAnyRole([
            Role::ADMIN,
            Role::CS,
            Role::CUSTOMS_SPECIALIST,
            Role::OPS,
            Role::FINANCE,
        ])) {
            return true;
        }

        // Field hanya boleh melihat job/trip yang ditugaskan kepadanya
        if ($user->hasRole(Role::FIELD)) {
            return $trip->fields()->where('field_user_id', $user->id)->exists();
        }

        return false;
    }
}