<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomsDeclaration;
use App\Models\Importir;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\Location;
use App\Models\ShippingLine;
use App\Models\TaxExchangeRate;
use App\Models\User;
use App\Services\PibCalculator;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes FK dokumen pembebasan PPh 22 Pasal 4 (keputusan user 2026-11-06):
 * `exemption_document_ref` teks bebas diganti `exemption_document_id`
 * (FK → job_documents). Dokumen pendukung harus milik Job yang sama DAN
 * filenya benar-benar ada di disk privat — ditegakkan di storeDraft,
 * kalkulator, dan approve (3 lapis, sama seperti aturan pembebasan).
 */
class ExemptionDocumentFkTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $customs;

    private User $customs2;

    private Job $job;

    private Job $jobLain;

    private JobDocument $docPendukung;

    private JobDocument $docJobLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->customs = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs->roles()->sync([$this->roleId('customs_specialist')]);

        $this->customs2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs2->roles()->sync([$this->roleId('customs_specialist')]);

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
        $this->jobLain = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
            'pol_id' => $pol->id,
            'pod_id' => $pod->id,
        ]);

        TaxExchangeRate::query()->create([
            'company_id' => $this->company->id,
            'kmk_number' => 'KMK-TEST-16000',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'currency_code' => 'USD',
            'rate_to_idr' => '16000',
            'source' => 'test',
        ]);

        Storage::fake('local');
        $this->docPendukung = $this->buatDokumen($this->job, 'sk-pembebasan.pdf', true);
        $this->docJobLain = $this->buatDokumen($this->jobLain, 'sk-job-lain.pdf', true);
    }

    private function buatDokumen(Job $job, string $fileName, bool $denganFile, string $type = JobDocument::TYPE_INVOICE): JobDocument
    {
        $path = "documents/{$this->company->id}/{$job->id}/{$fileName}";
        if ($denganFile) {
            Storage::disk('local')->put($path, 'dummy');
        }

        return JobDocument::query()->create([
            'company_id' => $this->company->id,
            'job_id' => $job->id,
            'phase' => JobDocument::PHASE_PRA_FILING,
            'document_type' => $type,
            'status' => JobDocument::STATUS_DIUPLOAD,
            'file_path' => $denganFile ? $path : null,
            'file_name' => $fileName,
            'uploaded_by_user_id' => $this->customs->id,
            'uploaded_at' => now(),
        ]);
    }

    private function itemPembebasan(array $overrides = []): array
    {
        return array_merge([
            'hs_code' => '8471302000',
            'currency_code' => 'USD',
            'fob_valas' => '10000',
            'freight_valas' => '500',
            'insurance_basis' => 'input_riil',
            'insurance_valas' => '10',
            'is_pph22_exempt' => true,
            'exemption_reason' => 'Hibah bencana alam.',
        ], $overrides);
    }

    private function buatDrafLamaDenganPembebasan(?int $docId): array
    {
        $declaration = CustomsDeclaration::query()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'pib_number' => 'PIB-DRAFT-'.$this->job->job_number,
            'version' => 1,
            'status' => CustomsDeclaration::STATUS_DRAFT,
            'kmk_number' => 'KMK-TEST-16000',
            'created_by_user_id' => $this->customs->id,
        ]);
        $item = $declaration->items()->create([
            'company_id' => $this->company->id,
            'item_number' => 1,
            'hs_code' => '8471302000',
            'currency_code' => 'USD',
            'is_pph22_exempt' => true,
            'exemption_reason' => 'Hibah bencana.',
            'exemption_document_id' => $docId,
            'pph22_category' => 'bebas_pasal_4',
            'pph22_rate' => 0,
            'pph22_amount' => 0,
        ]);

        return [$declaration, $item];
    }

    #[Test]
    public function migrasi_mengganti_kolom_teks_bebas_dengan_fk(): void
    {
        $this->assertTrue(Schema::hasColumn('customs_items', 'exemption_document_id'));
        $this->assertFalse(Schema::hasColumn('customs_items', 'exemption_document_ref'));
    }

    #[Test]
    public function pembebasan_dengan_dokumen_job_lain_ditolak(): void
    {
        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [$this->itemPembebasan(['exemption_document_id' => $this->docJobLain->id])],
        ]);

        $response->assertSessionHasErrors(['items.0.exemption_document_id']);
        $this->assertDatabaseCount('customs_declarations', 0);
    }

    #[Test]
    public function pembebasan_dengan_dokumen_tanpa_file_ditolak(): void
    {
        // Baris checklist yang belum pernah diupload (file_path null).
        // Tipe dokumen berbeda dari doc pendukung (unique job+type).
        $docTanpaFile = $this->buatDokumen($this->job, 'belum-ada.pdf', false, JobDocument::TYPE_PACKING_LIST);

        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [$this->itemPembebasan(['exemption_document_id' => $docTanpaFile->id])],
        ]);

        $response->assertSessionHasErrors(['items.0.exemption_document_id']);
        $this->assertDatabaseCount('customs_declarations', 0);
    }

    #[Test]
    public function pembebasan_dengan_dokumen_yang_filenya_hilang_ditolak(): void
    {
        // file_path terisi tapi file sudah tidak ada di disk.
        // Tipe dokumen berbeda dari doc pendukung (unique job+type).
        $docHilang = JobDocument::query()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'phase' => JobDocument::PHASE_PRA_FILING,
            'document_type' => JobDocument::TYPE_BL,
            'status' => JobDocument::STATUS_DIUPLOAD,
            'file_path' => "documents/{$this->company->id}/{$this->job->id}/sudah-dihapus.pdf",
            'file_name' => 'sudah-dihapus.pdf',
            'uploaded_by_user_id' => $this->customs->id,
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [$this->itemPembebasan(['exemption_document_id' => $docHilang->id])],
        ]);

        $response->assertSessionHasErrors(['items.0.exemption_document_id']);
        $this->assertDatabaseCount('customs_declarations', 0);
    }

    #[Test]
    public function referensi_teks_bebas_tidak_lagi_diterima(): void
    {
        // Field lama dikirim tanpa FK — harus ditolak (bukan diam-diam disimpan).
        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [$this->itemPembebasan(['exemption_document_ref' => 'SK-123/MK/2026'])],
        ]);

        $response->assertSessionHasErrors(['items.0.exemption_document_id']);
        $this->assertDatabaseCount('customs_declarations', 0);
    }

    #[Test]
    public function approve_memblokir_draf_lama_dengan_dokumen_job_lain(): void
    {
        [$declaration] = $this->buatDrafLamaDenganPembebasan($this->docJobLain->id);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->fresh()->status);
    }

    #[Test]
    public function approve_memblokir_draf_lama_yang_dokumennya_hilang(): void
    {
        // Dokumen sempat diupload saat draf dibuat, lalu filenya hilang.
        // Tipe dokumen berbeda dari doc pendukung (unique job+type).
        $doc = $this->buatDokumen($this->job, 'akan-hilang.pdf', true, JobDocument::TYPE_DO);
        [$declaration] = $this->buatDrafLamaDenganPembebasan($doc->id);
        Storage::disk('local')->delete($doc->file_path);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->fresh()->status);
    }

    #[Test]
    public function approve_menerima_draf_dengan_dokumen_valid(): void
    {
        [$declaration] = $this->buatDrafLamaDenganPembebasan($this->docPendukung->id);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(CustomsDeclaration::STATUS_APPROVED, $declaration->fresh()->status);
    }

    #[Test]
    public function kalkulator_menolak_pembebasan_tanpa_id_dokumen(): void
    {
        $this->expectException(ValidationException::class);

        (new PibCalculator)->calculateItem([
            'hs_code' => '8471302000',
            'currency_code' => 'USD',
            'fob_valas' => 10000,
            'freight_valas' => 500,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 10,
            'is_pph22_exempt' => true,
            'exemption_reason' => 'Hibah bencana.',
            // sengaja tanpa exemption_document_id
        ], 'API-U');
    }
}
