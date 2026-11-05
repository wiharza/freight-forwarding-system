<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Importir;
use App\Models\Job;
use App\Models\JobDocument;
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
 * Tes regresi temuan audit 8 item (2026-11-05), item 6 — verifikasi
 * dokumen mensyaratkan file bukti: `file_path` tidak kosong DAN file
 * benar-benar ada di disk privat (`PrivateFile::exists()`, cek disk
 * `local` lalu fallback `public` untuk file lama). Ditegakkan di
 * `JobDocument::markVerified()` (jalur bersama semua pemanggil).
 */
class Audit8ItemDokumenTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $ops;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        Storage::fake('local');

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
        ]);
        $this->job->initializeChecklist();
    }

    #[Test]
    public function verifikasi_tanpa_upload_ditolak(): void
    {
        $doc = $this->job->documents()->firstOrFail();
        $this->assertNull($doc->file_path);

        $response = $this->actingAs($this->ops)->post(
            route('jobs.documents.verify', [$this->job->id, $doc->id]),
            ['notes' => 'Cek isi sesuai.'],
        );

        $response->assertSessionHasErrors('document');
        $this->assertSame(JobDocument::STATUS_BELUM_ADA, $doc->fresh()->status);
    }

    #[Test]
    public function verifikasi_dengan_file_path_tetapi_file_hilang_ditolak(): void
    {
        $doc = $this->job->documents()->firstOrFail();
        $doc->update(['file_path' => 'documents/sudah-dihapus.pdf', 'status' => JobDocument::STATUS_DIUPLOAD]);

        $response = $this->actingAs($this->ops)->post(
            route('jobs.documents.verify', [$this->job->id, $doc->id]),
            ['notes' => 'Cek isi sesuai.'],
        );

        $response->assertSessionHasErrors('document');
        $this->assertSame(JobDocument::STATUS_DIUPLOAD, $doc->fresh()->status);
    }

    #[Test]
    public function verifikasi_dengan_upload_berhasil(): void
    {
        $doc = $this->job->documents()->firstOrFail();

        $this->actingAs($this->ops)->post(
            route('jobs.documents.upload', [$this->job->id, $doc->id]),
            ['file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')],
        )->assertSessionHasNoErrors();

        $response = $this->actingAs($this->ops)->post(
            route('jobs.documents.verify', [$this->job->id, $doc->id]),
            ['notes' => 'Cek isi sesuai.'],
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(JobDocument::STATUS_DIVERIFIKASI, $doc->fresh()->status);
    }
}
