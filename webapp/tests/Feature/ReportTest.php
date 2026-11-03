<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Importir;
use App\Models\Job;
use App\Models\ShippingLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;
    private User $finance;
    private User $ops;
    private User $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(\App\Support\CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->field = User::factory()->create(['company_id' => $this->company->id]);
        $this->field->roles()->sync([$this->roleId('field')]);
    }

    #[Test]
    public function finance_bisa_melihat_laporan_eksposur(): void
    {
        $response = $this->actingAs($this->finance)->get(route('reports.exposure'));
        $response->assertOk();
    }

    #[Test]
    public function ops_tidak_bisa_melihat_laporan_eksposur(): void
    {
        $response = $this->actingAs($this->ops)->get(route('reports.exposure'));
        $response->assertForbidden();
    }

    #[Test]
    public function ops_bisa_melihat_laporan_daftar_job(): void
    {
        $response = $this->actingAs($this->ops)->get(route('reports.jobs'));
        $response->assertOk();
    }

    #[Test]
    public function field_tidak_bisa_melihat_laporan_daftar_job(): void
    {
        $response = $this->actingAs($this->field)->get(route('reports.jobs'));
        $response->assertForbidden();
    }

    #[Test]
    public function finance_bisa_melihat_laporan_aging(): void
    {
        $response = $this->actingAs($this->finance)->get(route('reports.aging'));
        $response->assertOk();
    }

    #[Test]
    public function finance_bisa_melihat_laporan_demurrage(): void
    {
        $response = $this->actingAs($this->finance)->get(route('reports.demurrage'));
        $response->assertOk();
    }

    #[Test]
    public function finance_bisa_melihat_laporan_deposit(): void
    {
        $response = $this->actingAs($this->finance)->get(route('reports.deposit'));
        $response->assertOk();
    }
}