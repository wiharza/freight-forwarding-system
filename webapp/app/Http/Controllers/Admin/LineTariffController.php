<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LineTariff;
use App\Models\ShippingLine;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LineTariffController extends Controller
{
    public function index(Request $request): Response
    {
        $lineId = $request->query('shipping_line_id');

        $lines = ShippingLine::query()->orderBy('name')->get();
        $selectedLine = $lineId ? ShippingLine::query()->find($lineId) : $lines->first();

        $tariffs = $selectedLine
            ? LineTariff::query()
                ->where('shipping_line_id', $selectedLine->id)
                ->with(['creator:id,name', 'approver:id,name'])
                ->orderBy('charge_type')
                ->orderBy('container_size')
                ->orderBy('day_from')
                ->get()
            : collect();

        return Inertia::render('admin/master/line-tariffs', [
            'lines' => $lines,
            'selectedLine' => $selectedLine,
            'tariffs' => $tariffs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipping_line_id' => ['required', 'exists:shipping_lines,id'],
            'charge_type' => ['required', Rule::in([LineTariff::TYPE_DEMURRAGE, LineTariff::TYPE_DETENTION])],
            'container_size' => ['required', Rule::in(['20', '40', '45'])],
            'day_from' => ['required', 'integer', 'min:1'],
            'day_to' => ['nullable', 'integer', 'gte:day_from'],
            'currency_code' => ['required', 'string', 'size:3'],
            'rate_per_day' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['status'] = LineTariff::STATUS_DRAFT;
        $data['created_by_user_id'] = $request->user()->id;

        $tariff = LineTariff::query()->create($data);
        Audit::record('line_tariff.created', $tariff, $data, 'Pengajuan tarif line baru (draft)');

        return redirect()->back();
    }

    public function approve(Request $request, LineTariff $tariff): RedirectResponse
    {
        $tariff->approve($request->user(), $request->input('notes'));

        return redirect()->back();
    }

    public function updateConfig(Request $request, ShippingLine $line): RedirectResponse
    {
        $data = $request->validate([
            'free_days_demurrage' => ['required', 'integer', 'min:0'],
            'free_days_detention' => ['required', 'integer', 'min:0'],
            'start_offset_demurrage' => ['required', Rule::in(['H+0', 'H+1'])],
            'start_offset_detention' => ['required', Rule::in(['H+0', 'H+1'])],
            'calendar_type_demurrage' => ['required', Rule::in(['calendar', 'working'])],
            'calendar_type_detention' => ['required', Rule::in(['calendar', 'working'])],
            'mode' => ['required', Rule::in(['terpisah', 'gabungan'])],
        ]);

        $line->update($data);
        Audit::record('shipping_line.config_updated', $line, $data, 'Pembaruan konfigurasi free time line');

        return redirect()->back();
    }
}
