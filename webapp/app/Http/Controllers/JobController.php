<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\ContainerType;
use App\Models\FtaTariff;
use App\Models\Importir;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\Location;
use App\Models\Role;
use App\Models\ShippingLine;
use App\Models\User;
use App\Models\Vessel;
use App\Support\Audit;
use App\Support\FormatRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $status = $request->query('status');
        $problemFlag = $request->query('problem_flag');
        $importirId = $request->query('importir_id');
        $search = $request->query('q');

        $query = Job::query()
            ->visibleTo($user)
            ->with(['importir:id,name', 'shippingLine:id,code,name', 'containers:id,job_id,size,type,container_number'])
            ->orderByDesc('id');

        if ($status) {
            $query->where('cached_status', $status);
        }
        if ($problemFlag) {
            $query->where('problem_flag', $problemFlag);
        }
        if ($importirId) {
            $query->where('importir_id', $importirId);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('bl_number', 'like', "%{$search}%")
                  ->orWhere('vessel_name', 'like', "%{$search}%");
            });
        }

        return Inertia::render('jobs/index', [
            'jobs' => $query->paginate(20)->withQueryString(),
            'importirs' => Importir::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'status' => $status,
                'problem_flag' => $problemFlag,
                'importir_id' => $importirId,
                'q' => $search,
            ],
            'canCreate' => $user->can('job_entry') || $user->hasRole('admin'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('job_entry');

        return Inertia::render('jobs/create', [
            'importirs' => Importir::query()->orderBy('name')->get(['id', 'name', 'npwp', 'status_api']),
            'shippingLines' => ShippingLine::query()->orderBy('name')->get(['id', 'code', 'name']),
            'ports' => Location::query()->where('type', Location::TYPE_PORT)->orderBy('name')->get(['id', 'code', 'name']),
            'containerTypes' => ContainerType::query()->orderBy('size')->get(['id', 'size', 'type']),
            'vessels' => Vessel::query()->with('shippingLine:id,code,name')->orderBy('name')->get(['id', 'name', 'imo_number', 'call_sign', 'shipping_line_id']),
            'ftaSchemes' => array_keys(FtaTariff::SCHEMES),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('job_entry');

        // Batasan keras §1.2, Q2, Q10: v1 hanya FCL, LCL ditolak
        if ($request->has('is_fcl') && ! $request->boolean('is_fcl')) {
            throw ValidationException::withMessages([
                'is_fcl' => 'Sistem v1 hanya melayani FCL. LCL di luar scope (§1.2, Q2, Q10).',
            ]);
        }

        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'importir_id' => ['required', 'exists:importirs,id'],
            'shipping_line_id' => ['required', 'exists:shipping_lines,id'],
            'bl_number' => ['required', 'string', 'max:50'],
            'bl_date' => ['required', 'date'],
            'pol_id' => ['nullable', 'exists:locations,id'],
            'pod_id' => ['required', 'exists:locations,id'],
            'vessel_id' => ['nullable', Rule::exists('vessels', 'id')->where('company_id', $companyId)],
            'vessel_name' => ['nullable', 'string', 'max:100'],
            'voyage_number' => ['nullable', 'string', 'max:50'],
            'eta' => ['nullable', 'date'],
            'ata' => ['nullable', 'date'],
            'claim_fta' => ['boolean'],
            'fta_scheme' => ['nullable', 'string', 'max:20'],
            'referensi_excel_lama' => ['nullable', 'string', 'max:100'],
            'tanggal_cutover' => ['nullable', 'date'],
            'containers' => ['required', 'array', 'min:1'],
            'containers.*.container_number' => FormatRules::containerNumberRules(), // DQ-19
            'containers.*.size' => ['required', 'string', 'max:5'],
            'containers.*.type' => ['required', 'string', 'max:30'],
            'containers.*.seal_number' => ['nullable', 'string', 'max:50'],
            'containers.*.gross_weight_kg' => ['required', 'numeric', 'min:0'],
        ]);

        // DQ-22: pasangan (size, type) wajib terdaftar di master jenis kontainer
        foreach ($data['containers'] as $i => $c) {
            $exists = ContainerType::query()
                ->where('size', (string) $c['size'])
                ->where('type', $c['type'])
                ->exists();
            if (! $exists) {
                throw ValidationException::withMessages([
                    "containers.{$i}.type" => "Kombinasi ukuran {$c['size']}' dan tipe {$c['type']} tidak ada di master jenis kontainer.",
                ]);
            }
        }

        // DQ-18: pilih kapal dari master → nama di-snapshot ke job; master boleh
        // berubah tanpa menulis ulang riwayat job.
        $vesselName = $data['vessel_name'] ?? null;
        if (! empty($data['vessel_id'])) {
            $vesselName = Vessel::query()->findOrFail($data['vessel_id'])->name;
        }

        $jobNumber = 'JOB-' . date('Ym') . '-' . str_pad((string) (Job::query()->count() + 1), 4, '0', STR_PAD_LEFT);

        $job = Job::query()->create([
            'job_number' => $jobNumber,
            'importir_id' => $data['importir_id'],
            'shipping_line_id' => $data['shipping_line_id'],
            'bl_number' => $data['bl_number'],
            'bl_date' => $data['bl_date'],
            'pol_id' => $data['pol_id'] ?? null,
            'pod_id' => $data['pod_id'],
            'vessel_id' => $data['vessel_id'] ?? null,
            'vessel_name' => $vesselName,
            'voyage_number' => $data['voyage_number'] ?? null,
            'eta' => $data['eta'] ?? null,
            'ata' => $data['ata'] ?? null,
            'is_fcl' => true,
            'claim_fta' => (bool) ($data['claim_fta'] ?? false),
            'fta_scheme' => $data['fta_scheme'] ?? null,
            'referensi_excel_lama' => $data['referensi_excel_lama'] ?? null,
            'tanggal_cutover' => $data['tanggal_cutover'] ?? null,
            'cached_status' => Job::STATUS_DRAFT,
            'created_by_user_id' => $request->user()->id,
        ]);

        // Simpan 1..N kontainer (nomor dinormalisasi: trim, buang pemisah, uppercase)
        foreach ($data['containers'] as $c) {
            $job->containers()->create([
                'company_id' => $companyId,
                'container_number' => FormatRules::normalizeContainerNumber($c['container_number']),
                'size' => $c['size'],
                'type' => $c['type'],
                'seal_number' => $c['seal_number'] ?? null,
                'gross_weight_kg' => $c['gross_weight_kg'],
            ]);
        }

        // Buat checklist dokumen 3 fase
        $job->initializeChecklist();

        Audit::record('job.created', $job, ['job_number' => $job->job_number], 'Pembuatan order Job impor baru');

        return redirect()->route('jobs.show', $job->id);
    }

    public function show(Job $job, Request $request, \App\Services\DemurrageCalculator $calculator): Response
    {
        Gate::authorize('view', $job);

        $job->load([
            'importir',
            'shippingLine',
            'vessel:id,name,imo_number,call_sign',
            'pol',
            'pod',
            'containers',
            'documents.uploader:id,name',
            'documents.verifier:id,name',
            'milestones' => fn ($q) => $q->orderBy('occurred_at'),
            'milestones.container',
            'milestones.creator:id,name',
            'milestones.verifier:id,name',
            'fields:id,name',
            'creator:id,name',
        ]);

        $demurrageData = $calculator->calculateForJob($job);

        $fieldUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::FIELD))
            ->get(['id', 'name']);

        return Inertia::render('jobs/show', [
            'job' => $job,
            'demurrage' => $demurrageData,
            'fieldUsers' => $fieldUsers,
            'canAssignField' => $request->user()->can('job_assignment'),
            'canVerifyDoc' => $request->user()->can('verify_milestone'),
        ]);
    }

    public function uploadDocument(Request $request, Job $job, JobDocument $document): RedirectResponse
    {
        Gate::authorize('view', $job);

        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20MB
            'metadata' => ['nullable', 'array'],
        ]);

        $path = $request->file('file')->store("documents/{$job->company_id}/{$job->id}", 'public');

        $document->update([
            'file_path' => $path,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'status' => JobDocument::STATUS_DIUPLOAD,
            'uploaded_by_user_id' => $request->user()->id,
            'uploaded_at' => now(),
            'metadata' => $request->input('metadata'),
        ]);

        Audit::record('job_document.uploaded', $document, ['file_name' => $document->file_name]);

        return redirect()->back();
    }

    public function verifyDocument(Request $request, Job $job, JobDocument $document): RedirectResponse
    {
        Gate::authorize('verify_milestone');

        $notes = $request->input('notes');
        $document->markVerified($request->user(), $notes);

        return redirect()->back();
    }

    public function updateProblemFlag(Request $request, Job $job): RedirectResponse
    {
        $data = $request->validate([
            'problem_flag' => ['nullable', Rule::in(['Hold', 'Dispute', 'Dokumen Bermasalah'])],
            'reason' => ['required_with:problem_flag', 'nullable', 'string'],
        ]);

        $oldFlag = $job->problem_flag;
        $job->update(['problem_flag' => $data['problem_flag']]);

        Audit::record('job.problem_flag_updated', $job, [
            'old' => $oldFlag,
            'new' => $data['problem_flag'],
        ], $data['reason'] ?? 'Update problem flag');

        return redirect()->back();
    }

    public function assignField(Request $request, Job $job): RedirectResponse
    {
        Gate::authorize('job_assignment');

        $data = $request->validate([
            'field_user_id' => ['required', 'exists:users,id'],
        ]);

        $job->assignField($data['field_user_id'], $request->user());
        Audit::record('job.field_assigned', $job, ['field_user_id' => $data['field_user_id']]);

        return redirect()->back();
    }
}
