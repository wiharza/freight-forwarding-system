<?php

namespace App\Http\Controllers;

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
use App\Services\DemurrageCalculator;
use App\Services\JobClose;
use App\Support\Audit;
use App\Support\FormatRules;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            // HARDENING 2026-11-04 (temuan #1, lintas-tenant): keempat FK ini
            // sebelumnya hanya dicek keberadaannya ('exists') tanpa scope
            // company — Job bisa terhubung ke importir/shipping line/
            // pelabuhan perusahaan lain. Sekarang di-scope ke company user,
            // sama seperti vessel_id di bawah.
            'importir_id' => ['required', Rule::exists('importirs', 'id')->where('company_id', $companyId)],
            'shipping_line_id' => ['required', Rule::exists('shipping_lines', 'id')->where('company_id', $companyId)],
            'bl_number' => ['required', 'string', 'max:50'],
            'bl_date' => ['required', 'date'],
            'pol_id' => ['nullable', Rule::exists('locations', 'id')->where('company_id', $companyId)],
            'pod_id' => ['required', Rule::exists('locations', 'id')->where('company_id', $companyId)],
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

        $jobNumber = 'JOB-'.date('Ym').'-'.str_pad((string) (Job::query()->count() + 1), 4, '0', STR_PAD_LEFT);

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

    public function show(Job $job, Request $request, DemurrageCalculator $calculator): Response
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
            'closedBy:id,name',
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
            // Pembatalan verifikasi: pemegang ability verifikasi (Ops) ATAU
            // Admin/Finance — verifikator asli tetap dilarang (maker-checker,
            // ditegakkan di unverifyDocument + disembunyikan di UI).
            'canUnverifyDoc' => $request->user()->can('verify_milestone')
                || $request->user()->hasAnyRole([Role::ADMIN, Role::FINANCE]),
            // Penutupan Job (§2.3, Q11): status tiap syarat close secara live.
            'closePreconditions' => app(JobClose::class)->preconditions($job),
            'canClose' => $request->user()->can('close_job'),
            'canReopen' => $request->user()->can('reopen_close'),
        ]);
    }

    public function uploadDocument(Request $request, Job $job, JobDocument $document): RedirectResponse
    {
        Gate::authorize('view', $job);

        // KUNCI TUTUP (§2.3, Q11): Job tertutup tidak menerima dokumen baru.
        JobClose::assertNotClosed($job);

        // BUGFIX (otorisasi + integritas): dokumen harus milik job pada URL.
        // Tanpa ini, CS bisa mengunggah file ke folder Job A memakai ID dokumen
        // milik Job B — dokumen Job B tertimpa metadata-nya.
        if ((int) $document->job_id !== (int) $job->id) {
            abort(404, 'Dokumen tidak ditemukan pada Job ini.');
        }

        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20MB
            'metadata' => ['nullable', 'array'],
        ]);

        // HARDENING 2026-11-04: file disimpan di disk privat 'local', bukan
        // 'public' — path lama documents/{company_id}/{job_id} bisa ditebak
        // dan diunduh siapa pun lewat /storage/ tanpa login.
        $path = $request->file('file')->store("documents/{$job->company_id}/{$job->id}", 'local');

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
        // HARDENING 2026-11-04: verifikator juga harus punya akses ke Job ini
        // (sebelumnya hanya ability global — Field yang tidak ditugaskan ke
        // Job ini tetap bisa memverifikasi dokumennya).
        Gate::authorize('view', $job);

        // KUNCI TUTUP (§2.3, Q11): verifikasi dokumen adalah tulis.
        JobClose::assertNotClosed($job);

        // BUGFIX: cek kepemilikan yang sama seperti uploadDocument
        if ((int) $document->job_id !== (int) $job->id) {
            abort(404, 'Dokumen tidak ditemukan pada Job ini.');
        }

        $notes = $request->input('notes');
        $document->markVerified($request->user(), $notes);

        return redirect()->back();
    }

    /**
     * Unduh file dokumen lewat endpoint ber-Gate (HARDENING 2026-11-04).
     * File tersimpan di disk privat — tidak ada lagi URL /storage/ langsung.
     */
    public function downloadDocument(Request $request, Job $job, JobDocument $document): StreamedResponse
    {
        Gate::authorize('view', $job);

        if ((int) $document->job_id !== (int) $job->id) {
            abort(404, 'Dokumen tidak ditemukan pada Job ini.');
        }

        return PrivateFile::download($document->file_path, $document->file_name);
    }

    /**
     * Pratinjau file dokumen di tab baru (inline disposition).
     * Gate + kepemilikan SAMA PERSIS seperti downloadDocument — bedanya
     * hanya penyajian: response() inline, bukan download() attachment.
     * Tipe di luar daftar pratinjau (mis. docx/xlsx) ditolak 415 oleh
     * PrivateFile::preview(); tombol "Lihat" juga disembunyikan di UI.
     */
    public function previewDocument(Request $request, Job $job, JobDocument $document): StreamedResponse
    {
        Gate::authorize('view', $job);

        if ((int) $document->job_id !== (int) $job->id) {
            abort(404, 'Dokumen tidak ditemukan pada Job ini.');
        }

        return PrivateFile::preview($document->file_path, $document->file_name);
    }

    /**
     * Batalkan verifikasi dokumen: status kembali ke 'diupload'.
     * Alasan wajib, tercatat di audit. Verifikator asli dilarang
     * membatalkan verifikasinya sendiri (maker-checker, ADR 0006) —
     * yang boleh adalah pemegang ability verifikasi lain, atau
     * Admin/Finance. Job tertutup terkunci, sama seperti verify.
     */
    public function unverifyDocument(Request $request, Job $job, JobDocument $document): RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('verify_milestone') && ! $user->hasAnyRole([Role::ADMIN, Role::FINANCE])) {
            abort(403, 'Hanya Ops, Admin, atau Finance yang boleh membatalkan verifikasi dokumen.');
        }

        Gate::authorize('view', $job);

        // KUNCI TUTUP (§2.3, Q11): pembatalan verifikasi adalah tulis.
        JobClose::assertNotClosed($job);

        // BUGFIX: cek kepemilikan yang sama seperti upload/verify/download.
        if ((int) $document->job_id !== (int) $job->id) {
            abort(404, 'Dokumen tidak ditemukan pada Job ini.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($document->status !== JobDocument::STATUS_DIVERIFIKASI) {
            throw ValidationException::withMessages([
                'unverify' => 'Hanya dokumen berstatus diverifikasi yang bisa dibatalkan verifikasinya.',
            ]);
        }

        // Maker-checker (§2.5, ADR 0006): verifikator tidak boleh membatalkan
        // verifikasinya sendiri, walau memegang beberapa peran sekaligus.
        if ($document->verified_by_user_id !== null && (int) $document->verified_by_user_id === (int) $user->id) {
            throw ValidationException::withMessages([
                'maker_checker' => 'Verifikator tidak boleh membatalkan verifikasinya sendiri (Maker-Checker, ADR 0006).',
            ]);
        }

        $document->markUnverified($user, $data['reason']);

        return redirect()->back();
    }

    public function updateProblemFlag(Request $request, Job $job): RedirectResponse
    {
        // HARDENING 2026-11-04: sebelumnya TIDAK ada pemeriksaan akses sama
        // sekali — user mana pun yang login bisa mengubah problem flag Job
        // mana pun. Sekarang wajib lolos Gate view Job (Field hanya untuk
        // Job yang ditugaskan kepadanya, sesuai JobPolicy).
        Gate::authorize('view', $job);

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
        // HARDENING 2026-11-04 (temuan #1): penugasan Field juga harus
        // lolos akses Job, dan user Field yang ditugaskan harus berasal dari
        // company yang sama dengan Job ini.
        Gate::authorize('view', $job);

        $data = $request->validate([
            'field_user_id' => ['required', Rule::exists('users', 'id')->where('company_id', $job->company_id)],
        ]);

        $job->assignField($data['field_user_id'], $request->user());
        Audit::record('job.field_assigned', $job, ['field_user_id' => $data['field_user_id']]);

        return redirect()->back();
    }
}
