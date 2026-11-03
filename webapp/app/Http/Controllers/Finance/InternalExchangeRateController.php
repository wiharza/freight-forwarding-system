<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\InternalExchangeRate;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InternalExchangeRateController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();

        $rates = InternalExchangeRate::query()
            ->with('creator:id,name')
            ->orderByDesc('rate_date')
            ->orderBy('currency_code')
            ->take(60)
            ->get();

        $currencies = Currency::query()
            ->where('is_active', true)
            ->where('code', '!=', 'IDR')
            ->get();

        $todayRates = InternalExchangeRate::query()
            ->where('rate_date', $today)
            ->get()
            ->keyBy('currency_code');

        return Inertia::render('finance/exchange-rates', [
            'rates' => $rates,
            'activeCurrencies' => $currencies,
            'todayRates' => $todayRates,
            'today' => $today,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rate_date' => ['required', 'date'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'rate_to_idr' => ['required', 'numeric', 'min:0.0001'],
            'cutoff_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ]);

        // Normalisasi H:i -> H:i:s (Laravel date_format bersifat AND, bukan OR)
        if (! empty($data['cutoff_time']) && strlen($data['cutoff_time']) === 5) {
            $data['cutoff_time'] .= ':00';
        }

        $currency = Currency::query()->where('code', $data['currency_code'])->first();
        if (! $currency || ! $currency->is_active) {
            return redirect()->back()->withErrors([
                'currency_code' => 'Mata uang tidak aktif ditolak (Q24, §6.6).',
            ]);
        }

        $data['created_by_user_id'] = $request->user()->id;

        $rate = InternalExchangeRate::query()->updateOrCreate(
            [
                'company_id' => $request->user()->company_id,
                'rate_date' => $data['rate_date'],
                'currency_code' => strtoupper($data['currency_code']),
            ],
            $data
        );

        Audit::record('internal_rate.saved', $rate, $data, 'Penetapan kurs internal resmi');

        return redirect()->back();
    }
}
