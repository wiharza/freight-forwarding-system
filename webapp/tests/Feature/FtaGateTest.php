<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomsDeclaration;
use App\Models\FtaTariff;
use App\Models\Importir;
use App\Models\Job;
use App\Models\Location;
use App\Models\MfnTariff;
use App\Models\ShippingLine;
use App\Models\TaxExchangeRate;
use App\Models\User;
use App\Services\FtaGate;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes gerbang FTA (Tiket 10, Q19, §3.8): Form FTA sebagai Dokumen Asal,
 * validasi HS prefix 6 digit + negara asal/pelabuhan + masa berlaku,
 * override Customs Specialist (maker-checker, alasan + lampiran), dan
 * decision log MFN (file MFN now / delay) + referensi refund.
 *
 * Interpretasi T5 (belum diputuskan user): 6 digit cocok tetapi digit 7–8
 * berbeda = peringatan NON-BLOKIR (aturan kunci Q19 adalah prefix 6 digit).
 */
class FtaGateTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $cs;

    private User $customs;

    private User $customs2;

    private Importir $importir;

    private ShippingLine $line;

    private Location $pol;

    private Location $pod;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->customs = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs->roles()->sync([$this->roleId('customs_specialist')]);

        $this->customs2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs2->roles()->sync([$this->roleId('customs_specialist')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $this->line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $this->pol = Location::factory()->create([
            'company_id' => $this->company->id,
            'type' => Location::TYPE_PORT,
            'code' => 'CNSHA',
            'name' => 'Shanghai',
        ]);
        $this->pod = Location::factory()->create([
            'company_id' => $this->company->id,
            'type' => Location::TYPE_PORT,
            'code' => 'IDBLW',
            'name' => 'Belawan',
        ]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'pol_id' => $this->pol->id,
            'pod_id' => $this->pod->id,
            'claim_fta' => true,
            'fta_scheme' => 'ACFTA',
        ]);

        // Tarif FTA 5% (terverifikasi) + MFN 10% (terverifikasi) untuk HS 3901.10.
        FtaTariff::create([
            'company_id' => $this->company->id,
            'scheme_code' => 'ACFTA',
            'hs_code' => '3901.10.00',
            'fta_rate' => 0.05,
            'effective_from' => '2022-04-01',
            'verification_status' => FtaTariff::STATUS_VERIFIED,
        ]);
        MfnTariff::create([
            'company_id' => $this->company->id,
            'hs_code' => '3901.10.00',
            'description' => 'Polietilena',
            'bm_rate' => 0.10,
            'effective_from' => '2022-04-01',
            'verification_status' => MfnTariff::STATUS_VERIFIED,
        ]);

        app(CompanyContext::class)->set($this->company->id);

        // TEMUAN LANJUTAN (2026-11-05): kalkulator selalu resolve Kurs Pajak
        // dari baris resmi — draf-draf di file ini butuh baris USD 16000.
        TaxExchangeRate::query()->create([
            'kmk_number' => 'KMK-TEST-16000',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'currency_code' => 'USD',
            'rate_to_idr' => '16000',
            'source' => 'test',
        ]);
    }

    // ------------------------------------------------------------------
    // Happy path: Form valid + HS cocok → FTA rate + approve lolos
    // ------------------------------------------------------------------

    #[Test]
    public function form_valid_dan_hs_cocok_maka_tarif_fta_dan_approve_lolos(): void
    {
        $this->buatForm();

        $declaration = $this->buatDrafPib($this->job);

        $item = $declaration->items()->firstOrFail();
        $this->assertSame('fta', $item->bm_type);
        $this->assertEquals(0.05, (float) $item->bm_rate);
        // CIF = (10.000 + 500 + 10) × 16.000 = Rp168.160.000; BM 5% = Rp8.408.000.
        $this->assertEquals(8408000, (float) $item->bm_amount);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(CustomsDeclaration::STATUS_APPROVED, $declaration->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Blokir keras: HS tidak cocok / tanpa Form / kedaluwarsa / port /
    // negara asal — approve ditolak, status tetap draft.
    // ------------------------------------------------------------------

    #[Test]
    public function form_hs_tidak_cocok_maka_filing_diblokir(): void
    {
        $this->buatForm(['hs_code' => '847130']);

        $declaration = $this->buatDrafPib($this->job);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->fresh()->status);

        $evaluation = app(FtaGate::class)->evaluate($this->job->fresh(), $declaration->fresh());
        $this->assertSame(FtaGate::STATUS_DIBLOKIR, $evaluation['items'][0]['status']);
        $this->assertSame(FtaGate::STATUS_DIBLOKIR, $evaluation['overall']);
    }

    #[Test]
    public function tanpa_form_maka_filing_diblokir(): void
    {
        $declaration = $this->buatDrafPib($this->job);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->fresh()->status);
    }

    #[Test]
    public function form_kedaluwarsa_maka_filing_diblokir(): void
    {
        $this->buatForm(['valid_until' => '2026-10-01']);

        $declaration = $this->buatDrafPib($this->job);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->fresh()->status);
    }

    #[Test]
    public function port_of_loading_tidak_cocok_maka_filing_diblokir(): void
    {
        $this->buatForm(['port_of_loading' => 'CNSZX']);

        $declaration = $this->buatDrafPib($this->job);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->fresh()->status);
    }

    #[Test]
    public function negara_asal_tidak_cocok_maka_filing_diblokir(): void
    {
        $this->buatForm(['origin_country' => 'VN']);

        $declaration = $this->buatDrafPib($this->job);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Override: Customs Specialist lain + alasan + lampiran → filing lanjut
    // dan tercatat di audit trail; pembuat draf tidak boleh meng-override.
    // ------------------------------------------------------------------

    #[Test]
    public function override_oleh_customs_lain_membuka_filing_dan_diaudit(): void
    {
        $this->buatForm(['hs_code' => '847130']);

        $declaration = $this->buatDrafPib($this->job);
        $item = $declaration->items()->firstOrFail();

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.fta-override', ['job' => $this->job->id, 'declaration' => $declaration->id]),
            [
                'customs_item_id' => $item->id,
                'reason' => 'Form E diterbitkan ulang dengan HS 8471.30 — nomor Form sama, lampiran surat koreksi eksportir terlampir.',
                'attachment' => UploadedFile::fake()->create('koreksi-eksportir.pdf', 120, 'application/pdf'),
            ]
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fta_gate_overrides', [
            'customs_declaration_id' => $declaration->id,
            'customs_item_id' => $item->id,
            'created_by_user_id' => $this->customs2->id,
        ]);
        $this->assertDatabaseHas('audit_trails', ['action' => 'fta_gate.overridden']);

        $evaluation = app(FtaGate::class)->evaluate($this->job->fresh(), $declaration->fresh());
        $this->assertSame(FtaGate::STATUS_OVERRIDE, $evaluation['items'][0]['status']);

        $approve = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $approve->assertSessionHasNoErrors();
        $this->assertSame(CustomsDeclaration::STATUS_APPROVED, $declaration->fresh()->status);
    }

    #[Test]
    public function override_oleh_pembuat_draf_ditolak_maker_checker(): void
    {
        $this->buatForm(['hs_code' => '847130']);

        $declaration = $this->buatDrafPib($this->job);
        $item = $declaration->items()->firstOrFail();

        $response = $this->actingAs($this->customs)->post(
            route('jobs.calculator.fta-override', ['job' => $this->job->id, 'declaration' => $declaration->id]),
            [
                'customs_item_id' => $item->id,
                'reason' => 'Saya yang membuat draf ini.',
                'attachment' => UploadedFile::fake()->create('lampiran.pdf', 50, 'application/pdf'),
            ]
        );

        $response->assertSessionHasErrors(['maker_checker']);
        $this->assertDatabaseMissing('fta_gate_overrides', [
            'customs_declaration_id' => $declaration->id,
        ]);
    }

    #[Test]
    public function override_tanpa_lampiran_ditolak(): void
    {
        $this->buatForm(['hs_code' => '847130']);

        $declaration = $this->buatDrafPib($this->job);
        $item = $declaration->items()->firstOrFail();

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.fta-override', ['job' => $this->job->id, 'declaration' => $declaration->id]),
            [
                'customs_item_id' => $item->id,
                'reason' => 'Tanpa lampiran.',
            ]
        );

        $response->assertSessionHasErrors(['attachment']);
        $this->assertDatabaseMissing('fta_gate_overrides', [
            'customs_declaration_id' => $declaration->id,
        ]);
    }

    // ------------------------------------------------------------------
    // T5: 6 digit cocok, digit 7–8 berbeda → peringatan saja, filing lanjut,
    // dan peringatan tercatat di decision log.
    // ------------------------------------------------------------------

    #[Test]
    public function digit_7_8_berbeda_hanya_peringatan_dan_filing_lanjut(): void
    {
        // Form HS 39011000 vs PIB HS 3901109900: prefix 6 digit sama,
        // digit 7–8 berbeda (00 vs 99) → peringatan non-blokir (T5).
        // Baris tarif FTA 3901.10.99 ditambahkan supaya kalkulator menilai
        // seri ini bertarif FTA (lookup tarif memakai prefix 8 digit) —
        // tanpa baris itu seri akan jatuh ke MFN dan gerbang tidak berlaku.
        FtaTariff::create([
            'company_id' => $this->company->id,
            'scheme_code' => 'ACFTA',
            'hs_code' => '3901.10.99',
            'fta_rate' => 0.05,
            'effective_from' => '2022-04-01',
            'verification_status' => FtaTariff::STATUS_VERIFIED,
        ]);

        $this->buatForm(['hs_code' => '39011000']);

        $declaration = $this->buatDrafPib($this->job, '3901109900');

        $evaluation = app(FtaGate::class)->evaluate($this->job->fresh(), $declaration->fresh());
        $this->assertSame(FtaGate::STATUS_PERINGATAN, $evaluation['items'][0]['status']);
        $this->assertSame(FtaGate::STATUS_PERINGATAN, $evaluation['overall']);
        $this->assertContains('digits8_warning', $evaluation['items'][0]['checks']);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(CustomsDeclaration::STATUS_APPROVED, $declaration->fresh()->status);
        $this->assertDatabaseHas('fta_decisions', [
            'job_id' => $this->job->id,
            'customs_declaration_id' => $declaration->id,
            'decision_type' => 'warning_8digit',
        ]);
    }

    // ------------------------------------------------------------------
    // Form belum tersedia: keputusan "file MFN now" → draf dihitung ulang
    // MFN, klaim FTA Job dimatikan, filing lanjut; klaim ulang diblokir.
    // ------------------------------------------------------------------

    #[Test]
    public function tanpa_form_keputusan_mfn_now_maka_filing_mfn_dan_tercatat(): void
    {
        $declaration = $this->buatDrafPib($this->job);

        // Sebelum keputusan: draf masih bertarif FTA 5%.
        $this->assertSame('fta', $declaration->items()->firstOrFail()->bm_type);

        $response = $this->actingAs($this->customs)->post(
            route('jobs.calculator.fta-decision', ['job' => $this->job->id, 'declaration' => $declaration->id]),
            [
                'decision_type' => 'mfn_now',
                'reason' => 'Form E belum terbit; free time habis — file MFN sekarang, reclaim manual setelah Form terbit.',
                'risk_note' => 'Selisih BM MFN vs FTA Rp8.408.000.',
            ]
        );

        $response->assertSessionHasNoErrors();
        $this->assertFalse($this->job->fresh()->claim_fta);

        // Draf dihitung ulang ke MFN 10%: BM Rp16.816.000.
        $item = $declaration->fresh()->items()->firstOrFail();
        $this->assertSame('mfn', $item->bm_type);
        $this->assertEquals(0.10, (float) $item->bm_rate);
        $this->assertEquals(16816000, (float) $item->bm_amount);

        $this->assertDatabaseHas('fta_decisions', [
            'job_id' => $this->job->id,
            'customs_declaration_id' => $declaration->id,
            'decision_type' => 'mfn_now',
        ]);
        $this->assertDatabaseHas('audit_trails', ['action' => 'fta_decision.recorded']);

        $approve = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $approve->assertSessionHasNoErrors();
        $this->assertSame(CustomsDeclaration::STATUS_APPROVED, $declaration->fresh()->status);
    }

    #[Test]
    public function klaim_ulang_setelah_keputusan_mfn_diblokir(): void
    {
        $declaration = $this->buatDrafPib($this->job);

        $this->actingAs($this->customs)->post(
            route('jobs.calculator.fta-decision', ['job' => $this->job->id, 'declaration' => $declaration->id]),
            ['decision_type' => 'mfn_now', 'reason' => 'File MFN sekarang.']
        )->assertSessionHasNoErrors();

        // Klaim FTA dihidupkan lagi di Job (mis. lewat edit) — draf baru
        // tetap tidak boleh dihitung dengan klaim FTA untuk versi ini.
        // refresh() dulu: instans di memori masih menyimpan claim_fta=true
        // dari setUp, padahal keputusan MFN sudah mematikannya di DB.
        $this->job->refresh()->update(['claim_fta' => true]);

        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => '3901100000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'insurance_valas' => '10',
                'kurs_pajak_rate' => '16000',
            ]],
        ]);

        $response->assertSessionHasErrors(['claim_fta']);
    }

    #[Test]
    public function keputusan_delay_tidak_mengunci_mfn_dan_filing_tetap_diblokir_tanpa_form(): void
    {
        $declaration = $this->buatDrafPib($this->job);

        $response = $this->actingAs($this->customs)->post(
            route('jobs.calculator.fta-decision', ['job' => $this->job->id, 'declaration' => $declaration->id]),
            [
                'decision_type' => 'delay',
                'reason' => 'Menunggu Form E dari eksportir, estimasi terbit 2 hari lagi.',
            ]
        );

        $response->assertSessionHasNoErrors();
        $this->assertTrue($this->job->fresh()->claim_fta);
        $this->assertDatabaseHas('fta_decisions', [
            'customs_declaration_id' => $declaration->id,
            'decision_type' => 'delay',
        ]);

        // Tanpa Form, filing tetap diblokir meski sudah ada keputusan delay.
        $approve = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $approve->assertSessionHasErrors(['approval']);
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Job tanpa klaim FTA: approve tanpa Form apa pun.
    // ------------------------------------------------------------------

    #[Test]
    public function job_tanpa_klaim_fta_approve_tanpa_form(): void
    {
        $jobMfn = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'pol_id' => $this->pol->id,
            'pod_id' => $this->pod->id,
            'claim_fta' => false,
            'fta_scheme' => null,
        ]);

        $declaration = $this->buatDrafPib($jobMfn);

        $this->assertSame('mfn', $declaration->items()->firstOrFail()->bm_type);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $jobMfn->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(CustomsDeclaration::STATUS_APPROVED, $declaration->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Form FTA dengan lampiran → cermin ke checklist Dokumen Asal.
    // ------------------------------------------------------------------

    #[Test]
    public function form_dengan_lampiran_mencerminkan_dokumen_checklist_form_fta(): void
    {
        $response = $this->buatForm([], UploadedFile::fake()->create('form-e.pdf', 100, 'application/pdf'));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fta_forms', [
            'job_id' => $this->job->id,
            'form_number' => 'E24123456789',
        ]);
        $this->assertDatabaseHas('job_documents', [
            'job_id' => $this->job->id,
            'document_type' => 'form_fta',
            'status' => 'diupload',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buatDrafPib(Job $job, string $hsCode = '3901100000'): CustomsDeclaration
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

        return CustomsDeclaration::query()->where('job_id', $job->id)->latest('id')->firstOrFail();
    }

    private function buatForm(array $overrides = [], ?UploadedFile $file = null)
    {
        $payload = array_merge([
            'scheme_code' => 'ACFTA',
            'form_number' => 'E24123456789',
            'hs_code' => '390110',
            'origin_country' => 'CN',
            'port_of_loading' => 'CNSHA',
            'issued_at' => '2026-10-01',
            'valid_until' => '2027-10-01',
        ], $overrides);

        if ($file) {
            $payload['file'] = $file;
        }

        return $this->actingAs($this->cs)->post(route('jobs.fta-forms.store', $this->job->id), $payload);
    }
}
