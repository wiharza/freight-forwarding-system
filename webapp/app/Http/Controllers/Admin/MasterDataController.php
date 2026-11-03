<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContainerType;
use App\Models\Currency;
use App\Models\Importir;
use App\Models\Location;
use App\Models\ShippingLine;
use App\Models\Vendor;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    public function importirs(): Response
    {
        return Inertia::render('admin/master/importirs', [
            'items' => Importir::query()->orderBy('name')->get(),
        ]);
    }

    public function storeImportir(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'npwp' => [
                'required', 'string', 'max:20',
                Rule::unique('importirs')->where('company_id', app(\App\Support\CompanyContext::class)->id()),
            ],
            'nib' => ['nullable', 'string', 'max:50'],
            'status_api' => ['required', Rule::in(['API-U', 'API-P', 'NON-API'])],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:255'],
        ], [
            'npwp.unique' => 'Importir dengan NPWP ini sudah terdaftar (dedup NPWP, §1.7).',
        ]);

        $importir = Importir::query()->create($data);
        Audit::record('importir.created', $importir, ['npwp' => $importir->npwp]);

        return redirect()->back();
    }

    public function shippingLines(): Response
    {
        return Inertia::render('admin/master/shipping-lines', [
            'items' => ShippingLine::query()->orderBy('name')->get(),
        ]);
    }

    public function storeShippingLine(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $line = ShippingLine::query()->create($data);
        Audit::record('shipping_line.created', $line);

        return redirect()->back();
    }

    public function vendors(): Response
    {
        return Inertia::render('admin/master/vendors', [
            'items' => Vendor::query()->orderBy('name')->get(),
        ]);
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', Rule::in([Vendor::TYPE_EKSTERNAL, Vendor::TYPE_INTERNAL])],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        $vendor = Vendor::query()->create($data);
        Audit::record('vendor.created', $vendor);

        return redirect()->back();
    }

    public function locations(): Response
    {
        return Inertia::render('admin/master/locations', [
            'items' => Location::query()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([Location::TYPE_PORT, Location::TYPE_DEPOT, Location::TYPE_WAREHOUSE])],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $loc = Location::query()->create($data);
        Audit::record('location.created', $loc);

        return redirect()->back();
    }

    public function containerTypes(): Response
    {
        return Inertia::render('admin/master/container-types', [
            'items' => ContainerType::query()->orderBy('size')->orderBy('type')->get(),
        ]);
    }

    public function storeContainerType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'size' => ['required', 'string', 'max:10'],
            'type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $ct = ContainerType::query()->create($data);
        Audit::record('container_type.created', $ct);

        return redirect()->back();
    }

    public function currencies(): Response
    {
        return Inertia::render('admin/master/currencies', [
            'items' => Currency::query()->orderBy('code')->get(),
        ]);
    }

    public function updateCurrency(Request $request, Currency $currency): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $currency->update($data);
        Audit::record('currency.updated', $currency, $data);

        return redirect()->back();
    }
}
