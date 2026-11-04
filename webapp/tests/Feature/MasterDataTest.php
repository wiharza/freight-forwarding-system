<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Importir;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private User $admin;

    private User $cs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $company = Company::factory()->create();

        $this->admin = User::factory()->create(['company_id' => $company->id]);
        $this->admin->roles()->sync([$this->roleId('admin')]);

        $this->cs = User::factory()->create(['company_id' => $company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);
    }

    #[Test]
    public function admin_dapat_melihat_dan_membuat_importir(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.master.importirs'));
        $response->assertOk();

        $post = $this->actingAs($this->admin)->post(route('admin.master.importirs.store'), [
            'name' => 'PT Maju Bersama',
            'npwp' => '01.234.567.8-012.000',
            'nib' => '1234567890123',
            'status_api' => 'API-U',
            'phone' => '021-5551234',
        ]);

        $post->assertSessionHasNoErrors();
        $this->assertDatabaseHas('importirs', [
            'name' => 'PT Maju Bersama',
            'npwp' => '01.234.567.8-012.000',
            'status_api' => 'API-U',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'importir.created',
        ]);
    }

    #[Test]
    public function dedup_npwp_menolak_npwp_ganda_pada_company_yang_sama(): void
    {
        Importir::factory()->create([
            'company_id' => $this->admin->company_id,
            'npwp' => '01.234.567.8-012.000',
            'name' => 'PT Asli',
        ]);

        // Coba masukkan nama berbeda dengan NPWP sama
        $response = $this->actingAs($this->admin)->post(route('admin.master.importirs.store'), [
            'name' => 'PT Duplikat Ejaan Beda',
            'npwp' => '01.234.567.8-012.000',
            'status_api' => 'API-U',
        ]);

        $response->assertSessionHasErrors(['npwp']);
        $this->assertDatabaseMissing('importirs', [
            'name' => 'PT Duplikat Ejaan Beda',
        ]);
    }

    #[Test]
    public function cs_ditolak_mengakses_dan_membuat_master_data(): void
    {
        $response = $this->actingAs($this->cs)->get(route('admin.master.importirs'));
        $response->assertForbidden();

        $post = $this->actingAs($this->cs)->post(route('admin.master.importirs.store'), [
            'name' => 'PT Tidak Boleh',
            'npwp' => '99.999.999.9-999.000',
            'status_api' => 'API-U',
        ]);
        $post->assertForbidden();
    }

    #[Test]
    public function master_vendor_mendukung_tipe_eksternal_dan_internal(): void
    {
        $this->actingAs($this->admin)->post(route('admin.master.vendors.store'), [
            'name' => 'PT Armada Mandiri',
            'vendor_type' => 'internal',
            'contact_person' => 'Budi',
        ]);

        $this->assertDatabaseHas('vendors', [
            'name' => 'PT Armada Mandiri',
            'vendor_type' => Vendor::TYPE_INTERNAL,
        ]);
    }

    #[Test]
    public function admin_dapat_mengubah_status_aktif_mata_uang(): void
    {
        $currency = Currency::factory()->create([
            'company_id' => $this->admin->company_id,
            'code' => 'CNY',
            'name' => 'Chinese Yuan',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.master.currencies.update', $currency->id), [
            'is_active' => true,
            'effective_date' => '2026-11-03',
        ]);

        $response->assertSessionHasNoErrors();
        $currency->refresh();

        $this->assertTrue($currency->is_active);
        $this->assertEquals('2026-11-03', $currency->effective_date->toDateString());
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'currency.updated',
            'object_id' => $currency->id,
        ]);
    }
}
