<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Company;
use App\Models\Importir;
use App\Models\InternalExchangeRate;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\Location;
use App\Models\ShippingLine;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes perbaikan verifikasi dokumen Job: bug Cancel pada prompt verifikasi
 * (perilaku frontend — diverifikasi lewat review kode), tombol Unduh/Lihat,
 * dan Batalkan Verifikasi (unverify) dengan alasan wajib + audit.
 * Cakupan unverify (keputusan user 2026-11-05, temuan audit P2-4): HANYA
 * verifikator asli ATAU Admin (override) yang boleh membatalkan verifikasi.
 * Ops/Finance lain ditolak 403. Alasan wajib + audit tetap berlaku.
 * Verify tidak berubah (maker-checker verify ditegakkan di server pada
 * Milestone::verify()).
 */
class DokumenVerifikasiTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $admin;

    private User $finance;

    private User $ops1;

    private User $ops2;

    private User $cs;

    private User $field;

    private Job $job;

    private JobDocument $doc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        Storage::fake('local');

        $this->company = Company::factory()->create();

        $this->admin = $this->buatUser('admin');
        $this->finance = $this->buatUser('finance');
        $this->ops1 = $this->buatUser('ops');
        $this->ops2 = $this->buatUser('ops');
        $this->cs = $this->buatUser('cs');
        $this->field = $this->buatUser('field');

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $pol = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);
        $pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
            'pol_id' => $pol->id,
            'pod_id' => $pod->id,
        ]);
        $this->job->initializeChecklist();
        $this->doc = $this->job->documents()->firstOrFail();

        InternalExchangeRate::factory()->create([
            'company_id' => $this->company->id,
            'rate_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'rate_to_idr' => '16000.0000',
        ]);

        app(CompanyContext::class)->set($this->company->id);
    }

    private function buatUser(string $role): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->roles()->sync([$this->roleId($role)]);

        return $user;
    }

    private function unggah(User $user, string $nama = 'dokumen.pdf', string $mime = 'application/pdf'): void
    {
        $response = $this->actingAs($user)->post(
            route('jobs.documents.upload', [$this->job->id, $this->doc->id]),
            ['file' => UploadedFile::fake()->create($nama, 100, $mime)],
        );
        $response->assertSessionHasNoErrors();
        $this->assertSame(JobDocument::STATUS_DIUPLOAD, $this->doc->fresh()->status);
    }

    private function verifikasi(User $user): void
    {
        $response = $this->actingAs($user)->post(
            route('jobs.documents.verify', [$this->job->id, $this->doc->id]),
            ['notes' => 'Cek isi sesuai.'],
        );
        $response->assertSessionHasNoErrors();
        $this->assertSame(JobDocument::STATUS_DIVERIFIKASI, $this->doc->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Verifikasi (perilaku endpoint — tidak berubah oleh perbaikan Cancel)
    // ------------------------------------------------------------------

    #[Test]
    public function verifikasi_melalui_endpoint_tetap_berfungsi(): void
    {
        $this->unggah($this->cs);
        $this->verifikasi($this->ops1);

        $doc = $this->doc->fresh();
        $this->assertSame($this->ops1->id, $doc->verified_by_user_id);
        $this->assertNotNull($doc->verified_at);
    }

    // ------------------------------------------------------------------
    // Batalkan verifikasi (unverify)
    // ------------------------------------------------------------------

    /**
     * Cakupan dipersempit 2026-11-05 (P2-4): Ops yang BUKAN verifikator
     * asli tidak boleh membatalkan verifikasi orang lain.
     */
    #[Test]
    public function unverify_oleh_ops_lain_ditolak(): void
    {
        $this->unggah($this->cs);
        $this->verifikasi($this->ops1);

        $response = $this->actingAs($this->ops2)->post(
            route('jobs.documents.unverify', [$this->job->id, $this->doc->id]),
            ['reason' => 'Ternyata nomor B/L salah ketik, periksa ulang.'],
        );
        $response->assertForbidden();

        $doc = $this->doc->fresh();
        $this->assertSame(JobDocument::STATUS_DIVERIFIKASI, $doc->status);
        $this->assertSame($this->ops1->id, $doc->verified_by_user_id);
        $this->assertFalse(AuditTrail::query()
            ->where('action', 'job_document.unverified')
            ->where('object_id', $this->doc->id)
            ->exists());
    }

    #[Test]
    public function unverify_oleh_admin_berhasil(): void
    {
        $this->unggah($this->cs);
        $this->verifikasi($this->ops1);

        $response = $this->actingAs($this->admin)->post(
            route('jobs.documents.unverify', [$this->job->id, $this->doc->id]),
            ['reason' => 'Verifikasi keliru, kembalikan ke diupload.'],
        );
        $response->assertSessionHasNoErrors();
        $this->assertSame(JobDocument::STATUS_DIUPLOAD, $this->doc->fresh()->status);
    }

    /**
     * Cakupan dipersempit 2026-11-05 (P2-4): Finance yang bukan
     * verifikator asli (dan bukan Admin) tidak boleh membatalkan.
     */
    #[Test]
    public function unverify_oleh_finance_lain_ditolak(): void
    {
        $this->unggah($this->cs);
        $this->verifikasi($this->ops1);

        $response = $this->actingAs($this->finance)->post(
            route('jobs.documents.unverify', [$this->job->id, $this->doc->id]),
            ['reason' => 'Dokumen tidak sesuai, verifikasi dibatalkan.'],
        );
        $response->assertForbidden();
        $this->assertSame(JobDocument::STATUS_DIVERIFIKASI, $this->doc->fresh()->status);
    }

    /**
     * Cakupan unverify (keputusan user 2026-11-05, P2-4): verifikator
     * asli BOLEH membatalkan verifikasinya sendiri, dengan alasan wajib
     * + audit. Pembatalan adalah koreksi, bukan persetujuan.
     */
    #[Test]
    public function unverify_oleh_verifikator_asli_diperbolehkan_dengan_alasan_dan_audit(): void
    {
        $this->unggah($this->cs);
        $this->verifikasi($this->ops1);

        $response = $this->actingAs($this->ops1)->post(
            route('jobs.documents.unverify', [$this->job->id, $this->doc->id]),
            ['reason' => 'Saya yang memverifikasi, saya batalkan — nomor B/L salah baca.'],
        );
        $response->assertSessionHasNoErrors();

        $doc = $this->doc->fresh();
        $this->assertSame(JobDocument::STATUS_DIUPLOAD, $doc->status);
        $this->assertNull($doc->verified_by_user_id);
        $this->assertNull($doc->verified_at);

        $audit = AuditTrail::query()
            ->where('action', 'job_document.unverified')
            ->where('object_id', $this->doc->id)
            ->firstOrFail();
        $this->assertSame('Saya yang memverifikasi, saya batalkan — nomor B/L salah baca.', $audit->reason);
        $this->assertSame($this->ops1->id, $audit->user_id);

        // Setelah dibatalkan, dokumen bisa diverifikasi lagi (verify tak berubah).
        $this->verifikasi($this->ops2);
    }

    /**
     * Verify TIDAK berubah oleh cakupan unverify: setelah verifikator
     * asli membatalkan verifikasinya sendiri, ia tetap bisa memverifikasi
     * ulang dokumen yang sama (endpoint verify tidak mendapat pembatasan
     * baru). Maker-checker verify yang sesungguhnya ditegakkan di sisi
     * server pada Milestone::verify() — lihat
     * MilestoneAndPwaTest::verifikasi_milestone_menegakkan_maker_checker,
     * yang tetap hijau bersama suite ini.
     */
    #[Test]
    public function verifikasi_ulang_oleh_verifikator_asli_setelah_unverify_tetap_berfungsi(): void
    {
        $this->unggah($this->cs);
        $this->verifikasi($this->ops1);

        $response = $this->actingAs($this->ops1)->post(
            route('jobs.documents.unverify', [$this->job->id, $this->doc->id]),
            ['reason' => 'Salah baca nomor, batalkan dulu.'],
        );
        $response->assertSessionHasNoErrors();
        $this->assertSame(JobDocument::STATUS_DIUPLOAD, $this->doc->fresh()->status);

        // Verifikasi ulang oleh user yang sama tetap berfungsi.
        $this->verifikasi($this->ops1);
    }

    #[Test]
    public function unverify_tanpa_alasan_ditolak(): void
    {
        $this->unggah($this->cs);
        $this->verifikasi($this->ops1);

        // Pemanggil harus lolos aturan cakupan (verifikator asli) supaya
        // sampai ke validasi alasan — Ops lain sudah ditolak 403 duluan.
        $response = $this->actingAs($this->ops1)->post(
            route('jobs.documents.unverify', [$this->job->id, $this->doc->id]),
            [],
        );
        $response->assertSessionHasErrors('reason');
        $this->assertSame(JobDocument::STATUS_DIVERIFIKASI, $this->doc->fresh()->status);
    }

    #[Test]
    public function unverify_dokumen_belum_diverifikasi_ditolak(): void
    {
        $this->unggah($this->cs);

        // Dokumen belum diverifikasi sehingga tidak ada verifikator asli;
        // Admin (override) dipakai supaya sampai ke pengecekan status.
        $response = $this->actingAs($this->admin)->post(
            route('jobs.documents.unverify', [$this->job->id, $this->doc->id]),
            ['reason' => 'Belum diverifikasi kok dibatalkan.'],
        );
        $response->assertSessionHasErrors('unverify');
        $this->assertSame(JobDocument::STATUS_DIUPLOAD, $this->doc->fresh()->status);
    }

    #[Test]
    public function unverify_pada_job_tertutup_ditolak(): void
    {
        $this->unggah($this->cs);
        $this->verifikasi($this->ops1);

        $this->job->update([
            'closed_at' => now(),
            'closed_by_user_id' => $this->admin->id,
            'close_reason' => 'Selesai.',
        ]);

        // Pemanggil harus lolos aturan cakupan (verifikator asli) supaya
        // sampai ke pengecekan kunci tutup.
        $response = $this->actingAs($this->ops1)->post(
            route('jobs.documents.unverify', [$this->job->id, $this->doc->id]),
            ['reason' => 'Job sudah tertutup.'],
        );
        $response->assertSessionHasErrors('job_closed');
        $this->assertSame(JobDocument::STATUS_DIVERIFIKASI, $this->doc->fresh()->status);
    }

    #[Test]
    public function unverify_dokumen_job_lain_404(): void
    {
        $jobLain = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->job->importir_id,
            'shipping_line_id' => $this->job->shipping_line_id,
            'pol_id' => $this->job->pol_id,
            'pod_id' => $this->job->pod_id,
        ]);
        $jobLain->initializeChecklist();
        $docLain = $jobLain->documents()->firstOrFail();
        $docLain->markVerified($this->ops1);

        // Pemanggil adalah verifikator dokumen lain itu sendiri, supaya
        // sampai ke pengecekan kepemilikan dokumen terhadap Job di URL.
        $response = $this->actingAs($this->ops1)->post(
            route('jobs.documents.unverify', [$this->job->id, $docLain->id]),
            ['reason' => 'Dokumen job lain.'],
        );
        $response->assertNotFound();
    }

    #[Test]
    public function unverify_ditolak_untuk_cs(): void
    {
        $this->unggah($this->cs);
        $this->verifikasi($this->ops1);

        $response = $this->actingAs($this->cs)->post(
            route('jobs.documents.unverify', [$this->job->id, $this->doc->id]),
            ['reason' => 'CS mencoba membatalkan.'],
        );
        $response->assertForbidden();
        $this->assertSame(JobDocument::STATUS_DIVERIFIKASI, $this->doc->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Unduh & pratinjau
    // ------------------------------------------------------------------

    #[Test]
    public function pratinjau_pdf_mengembalikan_inline(): void
    {
        $this->unggah($this->cs, 'dokumen.pdf', 'application/pdf');

        $response = $this->actingAs($this->ops1)->get(
            route('jobs.documents.preview', [$this->job->id, $this->doc->id]),
        );
        $response->assertOk();
        $this->assertStringContainsString(
            'inline',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    #[Test]
    public function pratinjau_gambar_mengembalikan_inline(): void
    {
        $this->unggah($this->cs, 'foto.jpg', 'image/jpeg');

        $response = $this->actingAs($this->ops1)->get(
            route('jobs.documents.preview', [$this->job->id, $this->doc->id]),
        );
        $response->assertOk();
        $this->assertStringContainsString(
            'inline',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    #[Test]
    public function pratinjau_docx_ditolak(): void
    {
        $this->unggah(
            $this->cs,
            'dokumen.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $response = $this->actingAs($this->ops1)->get(
            route('jobs.documents.preview', [$this->job->id, $this->doc->id]),
        );
        $response->assertStatus(415);
    }

    #[Test]
    public function pratinjau_tanpa_akses_ke_job_ditolak(): void
    {
        $this->unggah($this->cs);

        // Field murni yang tidak ditugaskan ke Job ini tidak lolos Gate view.
        $response = $this->actingAs($this->field)->get(
            route('jobs.documents.preview', [$this->job->id, $this->doc->id]),
        );
        $response->assertForbidden();
    }

    #[Test]
    public function pratinjau_dokumen_job_lain_404(): void
    {
        $this->unggah($this->cs);

        $jobLain = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->job->importir_id,
            'shipping_line_id' => $this->job->shipping_line_id,
            'pol_id' => $this->job->pol_id,
            'pod_id' => $this->job->pod_id,
        ]);
        $jobLain->initializeChecklist();
        $docLain = $jobLain->documents()->firstOrFail();

        $response = $this->actingAs($this->ops1)->get(
            route('jobs.documents.preview', [$this->job->id, $docLain->id]),
        );
        $response->assertNotFound();
    }

    #[Test]
    public function unduh_tetap_memaksa_attachment(): void
    {
        $this->unggah($this->cs, 'dokumen.pdf', 'application/pdf');

        $response = $this->actingAs($this->ops1)->get(
            route('jobs.documents.download', [$this->job->id, $this->doc->id]),
        );
        $response->assertOk();
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
        );
    }
}
