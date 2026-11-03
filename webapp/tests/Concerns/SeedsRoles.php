<?php

namespace Tests\Concerns;

use App\Models\Role;

trait SeedsRoles
{
    /**
     * 6 peran kanonik §2.5 — idempoten, dipakai semua test RBAC.
     */
    protected function seedRoles(): void
    {
        foreach ([
            Role::ADMIN => 'Admin',
            Role::CS => 'Customer Service',
            Role::CUSTOMS_SPECIALIST => 'Customs Specialist',
            Role::OPS => 'Ops',
            Role::FIELD => 'Field',
            Role::FINANCE => 'Finance',
        ] as $slug => $name) {
            Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }

    protected function roleId(string $slug): int
    {
        return Role::query()->where('slug', $slug)->value('id');
    }
}
