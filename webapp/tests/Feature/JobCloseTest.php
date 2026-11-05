<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\Importir;
use App\Models\InternalExchangeRate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\Location;
use App\Models\Milestone;
use App\Models\ShippingLine;
use App\Models\UangMuka;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes fitur penutupan & pembukaan kembali Job (§2.3, Q11).
 */
class JobCloseTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $admin;

    private User $finance;

    private User $finance2;

    private User $cs;

    private User $ops;

    private Importir $importir;

    private ShippingLine $line;

    private Location $pol;

    private Location $pod;

    private Job $job;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->roles()->sync([$this->roleId('admin')]);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->finance2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance2->roles()->sync([$this->roleId('finance')]);

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $this->line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $this->pol = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);
        $this->pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        ContainerType::query()->create([
            'company_id' => $this->company->id,
            'size' => "40'",
            'type' => 'High Cube',
        ]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'pol_id' => $this->pol->id,
            'pod_id' => $this->pod->id,
        ]);
        $this->container = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU1234565',
            'size' => "40'",
            'type' => 'High Cube',
            'seal_number' => 'SEAL-001',
            'gross_weight_kg' => 25000,
        ]);

        $this->job->initializeChecklist();

        // Kurs Internal hari ini — halaman Job menghitung demurrage live dan
        // gagal tanpa baris kurs USD (§6.6).
        InternalExchangeRate::factory()->create([
            'company_id' => $this->company->id,
            'rate_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'rate_to_idr' => '16000.0000',
        ]);

        app(CompanyContext::class)->set($this->company->id);
    }

    // ------------------------------------------------------------------
    // Pembangun kondisi
    // ------------------------------------------------------------------

    /** Job yang memenuhi SEMUA syarat close (§2.3). */
    private function buatJobSiapTutup(): Job
    {
        // (b) gate-in empty untuk semua kontainer
        Milestone::query()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'container_id' => $this->container->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_GATE_IN_EMPTY,
            'occurred_at' => now(),
            'recorded_at' => now(),
            'is_backdate' => false,
            'is_active' => true,
            'created_by_user_id' => $this->ops->id,
        ]);

        // (c) arsip seluruh fase terverifikasi
        foreach ($this->job->documents as $doc) {
            if ($doc->status !== JobDocument::STATUS_TIDAK_BERLAKU) {
                $doc->markVerified($this->ops);
            }
        }

        // (a) invoice lunas
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'total_invoice' => 1000000.0000,
            'total_paid' => 1000000.0000,
            'status' => Invoice::STATUS_LOCKED,
            'created_by_user_id' => $this->finance->id,
        ]);

        // (d) tanpa Dispute — problem_flag dibiarkan null
        // (e) tanpa uang muka — settlement nol

        return $this->job->fresh();
    }

    private function tutupJob(Job $job, User $user, string $reason = 'Semua kewajiban selesai.'): void
    {
        $response = $this->actingAs($user)->post(route('jobs.close', $job->id), ['reason' => $reason]);
        $response->assertSessionHasNoErrors();
    }

    // ------------------------------------------------------------------
    // Close berhasil
    // ------------------------------------------------------------------

    #[Test]
    public function close_berhasil_bila_semua_syarat_lolos(): void
    {
        $job = $this->buatJobSiapTutup();

        $response = $this->actingAs($this->finance)->post(route('jobs.close', $job->id), [
            'reason' => 'Semua kewajiban selesai.',
        ]);

        $response->assertSessionHasNoErrors();

        $job = $job->fresh();
        $this->assertTrue($job->isClosed());
        $this->assertSame(Job::STATUS_CLOSED, $job->cached_status);
        $this->assertSame($this->finance->id, $job->closed_by_user_id);
        $this->assertSame('Semua kewajiban selesai.', $job->close_reason);
        $this->assertNotNull($job->closed_at);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'job.closed',
            'object_id' => $job->id,
            'user_id' => $this->finance->id,
        ]);
    }

    #[Test]
    public function halaman_job_menampilkan_lima_syarat_close_yang_lolos(): void
    {
        $job = $this->buatJobSiapTutup();

        $this->actingAs($this->finance)->get(route('jobs.show', $job->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('closePreconditions', 5)
                ->where('canClose', true)
                ->where('canReopen', true));
    }

    // ------------------------------------------------------------------
    // Tiap syarat yang gagal memblokir close dengan blocker bernama
    // ------------------------------------------------------------------

    #[Test]
    public function close_ditolak_bila_invoice_belum_lunas(): void
    {
        $job = $this->buatJobSiapTutup();
        // Ubah invoice menjadi belum lunas
        $job->invoices()->update(['total_paid' => 600000.0000]);

        $response = $this->actingAs($this->finance)->post(route('jobs.close', $job->id), [
            'reason' => 'Coba tutup.',
        ]);

        $response->assertSessionHasErrors('close');
        $this->assertFalse($job->fresh()->isClosed());
        $this->assertDatabaseHas('audit_trails', ['action' => 'job.close_blocked', 'object_id' => $job->id]);
    }

    #[Test]
    public function close_ditolak_bila_belum_ada_invoice(): void
    {
        $job = $this->buatJobSiapTutup();
        $job->invoices()->delete();

        $response = $this->actingAs($this->finance)->post(route('jobs.close', $job->id), [
            'reason' => 'Coba tutup.',
        ]);

        $response->assertSessionHasErrors('close');
        $this->assertFalse($job->fresh()->isClosed());
    }

    #[Test]
    public function close_ditolak_bila_kontainer_belum_gate_in_empty(): void
    {
        $job = $this->buatJobSiapTutup();
        $job->milestones()->delete();

        $response = $this->actingAs($this->finance)->post(route('jobs.close', $job->id), [
            'reason' => 'Coba tutup.',
        ]);

        $response->assertSessionHasErrors('close');
        $this->assertFalse($job->fresh()->isClosed());
    }

    #[Test]
    public function close_ditolak_bila_arsip_belum_lengkap(): void
    {
        $job = $this->buatJobSiapTutup();
        // Batalkan verifikasi satu dokumen (POD pasca-delivery)
        $job->documents()->where('document_type', JobDocument::TYPE_POD)
            ->update(['status' => JobDocument::STATUS_DIUPLOAD]);

        $response = $this->actingAs($this->finance)->post(route('jobs.close', $job->id), [
            'reason' => 'Coba tutup.',
        ]);

        $response->assertSessionHasErrors('close');
        $this->assertFalse($job->fresh()->isClosed());
    }

    #[Test]
    public function close_ditolak_bila_problem_flag_dispute(): void
    {
        $job = $this->buatJobSiapTutup();
        $job->update(['problem_flag' => 'Dispute']);

        $response = $this->actingAs($this->finance)->post(route('jobs.close', $job->id), [
            'reason' => 'Coba tutup.',
        ]);

        $response->assertSessionHasErrors('close');
        $this->assertFalse($job->fresh()->isClosed());
    }

    #[Test]
    public function close_ditolak_bila_settlement_uang_muka_belum_nol(): void
    {
        $job = $this->buatJobSiapTutup();
        UangMuka::query()->create([
            'company_id' => $this->company->id,
            'job_id' => $job->id,
            'amount' => 5000000.0000,
            'settled_amount' => 2000000.0000,
            'received_date' => now()->toDateString(),
            'method' => UangMuka::METHOD_TRANSFER,
            'is_settled' => false,
            'created_by_user_id' => $this->finance->id,
        ]);

        $response = $this->actingAs($this->finance)->post(route('jobs.close', $job->id), [
            'reason' => 'Coba tutup.',
        ]);

        $response->assertSessionHasErrors('close');
        $this->assertFalse($job->fresh()->isClosed());
    }

    // ------------------------------------------------------------------
    // Otorisasi & validasi close
    // ------------------------------------------------------------------

    #[Test]
    public function close_ditolak_untuk_cs(): void
    {
        $job = $this->buatJobSiapTutup();

        $this->actingAs($this->cs)->post(route('jobs.close', $job->id), ['reason' => 'Coba tutup.'])
            ->assertForbidden();
        $this->assertFalse($job->fresh()->isClosed());
    }

    #[Test]
    public function close_ditolak_tanpa_alasan(): void
    {
        $job = $this->buatJobSiapTutup();

        $this->actingAs($this->finance)->post(route('jobs.close', $job->id), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertFalse($job->fresh()->isClosed());
    }

    #[Test]
    public function close_kedua_ditolak(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);

        $this->actingAs($this->finance2)->post(route('jobs.close', $job->id), ['reason' => 'Tutup lagi.'])
            ->assertSessionHasErrors('close');
    }

    // ------------------------------------------------------------------
    // Kunci tulis pada Job tertutup
    // ------------------------------------------------------------------

    #[Test]
    public function milestone_ditolak_pada_job_tertutup(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);

        $response = $this->actingAs($this->ops)->post(route('jobs.milestones.store', $job->id), [
            'milestone_type' => Milestone::TYPE_DISCHARGE,
            'occurred_at' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('job_closed');
        $this->assertDatabaseMissing('milestones', [
            'job_id' => $job->id,
            'milestone_type' => Milestone::TYPE_DISCHARGE,
        ]);
    }

    #[Test]
    public function upload_dokumen_ditolak_pada_job_tertutup(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);
        $doc = $job->documents()->first();

        $response = $this->actingAs($this->ops)->post(
            route('jobs.documents.upload', ['job' => $job->id, 'document' => $doc->id]),
            ['file' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf')],
        );

        $response->assertSessionHasErrors('job_closed');
    }

    #[Test]
    public function invoice_baru_ditolak_pada_job_tertutup(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);

        $response = $this->actingAs($this->finance)->post(route('finance.invoices.store', $job->id), [
            'invoice_date' => now()->toDateString(),
            'blok_c_service_fee' => 100000,
            'blok_c_ppn_rate' => 0.12,
        ]);

        $response->assertSessionHasErrors('job_closed');
        $this->assertSame(1, $job->fresh()->invoices()->count());
    }

    #[Test]
    public function recompute_status_tidak_menimpa_closed(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);

        $this->assertSame(Job::STATUS_CLOSED, $job->fresh()->recomputeStatus());
        $this->assertSame(Job::STATUS_CLOSED, $job->fresh()->cached_status);
    }

    // ------------------------------------------------------------------
    // Reopen
    // ------------------------------------------------------------------

    #[Test]
    public function reopen_berhasil_oleh_admin_dan_status_kembali_ke_proyeksi_milestone(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);

        $response = $this->actingAs($this->admin)->post(route('jobs.reopen', $job->id), [
            'reason' => 'Invoice susulan ditemukan setelah close.',
        ]);

        $response->assertSessionHasNoErrors();

        $job = $job->fresh();
        $this->assertFalse($job->isClosed());
        $this->assertNull($job->closed_at);
        $this->assertNull($job->closed_by_user_id);
        $this->assertNull($job->close_reason);
        // Status kembali ke proyeksi milestone: gate-in empty selesai.
        $this->assertSame(Job::STATUS_GATE_IN_SELESAI, $job->cached_status);

        // Riwayat penutupan TIDAK terhapus — tetap ada di audit trail.
        $this->assertDatabaseHas('audit_trails', ['action' => 'job.closed', 'object_id' => $job->id]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'job.reopened',
            'object_id' => $job->id,
            'user_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function reopen_oleh_finance_kedua_berhasil(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);

        $this->actingAs($this->finance2)->post(route('jobs.reopen', $job->id), [
            'reason' => 'Perlu koreksi arsip.',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($job->fresh()->isClosed());
    }

    #[Test]
    public function reopen_ditolak_oleh_penutup_sendiri_maker_checker(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);

        $this->actingAs($this->finance)->post(route('jobs.reopen', $job->id), [
            'reason' => 'Saya yang menutup, saya buka lagi.',
        ])->assertSessionHasErrors('maker_checker');

        $this->assertTrue($job->fresh()->isClosed());
    }

    #[Test]
    public function reopen_ditolak_untuk_cs(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);

        $this->actingAs($this->cs)->post(route('jobs.reopen', $job->id), ['reason' => 'Coba buka.'])
            ->assertForbidden();
        $this->assertTrue($job->fresh()->isClosed());
    }

    #[Test]
    public function reopen_ditolak_tanpa_alasan(): void
    {
        $job = $this->buatJobSiapTutup();
        $this->tutupJob($job, $this->finance);

        $this->actingAs($this->admin)->post(route('jobs.reopen', $job->id), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertTrue($job->fresh()->isClosed());
    }

    #[Test]
    public function reopen_ditolak_bila_job_belum_tertutup(): void
    {
        $job = $this->buatJobSiapTutup();

        $this->actingAs($this->admin)->post(route('jobs.reopen', $job->id), ['reason' => 'Belum ditutup.'])
            ->assertSessionHasErrors('reopen');
    }
}
