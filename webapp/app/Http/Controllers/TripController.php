<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Location;
use App\Models\Trip;
use App\Models\Vendor;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $trips = Trip::query()
            ->with(['vendor:id,name', 'job:id,job_number', 'containers:id,job_id,size,type,container_number'])
            ->orderByDesc('scheduled_date')
            ->paginate(20);

        return Inertia::render('trips/index', [
            'trips' => $trips,
            'canCreate' => $user->can('job_assignment'),
        ]);
    }

    public function create(): Response
    {
        $jobId = request()->query('job_id');

        return Inertia::render('trips/create', [
            'jobId' => $jobId,
            'vendors' => Vendor::query()->where('company_id', request()->user()->company_id)->get(['id', 'name', 'vendor_type']),
            // Kontainer ikut dimuat agar form bisa memilih kontainer per job
            'jobs' => $jobId ? collect() : Job::query()
                ->with('containers:id,job_id,size,type,container_number')
                ->where('company_id', request()->user()->company_id)
                ->orderByDesc('id')
                ->get(['id', 'job_number']),
            'containerTypes' => ['20', '40', '45'],
            'locations' => Location::query()->where('company_id', request()->user()->company_id)->get(['id', 'name', 'code', 'type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'job_id' => ['required', 'exists:jobs,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'trip_number' => ['required', 'string', 'max:50'],
            'scheduled_date' => ['required', 'date'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:50'],
            'vehicle_number' => ['nullable', 'string', 'max:20'],
            'origin_location_id' => ['nullable', 'exists:locations,id'],
            'destination_location_id' => ['nullable', 'exists:locations,id'],
            'container_ids' => ['required', 'array', 'min:1'],
            // HARDENING 2026-11-04: kontainer HARUS milik Job trip ini.
            'container_ids.*' => [Rule::exists('containers', 'id')->where('job_id', $request->input('job_id'))],
            'notes' => ['nullable', 'string'],
        ]);

        $job = Job::query()->findOrFail($data['job_id']);
        Gate::authorize('job_assignment');
        // HARDENING 2026-11-04: pembuat trip juga harus punya akses ke Job-nya.
        Gate::authorize('view', $job);

        $trip = Trip::query()->create([
            'company_id' => $request->user()->company_id,
            'job_id' => $data['job_id'],
            'vendor_id' => $data['vendor_id'],
            'trip_number' => $data['trip_number'],
            'scheduled_date' => $data['scheduled_date'],
            'driver_name' => $data['driver_name'] ?? null,
            'driver_phone' => $data['driver_phone'] ?? null,
            'vehicle_number' => $data['vehicle_number'] ?? null,
            'origin_location_id' => $data['origin_location_id'] ?? null,
            'destination_location_id' => $data['destination_location_id'] ?? null,
            'status' => Trip::STATUS_ASSIGNED,
            'created_by_user_id' => $request->user()->id,
        ]);

        // Sync containers dengan urutan sequence
        foreach ($data['container_ids'] as $index => $cid) {
            $trip->containers()->attach($cid, ['sequence' => $index + 1]);
        }

        Audit::record('trip.created', $trip, ['job_number' => $trip->job->job_number], 'Penugasan trip trucking');

        return redirect()->route('trips.show', $trip->id);
    }

    public function show(Trip $trip): Response
    {
        // HARDENING 2026-11-04: sebelumnya tanpa Gate — siapa pun yang login
        // bisa membuka detail trip (dan SJ + vendor invoice di dalamnya).
        Gate::authorize('view', $trip);

        $trip->load([
            'job.importir',
            'job.shippingLine',
            'vendor',
            'origin',
            'destination',
            'containers',
            'suratJalan',
            'vendorInvoices',
        ]);

        return Inertia::render('trips/show', [
            'trip' => $trip,
            'canPrintSJ' => true,
        ]);
    }

    public function updateStatus(Request $request, Trip $trip): RedirectResponse
    {
        // HARDENING 2026-11-04: sebelumnya tanpa Gate.
        Gate::authorize('view', $trip);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                Trip::STATUS_ASSIGNED,
                Trip::STATUS_CONFIRMED,
                Trip::STATUS_IN_TRANSIT,
                Trip::STATUS_DELIVERED,
                Trip::STATUS_CANCELLED,
            ])],
        ]);

        $oldStatus = $trip->status;
        $trip->update($data);

        if ($data['status'] === Trip::STATUS_DELIVERED && $oldStatus !== Trip::STATUS_DELIVERED) {
            $trip->actual_arrival = now();
            $trip->save();
        }

        Audit::record('trip.status_changed', $trip, [
            'old_status' => $oldStatus,
            'new_status' => $trip->status,
        ], 'Perubahan status trip trucking');

        return redirect()->back();
    }

    public function printSuratJalan(Trip $trip): RedirectResponse
    {
        // HARDENING 2026-11-04: sebelumnya tanpa Gate.
        Gate::authorize('view', $trip);

        // Generate PDF Surat Jalan - akan diimplementasikan dengan DomPDF atau Browsershot
        // Untuk sekarang redirect ke halaman cetak
        return redirect()->route('trips.show', $trip);
    }
}
