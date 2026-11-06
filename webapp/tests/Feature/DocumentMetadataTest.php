<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FtaForm;
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
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes isian data terstruktur per jenis dokumen (§2.4, Q21): validasi
 * allowlist saat upload, endpoint edit metadata, prefill dari data Job /
 * Form FTA, dan kunci Job tertutup + dokumen terverifikasi.
 */
class DocumentMetadataTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $admin;

    private User $ops;

    private Job $job;

    private JobDocument $docInvoice;

    private JobDocument $docBl;

    private JobDocument $docFta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        Storage::fake('local');

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->admin = $this->buatUser('admin');
        $this->ops = $this->buatUser('ops');

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
            'bl_number' => 'SITU123456789',
        ]);
        $this->job->initializeChecklist();

        $this->docInvoice = $this->job->documents()->where('document_type', 'invoice')->firstOrFail();
        $this->docBl = $this->job->documents()->where('document_type', 'bl')->firstOrFail();
        $this->docFta = $this->job->documents()->where('document_type', 'form_fta')->firstOrFail();

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

    private function unggah(JobDocument $doc, array $metadata = []): void
    {
        $response = $this->actingAs($this->ops)->post(
            route('jobs.documents.upload', [$this->job->id, $doc->id]),
            array_merge(
                ['file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')],
                $metadata === [] ? [] : ['metadata' => $metadata],
            ),
        );
        $response->assertSessionHasNoErrors();
    }

    // ------------------------------------------------------------------
    // Upload dengan metadata
    // ------------------------------------------------------------------

    #[Test]
    public function upload_dengan_metadata_valid_menyimpan_dan_menampilkan_ringkasan()
    {
        $this->unggah($this->docInvoice, [
            'nomor_invoice' => 'INV-2026-001',
            'tanggal_invoice' => '2026-11-04',
            'nilai' => '1500000',
            'mata_uang' => 'IDR',
        ]);

        $stored = $this->docInvoice->fresh()->metadata;
        $this->assertSame('INV-2026-001', $stored['nomor_invoice']);
        $this->assertSame('2026-11-04', $stored['tanggal_invoice']);
        $this->assertSame('1500000', $stored['nilai']);
        $this->assertSame('IDR', $stored['mata_uang']);

        $this->actingAs($this->ops)->get(route('jobs.show', $this->job->id))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $doc = collect($page->toArray()['props']['job']['documents'])->firstWhere('id', $this->docInvoice->id);
                $this->assertNotNull($doc);
                $this->assertStringContainsString('INV-2026-001', $doc['metadata_summary']);
                $this->assertStringContainsString('Rp 1.500.000', $doc['metadata_summary']);
            });
    }

    #[Test]
    public function upload_tanpa_metadata_tetap_sah_dan_tidak_menghapus_data_lama()
    {
        $this->unggah($this->docInvoice, ['nomor_invoice' => 'INV-2026-001']);
        $this->assertSame('INV-2026-001', $this->docInvoice->fresh()->metadata['nomor_invoice']);

        // Upload ulang file tanpa metadata: data lama dipertahankan.
        $this->unggah($this->docInvoice);
        $this->assertSame('INV-2026-001', $this->docInvoice->fresh()->metadata['nomor_invoice']);
    }

    #[Test]
    public function upload_dengan_kunci_tak_dikenal_ditolak_422()
    {
        $response = $this->actingAs($this->ops)->post(
            route('jobs.documents.upload', [$this->job->id, $this->docInvoice->id]),
            [
                'file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
                'metadata' => ['nomor_invoice' => 'INV-1', 'kunci_rahasia' => 'x'],
            ],
        );
        $response->assertSessionHasErrors('metadata');
        $this->assertNull($this->docInvoice->fresh()->metadata);
    }

    #[Test]
    public function upload_dengan_nilai_salah_tipe_ditolak_422()
    {
        $response = $this->actingAs($this->ops)->post(
            route('jobs.documents.upload', [$this->job->id, $this->docInvoice->id]),
            [
                'file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
                'metadata' => ['tanggal_invoice' => 'bukan-tanggal', 'mata_uang' => 'EUR'],
            ],
        );
        $response->assertSessionHasErrors(['metadata.tanggal_invoice', 'metadata.mata_uang']);
        $this->assertNull($this->docInvoice->fresh()->metadata);
    }

    // ------------------------------------------------------------------
    // Endpoint edit metadata
    // ------------------------------------------------------------------

    #[Test]
    public function edit_metadata_memperbarui_dan_mencatat_audit()
    {
        $this->unggah($this->docInvoice, ['nomor_invoice' => 'INV-LAMA']);

        $response = $this->actingAs($this->ops)->post(
            route('jobs.documents.metadata', [$this->job->id, $this->docInvoice->id]),
            ['metadata' => ['nomor_invoice' => 'INV-BARU', 'nilai' => '2500000', 'mata_uang' => 'IDR']],
        );
        $response->assertSessionHasNoErrors();

        $stored = $this->docInvoice->fresh()->metadata;
        $this->assertSame('INV-BARU', $stored['nomor_invoice']);
        $this->assertSame('2500000', $stored['nilai']);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'job_document.metadata_updated',
            'object_type' => JobDocument::class,
            'object_id' => $this->docInvoice->id,
        ]);
    }

    #[Test]
    public function edit_metadata_ditolak_untuk_dokumen_terverifikasi()
    {
        $this->unggah($this->docInvoice, ['nomor_invoice' => 'INV-1']);
        $this->docInvoice->fresh()->markVerified($this->ops);

        $response = $this->actingAs($this->ops)->post(
            route('jobs.documents.metadata', [$this->job->id, $this->docInvoice->id]),
            ['metadata' => ['nomor_invoice' => 'INV-UBAH']],
        );
        $response->assertSessionHasErrors('metadata');
        $this->assertSame('INV-1', $this->docInvoice->fresh()->metadata['nomor_invoice']);
    }

    #[Test]
    public function edit_metadata_ditolak_pada_job_tertutup()
    {
        $this->unggah($this->docInvoice, ['nomor_invoice' => 'INV-1']);
        $this->job->update(['closed_at' => now(), 'closed_by' => $this->admin->id]);

        $this->actingAs($this->ops)->post(
            route('jobs.documents.metadata', [$this->job->id, $this->docInvoice->id]),
            ['metadata' => ['nomor_invoice' => 'INV-UBAH']],
        )->assertSessionHasErrors('job_closed');

        $this->assertSame('INV-1', $this->docInvoice->fresh()->metadata['nomor_invoice']);
    }

    #[Test]
    public function edit_metadata_dokumen_job_lain_ditolak_404()
    {
        $jobLain = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->job->importir_id,
            'shipping_line_id' => $this->job->shipping_line_id,
            'pol_id' => $this->job->pol_id,
            'pod_id' => $this->job->pod_id,
        ]);
        $jobLain->initializeChecklist();
        $docLain = $jobLain->documents()->where('document_type', 'invoice')->firstOrFail();

        // Dokumen Job lain diakses lewat URL Job ini.
        $this->actingAs($this->ops)->post(
            route('jobs.documents.metadata', [$this->job->id, $docLain->id]),
            ['metadata' => ['nomor_invoice' => 'INV-X']],
        )->assertNotFound();
    }

    #[Test]
    public function edit_metadata_dengan_kunci_tak_dikenal_ditolak_422()
    {
        $this->unggah($this->docInvoice, ['nomor_invoice' => 'INV-1']);

        $this->actingAs($this->ops)->post(
            route('jobs.documents.metadata', [$this->job->id, $this->docInvoice->id]),
            ['metadata' => ['nomor_invoice' => 'INV-1', 'asal_usul' => 'x']],
        )->assertSessionHasErrors('metadata');
    }

    // ------------------------------------------------------------------
    // Prefill
    // ------------------------------------------------------------------

    #[Test]
    public function prefill_bl_dari_nomor_bl_job_dan_form_fta_dari_fta_forms()
    {
        FtaForm::query()->create([
            'job_id' => $this->job->id,
            'company_id' => $this->company->id,
            'form_number' => 'FORM-E-777',
            'scheme_code' => 'ACFTA',
            'hs_code' => '8471.30.20',
            'origin_country' => 'CN',
            'port_of_loading' => 'Shanghai',
            'issued_at' => '2026-10-20',
            'valid_until' => '2027-10-19',
        ]);

        $this->actingAs($this->ops)->get(route('jobs.show', $this->job->id))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $bl = collect($page->toArray()['props']['job']['documents'])->firstWhere('id', $this->docBl->id);
                $fta = collect($page->toArray()['props']['job']['documents'])->firstWhere('id', $this->docFta->id);

                $this->assertSame('SITU123456789', $bl['metadata_prefill']['nomor_bl']);
                $this->assertSame('FORM-E-777', $fta['metadata_prefill']['nomor_form']);
                $this->assertSame('ACFTA', $fta['metadata_prefill']['skema']);
                $this->assertSame('CN', $fta['metadata_prefill']['negara_asal']);
            });
    }

    #[Test]
    public function metadata_tersimpan_menang_atas_prefill()
    {
        $this->unggah($this->docBl, ['nomor_bl' => 'BL-KOREKSI-001']);

        $this->actingAs($this->ops)->get(route('jobs.show', $this->job->id))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $bl = collect($page->toArray()['props']['job']['documents'])->firstWhere('id', $this->docBl->id);
                $this->assertSame('BL-KOREKSI-001', $bl['metadata_prefill']['nomor_bl']);
            });
    }
}
