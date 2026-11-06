<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        // TEMUAN AUDIT 8 ITEM (2026-11-05, item 1): sama seperti JobPolicy —
        // trip tenant lain tidak boleh dilihat walau binding lolos.
        if ((int) $trip->company_id !== (int) $user->company_id) {
            return false;
        }

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

        // Field hanya boleh melihat trip dari job yang ditugaskan kepadanya.
        // (BUGFIX 2026-11-04: Trip tidak punya relasi fields() — panggilan
        // lama $trip->fields() melempar BadMethodCallException untuk user
        // Field murni. Penugasan Field tercatat di Job, bukan di Trip.)
        if ($user->hasRole(Role::FIELD)) {
            return $trip->job->fields()->where('field_user_id', $user->id)->exists();
        }

        return false;
    }
}
