<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Importir;
use App\Models\ImportirChecklistOverride;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\Location;
use App\Models\Role;
use App\Models\ShippingLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class JobAndChecklistTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;
    private User $cs;
    private User $ops;
    private User $field;
    private Importir $importir;
    private ShippingLine $line;
    private Location $pod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->field = User::factory()->create(['company_id' => $this->company->id]);
        $this->field->roles()->sync([$this->roleId('field')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $this->line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $this->pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        app(\App\Support\CompanyContext::class)->set($this->company->id);
    }

    #[Test]
    public function cs_dapat_membuat_job_fcl_dengan_multiple_kontainer(): void
    {
        $payload = [
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'bl_number' => 'BL-JKT-001',
            'bl_date' => '2026-11-01',
            'pod_id' => $this->pod->id,
            'vessel_name' => 'EVER GIVEN',
            'voyage_number' => '101N',
            'claim_fta' => false,
            'is_fcl' => true,
            'containers' => [
                [
                    'container_number' => 'MSKU1234567',
                    'size' => '20',
                    'type' => 'Dry',
                    'seal_number' => 'SEAL-01',
                    'gross_weight_kg' => '21000',
                ],
                [
                    'container_number' => 'MSKU7654321',
                    'size' => '40',
                    'type' => 'High Cube',
                    'seal_number' => 'SEAL-02',
                    'gross_weight_kg' => '28000',
                ],
            ],
        ];

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jobs', [
            'bl_number' => 'BL-JKT-001',
            'cached_status' => Job::STATUS_DRAFT,
        ]);

        $job = Job::query()->where('bl_number', 'BL-JKT-001')->firstOrFail();
        $this->assertCount(2, $job->containers);
        $this->assertDatabaseHas('containers', [
            'job_id' => $job->id,
            'container_number' => 'MSKU1234567',
            'size' => '20',
        ]);

        // Verifikasi checklist dokumen terinisialisasi
        $this->assertDatabaseHas('job_documents', [
            'job_id' => $job->id,
            'document_type' => JobDocument::TYPE_INVOICE,
            'status' => JobDocument::STATUS_BELUM_ADA,
        ]);
        // Form FTA berstatus tidak_berlaku karena claim_fta = false
        $this->assertDatabaseHas('job_documents', [
            'job_id' => $job->id,
            'document_type' => JobDocument::TYPE_FORM_FTA,
            'status' => JobDocument::STATUS_TIDAK_BERLAKU,
        ]);
    }

    #[Test]
    public function pembuatan_job_lcl_ditolak_eksplisit(): void
    {
        $payload = [
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'bl_number' => 'BL-LCL-999',
            'bl_date' => '2026-11-01',
            'pod_id' => $this->pod->id,
            'is_fcl' => false, // upaya LCL
            'containers' => [
                [
                    'container_number' => 'LCLU1234567',
                    'size' => '20',
                    'type' => 'Dry',
                    'gross_weight_kg' => '5000',
                ],
            ],
        ];

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $payload);

        $response->assertSessionHasErrors(['is_fcl']);
        $this->assertDatabaseMissing('jobs', ['bl_number' => 'BL-LCL-999']);
    }

    #[Test]
    public function status_progres_menjadi_dokumen_lengkap_setelah_semua_pra_filing_diverifikasi(): void
    {
        Storage::fake('public');

        $job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'claim_fta' => false,
            'cached_status' => Job::STATUS_DRAFT,
        ]);
        $job->initializeChecklist();

        // Ambil dokumen Pra-Filing (Invoice, PL, BL)
        $praFilingDocs = $job->documents()
            ->where('phase', JobDocument::PHASE_PRA_FILING)
            ->where('status', '!=', JobDocument::STATUS_TIDAK_BERLAKU)
            ->get();

        $this->assertCount(3, $praFilingDocs); // Inv, PL, BL

        // Verifikasi dokumen satu per satu oleh Ops
        foreach ($praFilingDocs as $doc) {
            $this->assertEquals(Job::STATUS_DRAFT, $job->fresh()->cached_status);

            // Upload file
            $file = UploadedFile::fake()->create("{$doc->document_type}.pdf", 100);
            $this->actingAs($this->cs)->post(route('jobs.documents.upload', ['job' => $job->id, 'document' => $doc->id]), [
                'file' => $file,
            ]);

            // Verifikasi oleh Ops
            $this->actingAs($this->ops)->post(route('jobs.documents.verify', ['job' => $job->id, 'document' => $doc->id]), [
                'notes' => 'Dokumen sah',
            ]);
        }

        // Sekarang semua dokumen Pra-Filing sudah diverifikasi -> Status Progres wajib "Dokumen Lengkap" (§2.3)
        $this->assertEquals(Job::STATUS_DOKUMEN_LENGKAP, $job->fresh()->cached_status);
    }

    #[Test]
    public function override_checklist_importir_tidak_memblokir_dokumen_lengkap(): void
    {
        // Importir memiliki override: Packing List tidak diperlukan (§2.4, Q21)
        ImportirChecklistOverride::create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'document_type' => JobDocument::TYPE_PACKING_LIST,
            'is_required' => false,
            'reason' => 'Kontrak khusus barang curah/homogen',
            'effective_date' => now()->toDateString(),
        ]);

        $job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'claim_fta' => false,
        ]);
        $job->initializeChecklist();

        $plDoc = $job->documents()->where('document_type', JobDocument::TYPE_PACKING_LIST)->first();
        $this->assertEquals(JobDocument::STATUS_TIDAK_BERLAKU, $plDoc->status);

        // Verifikasi hanya Invoice & BL
        $invDoc = $job->documents()->where('document_type', JobDocument::TYPE_INVOICE)->first();
        $blDoc = $job->documents()->where('document_type', JobDocument::TYPE_BL)->first();

        $invDoc->markVerified($this->ops);
        $blDoc->markVerified($this->ops);

        // Status langsung naik ke Dokumen Lengkap tanpa menuntut Packing List
        $this->assertEquals(Job::STATUS_DOKUMEN_LENGKAP, $job->fresh()->cached_status);
    }

    #[Test]
    public function problem_flag_dapat_diubah_terpisah_dari_status_progres(): void
    {
        $job = Job::factory()->create([
            'company_id' => $this->company->id,
            'cached_status' => Job::STATUS_DOKUMEN_LENGKAP,
            'problem_flag' => null,
        ]);

        $response = $this->actingAs($this->ops)->patch(route('jobs.problem-flag', $job->id), [
            'problem_flag' => 'Hold',
            'reason' => 'Menunggu instruksi importir terkait jalur merah',
        ]);

        $response->assertSessionHasNoErrors();
        $job->refresh();

        $this->assertEquals('Hold', $job->problem_flag);
        $this->assertEquals(Job::STATUS_DOKUMEN_LENGKAP, $job->cached_status); // progres tidak hilang!
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'job.problem_flag_updated',
            'reason' => 'Menunggu instruksi importir terkait jalur merah',
        ]);
    }

    #[Test]
    public function ops_dapat_menugaskan_field_ke_job(): void
    {
        $job = Job::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->ops)->post(route('jobs.assign-field', $job->id), [
            'field_user_id' => $this->field->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($this->field->can('view', $job));
        $this->assertDatabaseHas('field_job_assignments', [
            'job_id' => $job->id,
            'field_user_id' => $this->field->id,
            'assigned_by' => $this->ops->id,
        ]);
    }
}
