<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\Role;
use App\Models\User;

class JobPolicy
{
    /**
     * Tentukan apakah user boleh melihat job ini (§2.5).
     *
     * TEMUAN AUDIT 8 ITEM (2026-11-05, item 1 — Tiket 02): JobPolicy
     * sebelumnya tidak mencocokkan company_id, sehingga user tenant lain
     * yang lolos route model binding bisa melihat Job ini. Company harus
     * sama — ini pertahanan berlapis di samping global scope.
     */
    public function view(User $user, Job $job): bool
    {
        if ((int) $job->company_id !== (int) $user->company_id) {
            return false;
        }

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

    /**
     * Tentukan apakah user boleh melihat daftar job (§2.5).
     */
    public function viewAny(User $user): bool
    {
        // Peran non-Field boleh melihat daftar semua job di company-nya
        if ($user->hasAnyRole([
            Role::ADMIN,
            Role::CS,
            Role::CUSTOMS_SPECIALIST,
            Role::OPS,
            Role::FINANCE,
        ])) {
            return true;
        }

        // Field boleh melihat daftar job yang ditugaskan kepadanya
        return $user->hasRole(Role::FIELD);
    }
}
