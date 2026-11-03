<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Job;
use App\Models\SuratJalan;
use App\Models\Trip;
use App\Models\VendorInvoice;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SuratJalanController extends Controller
{
    public function create(Trip $trip): \Inertia\Response
    {
        Gate::authorize('view', $trip);

        $trip->load(['job.importir', 'vendor', 'containers', 'origin', 'destination']);

        return Inertia::render('surat-jalan/create', [
            'trip' => $trip,
        ]);
    }

    public function store(Request $request, Trip $trip): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('view', $trip);

        $data = $request->validate([
            'sj_number' => ['required', 'string', 'max:50'],
            'sj_date' => ['required', 'date'],
            'driver_name' => ['required', 'string', 'max:255'],
            'driver_phone' => ['required', 'string', 'max:50'],
            'vehicle_number' => ['required', 'string', 'max:20'],
            'origin_address' => ['required', 'string', 'max:500'],
            'destination_address' => ['required', 'string', 'max:500'],
            'containers_info' => ['required', 'array', 'min:1'],
            'containers_info.*.container_number' => ['required', 'string'],
            'containers_info.*.seal_number' => ['nullable', 'string'],
            'containers_info.*.gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sj = SuratJalan::query()->create([
            'company_id' => $request->user()->company_id,
            'trip_id' => $trip->id,
            'sj_number' => $data['sj_number'],
            'sj_date' => $data['sj_date'],
            'driver_name' => $data['driver_name'],
            'driver_phone' => $data['driver_phone'],
            'vehicle_number' => $data['vehicle_number'],
            'origin_address' => $data['origin_address'],
            'destination_address' => $data['destination_address'],
            'containers_info' => $data['containers_info'],
            'status' => SuratJalan::STATUS_DRAFT,
            'created_by_user_id' => $request->user()->id,
        ]);

        Audit::record('surat_jalan.created', $sj, $data, 'Pembuatan Surat Jalan');

        return redirect()->route('surat-jalan.show', $sj->id);
    }

    public function show(SuratJalan $sj): \Inertia\Response
    {
        $sj->load(['trip.job.importir', 'trip.vendor', 'trip.containers', 'trip.origin', 'trip.destination']);

        return Inertia::render('surat-jalan/show', [
            'sj' => $sj,
        ]);
    }

    public function sign(Request $request, \App\Models\SuratJalan $sj): \Illuminate\Http\RedirectResponse
    {
        $sj->update([
            'status' => \App\Models\SuratJalan::STATUS_SIGNED,
            'signed_by_user_id' => $request->user()->id,
            'pod_signed_at' => now(),
        ]);

        \App\Support\Audit::record('surat_jalan.signed', $sj, [], 'Surat Jalan ditandatangani sebagai POD');

        return redirect()->back();
    }

    public function uploadPod(Request $request, \App\Models\SuratJalan $sj): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20MB
        ]);

        $path = $request->file('file')->store("pods/{$sj->company_id}", 'public');

        $sj->update([
            'pod_file_path' => $path,
            'status' => SuratJalan::STATUS_SIGNED,
            'pod_signed_at' => now(),
        ]);

        Audit::record('surat_jalan.pod_uploaded', $sj, ['file_name' => $request->file('file')->getClientOriginalName()]);

        return redirect()->back();
    }
}