<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\ContainerDeposit;
use App\Models\Importir;
use App\Models\Job;
use App\Models\JobCost;
use App\Models\ShippingLine;
use App\Models\TitipanDana;
use App\Models\UangMuka;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class LedgerDepositUangMukaTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $finance;

    private User $ops;

    private Importir $importir;

    private Job $job;

    private Container $c1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);

        $this->c1 = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU1111111',
            'size' => '20',
            'type' => 'Dry',
            'gross_weight_kg' => '20000',
        ]);
    }

    #[Test]
    public function freight_prepaid_importir_tidak_masuk_tagihan_tetap_masuk_cif(): void
    {
        // Ocean freight prepaid yang dibayar langsung oleh importir
        $cost = JobCost::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'cost_type' => JobCost::TYPE_OCEAN_FREIGHT,
            'payer_flag' => JobCost::PAYER_IMPORTIR,
            'currency_code' => 'USD',
            'amount_valas' => 2500.0000,
            'kurs_internal_rate' => 1,
            'amount_idr' => 39500000.0000,
            'status' => JobCost::STATUS_AKTUAL,
            'created_by_user_id' => $this->ops->id,
        ]);

        // Ocean freight collect yang dibayar oleh PPJK
        $cost2 = JobCost::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'cost_type' => JobCost::TYPE_OCEAN_FREIGHT,
            'payer_flag' => JobCost::PAYER_PPJK,
            'currency_code' => 'USD',
            'amount_valas' => 2500.0000,
            'kurs_internal_rate' => 1,
            'amount_idr' => 39500000.0000,
            'status' => JobCost::STATUS_AKTUAL,
            'created_by_user_id' => $this->ops->id,
        ]);

        $this->assertFalse($cost->isBillable(), 'Prepaid-importir TIDAK ditagih');
        $this->assertTrue($cost2->isBillable(), 'Collect-PPJK ditagih');
    }

    #[Test]
    public function deposit_kontainer_hangus_sebagian_posting_ke_ledger(): void
    {
        $deposit = ContainerDeposit::create([
            'company_id' => $this->company->id,
            'container_id' => $this->c1->id,
            'job_id' => $this->job->id,
            'amount_usd' => 500.0000,
            'amount_idr' => 7900000.0000,
            'paid_date' => '2026-11-01',
            'status' => ContainerDeposit::STATUS_HANGUS_SEBAGIAN,
            'forfeit_amount_usd' => 200.0000,
            'forfeit_amount_idr' => 3160000.0000,
            'debit_note_number' => 'DN-001',
        ]);

        $jobCost = $deposit->postForfeitToLedger($this->finance);

        $this->assertNotNull($jobCost);
        $this->assertEquals(JobCost::TYPE_DEPOSIT_FORFEIT, $jobCost->cost_type);
        $this->assertEquals('DN-001', $jobCost->bukti_number);
        $this->assertEquals(200.0000, (float) $jobCost->amount_valas);
    }

    #[Test]
    public function deposit_kontainer_tanpa_hangus_tidak_posting_ke_ledger(): void
    {
        $deposit = ContainerDeposit::create([
            'company_id' => $this->company->id,
            'container_id' => $this->c1->id,
            'job_id' => $this->job->id,
            'amount_usd' => 500.0000,
            'status' => ContainerDeposit::STATUS_KEMBALI_PENUH,
            'forfeit_amount_usd' => 0,
        ]);

        $jobCost = $deposit->postForfeitToLedger($this->finance);
        $this->assertNull($jobCost);
    }

    #[Test]
    public function uang_muka_dari_estimasi_kalkulator_dan_biaya(): void
    {
        $uangMuka = UangMuka::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'amount' => 50000000.0000,
            'received_date' => '2026-11-01',
            'method' => UangMuka::METHOD_TRANSFER,
            'reference' => 'TRF-001',
            'is_settled' => false,
            'created_by_user_id' => $this->finance->id,
        ]);

        $this->assertFalse($uangMuka->is_settled);

        // Settlement
        $uangMuka->settle(30000000.0000);
        $uangMuka->refresh();
        $this->assertFalse($uangMuka->is_settled);
        $this->assertEquals(30000000.0000, (float) $uangMuka->settled_amount);

        // Full settlement
        $uangMuka->settle(20000000.0000);
        $uangMuka->refresh();
        $this->assertTrue($uangMuka->is_settled);
    }

    #[Test]
    public function titipan_dana_mencatat_saldo_dan_riwayat_alokasi(): void
    {
        $titipan = TitipanDana::create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'received_date' => '2026-11-01',
            'amount' => 100000000.0000,
            'method' => TitipanDana::METHOD_TRANSFER,
            'reference' => 'TRF-002',
            'created_by_user_id' => $this->finance->id,
        ]);

        $this->assertEquals(100000000.0000, $titipan->sisaSaldo());
    }

    #[Test]
    public function deposit_tidak_sama_dengan_titipan_dana(): void
    {
        // Deposit adalah per kontainer, Titipan Dana adalah per importir
        $deposit = ContainerDeposit::create([
            'company_id' => $this->company->id,
            'container_id' => $this->c1->id,
            'job_id' => $this->job->id,
            'amount_usd' => 500,
            'status' => ContainerDeposit::STATUS_DITAHAN,
        ]);

        $titipan = TitipanDana::create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'received_date' => '2026-11-01',
            'amount' => 100000000,
        ]);

        // Mereka adalah entitas yang berbeda
        $this->assertNotInstanceOf(TitipanDana::class, $deposit);
        $this->assertNotInstanceOf(ContainerDeposit::class, $titipan);
        $this->assertDatabaseCount('container_deposits', 1);
        $this->assertDatabaseCount('titipan_danas', 1);
    }
}
