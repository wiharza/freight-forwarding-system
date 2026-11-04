<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\Currency;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobCost;
use App\Models\JobDocument;
use App\Models\Location;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\ShippingLine;
use App\Models\SuratJalan;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vendor;
use App\Support\CompanyContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Test regresi untuk lima item hardening yang disetujui user 2026-11-04
 * (di luar bug workflow verifikasi VPS):
 *
 *  1. Otorisasi Job/kontainer — container harus milik Job yang sama;
 *     updateProblemFlag/verifyDocument/endpoint Trip memakai Gate view Job.
 *  2. Invoice rawan salah tagih — SATU definisi billable (Job::billableCosts)
 *     untuk preview + submit; biaya ditandai invoice_id/invoiced_at.
 *  3. Race alokasi — saldo + tulis dalam SATU transaksi + lockForUpdate.
 *  4. Dokumen di storage publik — pindah ke disk privat + endpoint download
 *     ber-Gate (termasuk bukti milestone + POD Surat Jalan).
 *  5. Seeder + CI — tenant demo dikunci eksplisit; CI check-only + typecheck
 *     (bagian CI diverifikasi dengan menjalankan perintahnya secara lokal,
 *     bukan oleh test ini).
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $finance;

    private User $cs;

    private User $ops;

    private User $field;

    private User $fieldLain;

    private Importir $importir;

    private Job $job;

    private Job $jobLain;

    private Container $c1;

    private Container $cLain;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->field = User::factory()->create(['company_id' => $this->company->id]);
        $this->field->roles()->sync([$this->roleId('field')]);

        // Field murni yang TIDAK ditugaskan ke Job mana pun.
        $this->fieldLain = User::factory()->create(['company_id' => $this->company->id]);
        $this->fieldLain->roles()->sync([$this->roleId('field')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);
        $this->c1 = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU1234567',
            'size' => '40',
            'type' => 'High Cube',
            'gross_weight_kg' => '21500',
        ]);
        $this->job->assignField($this->field, $this->ops);

        $this->jobLain = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);
        $this->cLain = $this->jobLain->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'TCLU7654321',
            'size' => '20',
            'type' => 'Dry',
            'gross_weight_kg' => '18000',
        ]);

        $this->vendor = Vendor::factory()->create([
            'company_id' => $this->company->id,
            'vendor_type' => Vendor::TYPE_EKSTERNAL,
        ]);
    }

    private function buatBiaya(string $type, string $payer, string $status, float $amount): JobCost
    {
        return JobCost::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'cost_type' => $type,
            'payer_flag' => $payer,
            'currency_code' => 'IDR',
            'amount_valas' => $amount,
            'kurs_internal_rate' => 1,
            'amount_idr' => $amount,
            'status' => $status,
            'created_by_user_id' => $this->ops->id,
        ]);
    }

    private function buatInvoice(float $serviceFee = 100000): Invoice
    {
        $response = $this->actingAs($this->finance)->post(route('finance.invoices.store', ['job' => $this->job->id]), [
            'invoice_date' => '2026-11-04',
            'blok_c_service_fee' => $serviceFee,
            'blok_c_ppn_rate' => '0.12',
        ]);
        $response->assertSessionHasNoErrors();

        return Invoice::query()->orderByDesc('id')->firstOrFail();
    }

    // ------------------------------------------------------------------
    // 1. Otorisasi Job/kontainer
    // ------------------------------------------------------------------

    #[Test]
    public function milestone_dengan_container_milik_job_lain_ditolak(): void
    {
        // container_id tunggal milik Job lain
        $response = $this->actingAs($this->field)->post(route('jobs.milestones.store', $this->job->id), [
            'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
            'occurred_at' => now()->addHours(5)->format('Y-m-d H:i:s'),
            'container_id' => $this->cLain->id,
        ]);
        $response->assertSessionHasErrors('container_id');
        $this->assertDatabaseMissing('milestones', [
            'job_id' => $this->job->id,
            'container_id' => $this->cLain->id,
        ]);

        // container_ids (batch) milik Job lain
        $response = $this->actingAs($this->field)->post(route('jobs.milestones.store', $this->job->id), [
            'milestone_type' => Milestone::TYPE_DISCHARGE,
            'occurred_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'container_ids' => [$this->cLain->id],
        ]);
        $response->assertSessionHasErrors('container_ids.0');
        $this->assertDatabaseMissing('milestones', [
            'job_id' => $this->job->id,
            'milestone_type' => Milestone::TYPE_DISCHARGE,
        ]);
    }

    #[Test]
    public function problem_flag_oleh_field_tanpa_akses_job_ditolak(): void
    {
        // Field yang tidak ditugaskan ke Job ini -> 403 (sebelumnya: tanpa
        // Gate sama sekali, siapa pun bisa mengubah problem flag Job mana pun).
        $response = $this->actingAs($this->fieldLain)->patch(route('jobs.problem-flag', $this->job->id), [
            'problem_flag' => 'Hold',
            'reason' => 'Coba-coba',
        ]);
        $response->assertForbidden();
        $this->assertNull($this->job->fresh()->problem_flag);

        // Kontrol positif: Field yang ditugaskan BOLEH (lolos Gate view Job).
        $response = $this->actingAs($this->field)->patch(route('jobs.problem-flag', $this->job->id), [
            'problem_flag' => 'Hold',
            'reason' => 'Dokumen belum lengkap',
        ]);
        $response->assertRedirect();
        $this->assertSame('Hold', $this->job->fresh()->problem_flag);
    }

    #[Test]
    public function trip_policy_view_field_hanya_job_yang_ditugaskan(): void
    {
        $trip = Trip::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
        ]);

        // Sebelum perbaikan, cabang Field memanggil $trip->fields() yang
        // TIDAK ADA di model Trip -> BadMethodCallException.
        $this->assertTrue($this->field->can('view', $trip), 'Field yang ditugaskan harus bisa melihat trip');
        $this->assertFalse($this->fieldLain->can('view', $trip), 'Field yang tidak ditugaskan tidak boleh melihat trip');
        $this->assertTrue($this->ops->can('view', $trip), 'Ops boleh melihat semua trip');
    }

    #[Test]
    public function trip_store_dengan_container_job_lain_ditolak(): void
    {
        $response = $this->actingAs($this->ops)->post(route('trips.store'), [
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
            'trip_number' => 'TRP-HARD-001',
            'scheduled_date' => '2026-11-10',
            'container_ids' => [$this->cLain->id],
        ]);
        $response->assertSessionHasErrors('container_ids.0');
        $this->assertDatabaseMissing('trips', ['trip_number' => 'TRP-HARD-001']);
    }

    // ------------------------------------------------------------------
    // 2. Invoice rawan salah tagih
    // ------------------------------------------------------------------

    #[Test]
    public function biaya_sudah_ditagih_tidak_masuk_invoice_kedua(): void
    {
        $thc = $this->buatBiaya(JobCost::TYPE_THC, JobCost::PAYER_PPJK, JobCost::STATUS_AKTUAL, 2500000);
        $doFee = $this->buatBiaya(JobCost::TYPE_DO_FEE, JobCost::PAYER_PPJK, JobCost::STATUS_AKTUAL, 1000000);
        // Bukan billable: masih estimasi, dan deposit-forfeit (ledger sendiri).
        $estimasi = $this->buatBiaya(JobCost::TYPE_THC, JobCost::PAYER_PPJK, JobCost::STATUS_ESTIMASI, 9000000);
        $forfeit = $this->buatBiaya(JobCost::TYPE_DEPOSIT_FORFEIT, JobCost::PAYER_PPJK, JobCost::STATUS_AKTUAL, 3000000);

        $invoice1 = $this->buatInvoice();
        $this->assertEquals(3500000, (float) $invoice1->fresh()->blok_b_total);
        $this->assertSame($invoice1->id, $thc->fresh()->invoice_id);
        $this->assertNotNull($thc->fresh()->invoiced_at);
        $this->assertSame($invoice1->id, $doFee->fresh()->invoice_id);
        $this->assertNull($estimasi->fresh()->invoice_id);
        $this->assertNull($forfeit->fresh()->invoice_id);

        // Invoice kedua untuk Job yang sama: tidak ada lagi biaya billable.
        $invoice2 = $this->buatInvoice();
        $this->assertEquals(0, (float) $invoice2->fresh()->blok_b_total);
        $this->assertEquals(0, $invoice2->items()->where('blok', 'b')->count());
        $this->assertFalse($invoice2->items()->where('job_cost_id', $thc->id)->exists());
        $this->assertFalse($invoice2->items()->where('job_cost_id', $doFee->id)->exists());

        // Preview create() juga memakai definisi yang sama -> kosong.
        $this->actingAs($this->finance)
            ->get(route('finance.invoices.create', $this->job->id))
            ->assertInertia(fn ($page) => $page->has('billableCosts', 0));
    }

    #[Test]
    public function ocean_freight_ppjk_ditagih_prepaid_importir_tidak(): void
    {
        // Spesifikasi §6.4: ocean freight punya penanda pembayar — prepaid
        // (dibayar langsung importir) tidak ditagih; collect (dibayar PPJK)
        // masuk tagihan sebagai pass-through. Penanda itu adalah payer_flag;
        // pengecualian cost_type ocean_freight yang lama membuat freight
        // yang dibayar PPJK hilang dari tagihan.
        $collect = $this->buatBiaya(JobCost::TYPE_OCEAN_FREIGHT, JobCost::PAYER_PPJK, JobCost::STATUS_AKTUAL, 5000000);
        $prepaid = $this->buatBiaya(JobCost::TYPE_OCEAN_FREIGHT, JobCost::PAYER_IMPORTIR, JobCost::STATUS_AKTUAL, 7000000);

        $invoice = $this->buatInvoice();
        $this->assertEquals(5000000, (float) $invoice->fresh()->blok_b_total);
        $this->assertSame($invoice->id, $collect->fresh()->invoice_id);
        $this->assertNull($prepaid->fresh()->invoice_id);
    }

    // ------------------------------------------------------------------
    // 3. Race alokasi (sekuensial — batas yang bisa dibuktikan di SQLite)
    // ------------------------------------------------------------------

    #[Test]
    public function alokasi_berturut_turut_tidak_melebihi_pembayaran(): void
    {
        // CATATAN: lockForUpdate hanya bisa dibuktikan oleh dua koneksi yang
        // benar-benar bersamaan; SQLite :memory: pada test hanya punya satu
        // koneksi sehingga lock di sana no-op. Test ini menjaga INVARIAN-nya
        // (total alokasi kumulatif <= nominal payment) secara sekuensial;
        // pembuktian konkuren menjadi PR verifikasi di PostgreSQL.
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'status' => Invoice::STATUS_LOCKED,
            'total_invoice' => 10000000,
            'total_paid' => 0,
        ]);
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'importir_id' => $this->importir->id,
            'payment_date' => '2026-11-10',
            'amount' => 10000000,
            'method' => Payment::METHOD_TRANSFER,
            'is_unallocated' => true,
        ]);

        $payload = fn (int $amount) => [
            'invoice_id' => $invoice->id,
            'allocations' => [
                ['invoice_item_id' => null, 'amount' => $amount, 'blok' => 'a'],
            ],
        ];

        $this->actingAs($this->finance)
            ->post(route('finance.payments.allocate', $payment->id), $payload(6000000))
            ->assertSessionHasNoErrors();

        // Batch kedua Rp6jt akan membuat total Rp12jt > Rp10jt -> ditolak 422.
        $this->actingAs($this->finance)
            ->post(route('finance.payments.allocate', $payment->id), $payload(6000000))
            ->assertSessionHasErrors();

        $this->assertEquals(6000000, (float) $invoice->fresh()->total_paid);
        $this->assertEquals(6000000, (float) $payment->allocations()->sum('amount'));
    }

    // ------------------------------------------------------------------
    // 4. Dokumen di storage privat
    // ------------------------------------------------------------------

    #[Test]
    public function upload_dokumen_ke_disk_privat_dan_download_ber_gate(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->job->initializeChecklist();
        $doc = JobDocument::query()->where('job_id', $this->job->id)->firstOrFail();

        $response = $this->actingAs($this->cs)->post(route('jobs.documents.upload', [
            'job' => $this->job->id, 'document' => $doc->id,
        ]), [
            'file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ]);
        $response->assertSessionHasNoErrors();

        $path = $doc->fresh()->file_path;
        $this->assertNotEmpty($path);
        $this->assertTrue(Storage::disk('local')->exists($path), 'File harus di disk privat local');
        $this->assertFalse(Storage::disk('public')->exists($path), 'File tidak boleh di disk publik');

        // Unduh lewat endpoint ber-Gate: pemilik akses Job boleh.
        $this->actingAs($this->cs)
            ->get(route('jobs.documents.download', ['job' => $this->job->id, 'document' => $doc->id]))
            ->assertOk()
            ->assertDownload('dokumen.pdf');

        // Field yang tidak ditugaskan ke Job ini -> 403.
        $this->actingAs($this->fieldLain)
            ->get(route('jobs.documents.download', ['job' => $this->job->id, 'document' => $doc->id]))
            ->assertForbidden();

        // Fallback file LAMA di disk publik tetap bisa diunduh lewat endpoint.
        Storage::disk('public')->put('documents/legacy/test.pdf', 'isi-legacy');
        $doc->update(['file_path' => 'documents/legacy/test.pdf']);
        $this->actingAs($this->cs)
            ->get(route('jobs.documents.download', ['job' => $this->job->id, 'document' => $doc->id]))
            ->assertOk();
    }

    #[Test]
    public function bukti_milestone_dan_pod_tersimpan_di_disk_privat(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Bukti milestone (pib_filed, level-Job).
        $response = $this->actingAs($this->field)->post(route('jobs.milestones.store', $this->job->id), [
            'milestone_type' => Milestone::TYPE_PIB_FILED,
            'occurred_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'proof_number' => 'PIB-HARD-1',
            'proof_file' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
        ]);
        $response->assertSessionHasNoErrors();
        $milestone = Milestone::query()->where('job_id', $this->job->id)->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($milestone->proof_file_path));
        $this->assertFalse(Storage::disk('public')->exists($milestone->proof_file_path));
        $this->actingAs($this->cs)
            ->get(route('jobs.milestones.proof', ['job' => $this->job->id, 'milestone' => $milestone->id]))
            ->assertOk();

        // POD Surat Jalan.
        $trip = Trip::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
        ]);
        $sj = SuratJalan::factory()->create([
            'company_id' => $this->company->id,
            'trip_id' => $trip->id,
        ]);
        $response = $this->actingAs($this->ops)->post(route('surat-jalan.upload-pod', $sj->id), [
            'file' => UploadedFile::fake()->create('pod.pdf', 80, 'application/pdf'),
        ]);
        $response->assertSessionHasNoErrors();
        $sj->refresh();
        $this->assertTrue(Storage::disk('local')->exists($sj->pod_file_path));
        $this->assertFalse(Storage::disk('public')->exists($sj->pod_file_path));
        $this->actingAs($this->ops)
            ->get(route('surat-jalan.pod.download', $sj->id))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // 5. Seeder: tenant demo eksplisit
    // ------------------------------------------------------------------

    #[Test]
    public function seeder_demo_tidak_mendarat_di_tenant_id_terendah(): void
    {
        // Skenario pemicu bug lama: database SUDAH punya company lain dengan
        // id lebih rendah dari 'PPJK Induk'. CompanyContext::id() fallback ke
        // min('id'), sehingga MasterDataSeeder/LegalTariffSeeder dulu menaruh
        // data demo di tenant yang salah.
        $lain = Company::factory()->create(['name' => 'Perusahaan Lain']);

        $this->seed(DatabaseSeeder::class);

        $induk = Company::query()->withoutGlobalScopes()->where('name', 'PPJK Induk')->firstOrFail();
        $this->assertNotSame($lain->id, $induk->id);

        $usd = Currency::query()->withoutGlobalScopes()->where('code', 'USD')->firstOrFail();
        $this->assertSame($induk->id, (int) $usd->company_id, 'Mata uang demo harus di tenant PPJK Induk');

        $priok = Location::query()->withoutGlobalScopes()->where('code', 'IDTPP')->firstOrFail();
        $this->assertSame($induk->id, (int) $priok->company_id, 'Pelabuhan demo harus di tenant PPJK Induk');

        $this->assertSame(0, Currency::query()->withoutGlobalScopes()->where('company_id', $lain->id)->count());
        $this->assertSame(0, Location::query()->withoutGlobalScopes()->where('company_id', $lain->id)->count());

        $admin = User::query()->withoutGlobalScopes()->where('email', 'admin@example.local')->firstOrFail();
        $this->assertSame($induk->id, (int) $admin->company_id);
    }
}
