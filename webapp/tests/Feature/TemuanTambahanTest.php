<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContainerType;
use App\Models\CustomsDeclaration;
use App\Models\Importir;
use App\Models\InternalExchangeRate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Location;
use App\Models\MfnTariff;
use App\Models\Payment;
use App\Models\Pph22Rule;
use App\Models\ShippingLine;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PibCalculator;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes regresi untuk 6 temuan tambahan review kode 2026-11-04
 * (perbaiki-temuan-tambahan, master 3047e32).
 */
class TemuanTambahanTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private Company $company2;

    private User $cs;

    private User $ops;

    private User $finance;

    private User $customs;

    private User $customs2;

    private Importir $importir;

    private Importir $importir2;

    private Importir $importirB;

    private ShippingLine $line;

    private Location $pol;

    private Location $pod;

    private Job $job;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        $this->company2 = Company::factory()->create();

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->customs = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs->roles()->sync([$this->roleId('customs_specialist')]);

        $this->customs2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs2->roles()->sync([$this->roleId('customs_specialist')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        // Importir perusahaan lain (company 2)
        $this->importir2 = Importir::factory()->create(['company_id' => $this->company2->id]);
        // Importir kedua dalam company yang sama (untuk temuan #3)
        $this->importirB = Importir::factory()->create(['company_id' => $this->company->id]);

        $this->line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $this->pol = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);
        $this->pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'pol_id' => $this->pol->id,
            'pod_id' => $this->pod->id,
        ]);
        $this->job->containers()->create([
            'container_number' => 'MSKU1234565',
            'size' => "40'",
            'type' => 'High Cube',
            'seal_number' => 'SEAL-001',
            'gross_weight_kg' => 25000,
        ]);

        $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);

        ContainerType::query()->create([
            'company_id' => $this->company->id,
            'size' => "40'",
            'type' => 'High Cube',
        ]);

        app(CompanyContext::class)->set($this->company->id);
    }

    // ------------------------------------------------------------------
    // Temuan #1 — FK lintas-tenant pada Job & Trip
    // ------------------------------------------------------------------

    #[Test]
    public function job_ditolak_bila_importir_milik_company_lain(): void
    {
        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $this->jobPayload([
            'importir_id' => $this->importir2->id,
        ]));

        $response->assertSessionHasErrors(['importir_id']);
        $this->assertDatabaseMissing('jobs', ['bl_number' => 'BLSKU123456']);
    }

    #[Test]
    public function job_ditolak_bila_pod_milik_company_lain(): void
    {
        $podLain = Location::factory()->create(['company_id' => $this->company2->id, 'type' => Location::TYPE_PORT]);

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $this->jobPayload([
            'pod_id' => $podLain->id,
        ]));

        $response->assertSessionHasErrors(['pod_id']);
        $this->assertDatabaseMissing('jobs', ['bl_number' => 'BLSKU123456']);
    }

    #[Test]
    public function job_diterima_bila_semua_fk_milik_company_sendiri(): void
    {
        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $this->jobPayload());

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jobs', ['bl_number' => 'BLSKU123456']);
    }

    #[Test]
    public function trip_ditolak_bila_vendor_milik_company_lain(): void
    {
        $vendorLain = Vendor::factory()->create(['company_id' => $this->company2->id]);

        $response = $this->actingAs($this->ops)->post(route('trips.store'), $this->tripPayload([
            'vendor_id' => $vendorLain->id,
        ]));

        $response->assertSessionHasErrors(['vendor_id']);
        $this->assertDatabaseMissing('trips', ['trip_number' => 'TRP-TEST-001']);
    }

    #[Test]
    public function trip_ditolak_bila_job_milik_company_lain(): void
    {
        $jobLain = Job::factory()->create([
            'company_id' => $this->company2->id,
            'importir_id' => $this->importir2->id,
        ]);

        $response = $this->actingAs($this->ops)->post(route('trips.store'), $this->tripPayload([
            'job_id' => $jobLain->id,
        ]));

        $response->assertSessionHasErrors(['job_id']);
        $this->assertDatabaseMissing('trips', ['trip_number' => 'TRP-TEST-001']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buatDrafPib(Job $job, string $hsCode): CustomsDeclaration
    {
        $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => $hsCode,
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'insurance_valas' => '10',
                'kurs_pajak_rate' => '16000',
            ]],
        ])->assertSessionHasNoErrors();

        return CustomsDeclaration::query()->where('job_id', $job->id)->latest('version')->firstOrFail();
    }

    private function jobPayload(array $overrides = []): array
    {
        return array_merge([
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'bl_number' => 'BLSKU123456',
            'bl_date' => '2026-11-03',
            'pol_id' => $this->pol->id,
            'pod_id' => $this->pod->id,
            'containers' => [[
                'container_number' => 'MSKU1234565',
                'size' => "40'",
                'type' => 'High Cube',
                'seal_number' => 'SEAL-001',
                'gross_weight_kg' => '25000',
            ]],
        ], $overrides);
    }

    private function tripPayload(array $overrides = []): array
    {
        return array_merge([
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
            'trip_number' => 'TRP-TEST-001',
            'scheduled_date' => '2026-11-05',
            'container_ids' => [$this->job->containers()->firstOrFail()->id],
        ], $overrides);
    }
}
