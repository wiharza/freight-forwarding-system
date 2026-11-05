<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomsDeclaration;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobCost;
use App\Models\Location;
use App\Models\ShippingLine;
use App\Models\TaxExchangeRate;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes reproduksi + regresi temuan audit lanjutan 2026-11-05, item 2:
 * Job yang sama tidak boleh ditagih ulang untuk Blok A dan Blok C.
 */
class AuditLanjutanInvoiceTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private Company $company2;

    private User $finance;

    private User $customs;

    private User $customs2;

    private Importir $importir;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        $this->company2 = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->customs = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs->roles()->sync([$this->roleId('customs_specialist')]);

        $this->customs2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs2->roles()->sync([$this->roleId('customs_specialist')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $pol = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);
        $pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
            'pol_id' => $pol->id,
            'pod_id' => $pod->id,
        ]);

        // Kurs Pajak USD yang berlaku saat test berjalan (jendela lebar —
        // KMK riil mingguan, tapi test butuh baris yang pasti mencakup now()).
        TaxExchangeRate::query()->create([
            'company_id' => $this->company->id,
            'kmk_number' => 'KMK-TEST-16000',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'currency_code' => 'USD',
            'rate_to_idr' => '16000',
            'source' => 'test',
        ]);
    }

    // ------------------------------------------------------------------
    // Temuan 2 — invoice kedua tidak boleh menagih ulang Blok A dan C
    // ------------------------------------------------------------------

    private function buatSnapshotDisetujui(): CustomsDeclaration
    {
        $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => '8471302000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'insurance_valas' => '10',
                'kurs_pajak_rate' => '16000',
            ]],
        ])->assertSessionHasNoErrors();

        $declaration = CustomsDeclaration::query()->where('job_id', $this->job->id)->latest('version')->firstOrFail();

        $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        )->assertSessionHasNoErrors();

        return $declaration->fresh();
    }

    private function buatInvoice(array $overrides = [])
    {
        return $this->actingAs($this->finance)->post(route('finance.invoices.store', ['job' => $this->job->id]), array_merge([
            'invoice_date' => '2026-11-05',
            'blok_c_service_fee' => 100000,
            'blok_c_ppn_rate' => '0.12',
        ], $overrides));
    }

    #[Test]
    public function invoice_kedua_tidak_menagih_ulang_blok_a(): void
    {
        $snapshot = $this->buatSnapshotDisetujui();
        $this->assertGreaterThan(0, (float) $snapshot->total_pungutan);

        $this->buatInvoice()->assertSessionHasNoErrors();
        $first = Invoice::query()->where('job_id', $this->job->id)->firstOrFail();
        $this->assertEqualsWithDelta((float) $snapshot->total_pungutan, (float) $first->blok_a_total, 0.01);

        // Invoice susulan (biaya Blok B baru = 0, jasa 0): Blok A harus 0.
        $this->buatInvoice(['blok_c_service_fee' => 0])->assertSessionHasNoErrors();
        $second = Invoice::query()->where('job_id', $this->job->id)->orderByDesc('id')->firstOrFail();

        $this->assertSame('0.0000', (string) $second->blok_a_total, 'Blok A sudah ditagih pada invoice pertama — tidak boleh ditagih ulang');
        $this->assertNull($second->snapshot_pib_id);
        $this->assertEqualsWithDelta((float) $snapshot->total_pungutan, (float) $first->fresh()->blok_a_total, 0.01);
    }

    #[Test]
    public function invoice_kedua_dengan_jasa_ditolak(): void
    {
        $this->buatSnapshotDisetujui();
        $this->buatInvoice()->assertSessionHasNoErrors();

        $response = $this->buatInvoice(['blok_c_service_fee' => 100000]);

        $response->assertSessionHasErrors(['blok_c_service_fee']);
        $this->assertSame(1, Invoice::query()->where('job_id', $this->job->id)->count());
    }

    #[Test]
    public function invoice_susulan_blok_b_saja_tetap_bisa(): void
    {
        $this->buatSnapshotDisetujui();
        $this->buatInvoice()->assertSessionHasNoErrors();

        // Biaya aktual baru datang setelah invoice pertama.
        JobCost::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'cost_type' => JobCost::TYPE_THC,
            'payer_flag' => JobCost::PAYER_PPJK,
            'currency_code' => 'IDR',
            'amount_valas' => 250000,
            'kurs_internal_rate' => 1,
            'amount_idr' => 250000,
            'status' => JobCost::STATUS_AKTUAL,
            'created_by_user_id' => $this->customs->id,
        ]);

        $this->buatInvoice(['blok_c_service_fee' => 0])->assertSessionHasNoErrors();
        $second = Invoice::query()->where('job_id', $this->job->id)->orderByDesc('id')->firstOrFail();

        $this->assertSame('250000.0000', (string) $second->blok_b_total);
        $this->assertSame('0.0000', (string) $second->blok_a_total);
        $this->assertSame('0.0000', (string) $second->blok_c_service_fee);
    }

    #[Test]
    public function invoice_pertama_yang_dibatalkan_tidak_memblokir_penagihan_ulang(): void
    {
        $this->buatSnapshotDisetujui();
        $this->buatInvoice()->assertSessionHasNoErrors();

        $first = Invoice::query()->where('job_id', $this->job->id)->firstOrFail();
        $first->update(['status' => Invoice::STATUS_CANCELLED]);

        // Setelah pembatalan, Blok A dan jasa boleh ditagih lagi.
        $this->buatInvoice()->assertSessionHasNoErrors();
        $second = Invoice::query()->where('job_id', $this->job->id)->orderByDesc('id')->firstOrFail();
        $this->assertGreaterThan(0, (float) $second->blok_a_total);
        $this->assertSame('100000.0000', (string) $second->blok_c_service_fee);
    }
}
