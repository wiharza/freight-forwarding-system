<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\Role;
use App\Models\User;

class JobPolicy
{
    /**
     * Tentukan apakah user boleh melihat job ini (§2.5).
     */
    public function view(User $user, Job $job): bool
    {
        // Peran non-Field boleh melihat semua job di company-nya
        if ($user->hasAnyRole([
            Role::ADMIN,
            Role::CS,
            Role::CUSTOMS_SPECIALIST,
            Role::OPS,
            Role::FINANCE,
        ])) {
            return true;
        }

        // Field murni hanya boleh melihat job yang ditugaskan kepadanya
        if ($user->hasRole(Role::FIELD)) {
            return $job->fields()->where('field_user_id', $user->id)->exists();
        }

        return false;
    }
}
