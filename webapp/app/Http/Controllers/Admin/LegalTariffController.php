<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FtaTariff;
use App\Models\LuxuryGood;
use App\Models\MfnTariff;
use App\Models\Pph22Rule;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LegalTariffController extends Controller
{
    public function mfn(Request $request): Response
    {
        $search = $request->query('q');

        $query = MfnTariff::query()->with(['creator:id,name', 'approver:id,name'])->orderBy('hs_code');
        if ($search) {
            $query->where('hs_code', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
        }

        return Inertia::render('admin/master/mfn-tariffs', [
            'items' => $query->paginate(30)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function storeMfn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hs_code' => ['required', 'string', 'max:12'],
            'description' => ['nullable', 'string', 'max:255'],
            'bm_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'verification_status' => ['required', Rule::in([MfnTariff::STATUS_VERIFIED, MfnTariff::STATUS_UNVERIFIED])],
            'legal_basis' => ['required', 'string', 'max:255'],
        ]);

        $data['created_by_user_id'] = $request->user()->id;
        $tariff = MfnTariff::query()->create($data);

        Audit::record('mfn_tariff.created', $tariff, $data, 'Penambahan pos tarif MFN');

        return redirect()->back();
    }

    public function approveMfn(Request $request, MfnTariff $tariff): RedirectResponse
    {
        $data = $request->validate([
            'legal_basis' => ['required', 'string'],
        ]);

        $tariff->approve($request->user(), $data['legal_basis']);

        return redirect()->back();
    }

    public function fta(Request $request): Response
    {
        $scheme = $request->query('scheme');
        $search = $request->query('q');

        $query = FtaTariff::query()->with(['creator:id,name', 'approver:id,name'])->orderBy('scheme_code')->orderBy('hs_code');
        if ($scheme) {
            $query->where('scheme_code', $scheme);
        }
        if ($search) {
            $query->where('hs_code', 'like', "%{$search}%");
        }

        return Inertia::render('admin/master/fta-tariffs', [
            'items' => $query->paginate(30)->withQueryString(),
            'schemes' => FtaTariff::SCHEMES,
            'selectedScheme' => $scheme,
            'search' => $search,
        ]);
    }

    public function storeFta(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scheme_code' => ['required', Rule::in(array_keys(FtaTariff::SCHEMES))],
            'hs_code' => ['required', 'string', 'max:12'],
            'fta_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'verification_status' => ['required', Rule::in([FtaTariff::STATUS_VERIFIED, FtaTariff::STATUS_UNVERIFIED])],
            'legal_basis' => ['required', 'string', 'max:255'],
        ]);

        $data['coo_type'] = FtaTariff::SCHEMES[$data['scheme_code']] ?? null;
        $data['created_by_user_id'] = $request->user()->id;

        $tariff = FtaTariff::query()->create($data);
        Audit::record('fta_tariff.created', $tariff, $data, 'Penambahan tarif preferensi FTA');

        return redirect()->back();
    }

    public function pph22(Request $request): Response
    {
        $search = $request->query('q');

        $query = Pph22Rule::query()->orderBy('hs_code');
        if ($search) {
            $query->where('hs_code', 'like', "%{$search}%");
        }

        return Inertia::render('admin/master/pph22-rules', [
            'items' => $query->paginate(50)->withQueryString(),
            'search' => $search,
            'totalRows' => Pph22Rule::query()->count(),
        ]);
    }

    public function luxury(Request $request): Response
    {
        return Inertia::render('admin/master/luxury-goods', [
            'items' => LuxuryGood::query()->orderBy('category')->orderBy('hs_code')->get(),
        ]);
    }
}
