<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private function admin(): User
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $user->roles()->sync([$this->roleId('admin')]);

        return $user;
    }

    #[Test]
    public function admin_bisa_membuka_daftar_user(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/users');

        $response->assertOk();
    }

    #[Test]
    public function admin_bisa_membuat_user_dengan_peran(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Nur Field',
            'email' => 'field@example.local',
            'password' => 'rahasia-kuat-123',
            'roles' => [$this->roleId('field')],
        ]);

        $response->assertRedirect('/admin/users');

        $user = User::query()->where('email', 'field@example.local')->firstOrFail();
        $this->assertTrue($user->hasRole('field'));
        $this->assertTrue($user->hasRole('admin') === false);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'user.created',
            'object_id' => $user->id,
        ]);
    }

    #[Test]
    public function cs_tidak_bisa_mengakses_manajemen_user(): void
    {
        $this->seedRoles();
        $cs = User::factory()->create();
        $cs->roles()->sync([$this->roleId('cs')]);

        $response = $this->actingAs($cs)->get('/admin/users');

        $response->assertForbidden();
    }

    #[Test]
    public function admin_tidak_mewarisi_fitur_operasional_saats_membuat_user(): void
    {
        // Admin membuat user Field — tapi dirinya TIDAK ikut dapat fitur field
        // (Admin bukan superuser; peran melekat per user, ADR 0006).
        $admin = $this->admin();

        $this->assertTrue(\Gate::forUser($admin)->allows('user_management'));
        $this->assertTrue(\Gate::forUser($admin)->denies('input_milestone'));
        $this->assertTrue(\Gate::forUser($admin)->denies('invoice'));
    }
}
