<?php

namespace App\Http\Controllers;

use App\Models\VendorRoute;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VendorRouteController extends Controller
{
    public function index(): Response
    {
        $routes = VendorRoute::query()
            ->with(['vendor:id,name', 'origin:id,code,name', 'destination:id,code,name'])
            ->orderBy('effective_from')
            ->paginate(20);

        return Inertia::render('vendor-routes/index', [
            'routes' => $routes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'origin_location_id' => ['nullable', 'exists:locations,id'],
            'destination_location_id' => ['required', 'exists:locations,id'],
            'container_size' => ['required', Rule::in(['20', '40', '45'])],
            'base_price_usd' => ['required', 'numeric', 'min:0'],
            'include_toll' => ['boolean'],
            'include_unloading' => ['boolean'],
            'include_waiting' => ['boolean'],
            'waiting_rate_per_hour_usd' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $route = VendorRoute::query()->create([
            'company_id' => $request->user()->company_id,
            ...$data,
        ]);

        Audit::record('vendor_route.created', $route, $data, 'Penambahan rute vendor trucking');

        return redirect()->back();
    }

    public function destroy(VendorRoute $route): RedirectResponse
    {
        $route->delete();

        return redirect()->back();
    }
}
