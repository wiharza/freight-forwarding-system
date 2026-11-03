<?php

namespace App\Http\Controllers;

use App\Models\CustomsDeclaration;
use App\Models\CustomsItem;
use App\Models\FtaTariff;
use App\Models\Job;
use App\Models\TaxExchangeRate;
use App\Services\PibCalculator;
use App\Support\Audit;
use App\Support\FormatRules;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Closure;

class PibCalculatorController extends Controller
{
    public function show(Job $job, PibCalculator $calculator): Response
    {
        Gate::authorize('view', $job);

        $declaration = CustomsDeclaration::query()
            ->where('job_id', $job->id)
            ->with(['items', 'creator:id,name', 'approver:id,name'])
            ->latest('version')
            ->first();

        $activeTaxRate = TaxExchangeRate::resolveRate('USD', now());

        return Inertia::render('calculator/pib', [
            'job' => $job->load(['importir', 'shippingLine']),
            'declaration' => $declaration,
            'activeTaxRate' => $activeTaxRate,
            'ftaSchemes' => array_keys(FtaTariff::SCHEMES),
            'canCalculate' => auth()->user()->can('calculator_pib') || auth()->user()->hasRole('admin'),
            'canApprove' => auth()->user()->can('approve_snapshot') || auth()->user()->hasRole('admin'),
        ]);
    }

    public function storeDraft(Request $request, Job $job, PibCalculator $calculator): RedirectResponse
    {
        Gate::authorize('calculator_pib');

        $data = $request->validate([
            'incoterm' => FormatRules::incotermRules(), // DQ-21 (data keterangan, tidak mengubah DPP — Q28)
            'items' => ['required', 'array', 'min:1'],
            'items.*.hs_code' => FormatRules::hsCodeRules(), // DQ-20
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.currency_code' => ['required', 'string', 'size:3'],
            'items.*.fob_valas' => ['required', 'numeric', 'min:0'],
            'items.*.freight_valas' => ['required', 'numeric', 'min:0'],
            'items.*.insurance_valas' => ['nullable', 'numeric', 'min:0'],
            'items.*.insurance_basis' => ['required', 'string'],
            'items.*.kurs_pajak_rate' => ['required', 'numeric', 'min:0'],
            'items.*.is_ppn_luxury' => ['boolean'],
            'items.*.is_pph22_exempt' => ['boolean'],
            'items.*.exemption_reason' => ['nullable', 'string'],
            'items.*.pungutan_lain_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Normalisasi kode HS: buang titik/spasi → simpan digit murni
        $data['items'] = collect($data['items'])
            ->map(fn (array $item) => array_merge($item, [
                'hs_code' => FormatRules::normalizeHsCode($item['hs_code']),
            ]))
            ->all();

        $statusApi = $job->importir->status_api ?? 'API-U';
        $ftaScheme = $job->claim_fta ? $job->fta_scheme : null;

        // Eksekusi kalkulasi v2
        $calc = $calculator->calculateDeclaration($data['items'], $statusApi, $ftaScheme, now());

        $companyId = $request->user()->company_id;
        $kmk = TaxExchangeRate::resolveRate('USD', now())?->kmk_number ?? 'KMK-MANUAL';

        // Buat draf deklarasi pabean
        $declaration = CustomsDeclaration::query()->create([
            'company_id' => $companyId,
            'job_id' => $job->id,
            'pib_number' => 'PIB-DRAFT-' . $job->job_number,
            'version' => 1,
            'status' => CustomsDeclaration::STATUS_DRAFT,
            'kmk_number' => $kmk,
            'incoterm' => $data['incoterm'] ?? null,
            'total_cif_valas' => $calc['total_cif_valas'],
            'total_cif_idr' => $calc['total_cif_idr'],
            'total_bm' => $calc['total_bm'],
            'total_ppn' => $calc['total_ppn'],
            'total_pph22' => $calc['total_pph22'],
            'total_ppnbm' => $calc['total_ppnbm'],
            'total_pungutan_lain' => $calc['total_pungutan_lain'],
            'total_pungutan' => $calc['total_pungutan'],
            'created_by_user_id' => $request->user()->id,
        ]);

        // Simpan setiap seri barang
        foreach ($calc['items'] as $item) {
            $declaration->items()->create(array_merge($item, ['company_id' => $companyId]));
        }

        Audit::record('customs_declaration.draft_saved', $declaration, [
            'total_pungutan' => $declaration->total_pungutan,
            'items_count' => count($calc['items']),
        ], 'Penyimpanan draf Kalkulator PIB v2');

        return redirect()->back();
    }

    /**
     * Setujui draf PIB → menjadi Snapshot PIB (Tiket 11, ADR 0004):
     * satu-satunya sumber Blok (a) pungutan negara di Invoice.
     * Maker-checker ditegakkan per user ID (ADR 0006).
     */
    public function approve(Request $request, Job $job, CustomsDeclaration $declaration): RedirectResponse
    {
        Gate::authorize('approve_snapshot');

        \App\Support\MakerChecker::assertApproverDiffers($request->user(), $declaration);

        $declaration->update([
            'status' => CustomsDeclaration::STATUS_APPROVED,
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
        ]);

        Audit::record('customs_declaration.approved', $declaration, [
            'version' => $declaration->version,
            'total_pungutan' => $declaration->total_pungutan,
        ], 'Draf PIB disetujui menjadi Snapshot PIB — acuan invoice Blok (a)');

        return redirect()->back();
    }

    public function taxRatesIndex(): Response
    {
        $rates = TaxExchangeRate::query()
            ->orderByDesc('valid_from')
            ->take(50)
            ->get();

        return Inertia::render('admin/master/tax-rates', [
            'rates' => $rates,
        ]);
    }

    public function storeTaxRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kmk_number' => ['required', 'string', 'max:50'],
            // DQ-22: periode NDPBM selalu Rabu s.d. Selasa (PV-3)
            'valid_from' => [
                'required', 'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ((int) date('N', strtotime((string) $value)) !== 3) {
                        $fail('Periode Kurs Pajak NDPBM selalu dimulai hari Rabu (PV-3).');
                    }
                },
            ],
            'valid_to' => [
                'required', 'date', 'after_or_equal:valid_from',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $from = strtotime((string) $request->input('valid_from'));
                    $to = strtotime((string) $value);
                    if ($from !== false && $to !== false && ($to - $from) !== 6 * 86400) {
                        $fail('Periode Kurs Pajak berlaku tepat 7 hari: Rabu s.d. Selasa (PV-3).');
                    }
                },
            ],
            'currency_code' => ['required', 'string', 'size:3'],
            'rate_to_idr' => ['required', 'numeric', 'min:0.0001'],
            'source' => ['required', 'string'],
        ]);

        $companyId = $request->user()->company_id;

        $rate = TaxExchangeRate::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'kmk_number' => $data['kmk_number'],
                'currency_code' => strtoupper($data['currency_code']),
                'valid_from' => $data['valid_from'],
            ],
            $data
        );

        Audit::record('tax_rate.saved', $rate, $data, 'Penetapan Kurs Pajak Kemenkeu');

        return redirect()->back();
    }
}
