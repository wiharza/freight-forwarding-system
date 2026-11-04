<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Milestone;
use App\Services\JobClose;
use App\Support\Audit;
use App\Support\PrivateFile;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MilestoneController extends Controller
{
    public function store(Request $request, Job $job): RedirectResponse
    {
        Gate::authorize('view', $job);

        // KUNCI TUTUP (§2.3, Q11): Job tertutup tidak menerima milestone baru.
        JobClose::assertNotClosed($job);

        $types = [
            Milestone::TYPE_DISCHARGE,
            Milestone::TYPE_DO_RELEASE,
            Milestone::TYPE_PIB_FILED,
            Milestone::TYPE_SPPB,
            Milestone::TYPE_GATE_OUT_FULL,
            Milestone::TYPE_DELIVERED,
            Milestone::TYPE_GATE_IN_EMPTY,
        ];

        $data = $request->validate([
            'milestone_type' => ['required', Rule::in($types)],
            'occurred_at' => ['required', 'date'],
            // HARDENING 2026-11-04 (otorisasi Job/kontainer): container HARUS
            // milik Job ini — 'exists' saja memungkinkan kontainer Job lain
            // dicatat di Job ini (cross-Job write).
            'container_id' => ['nullable', Rule::exists('containers', 'id')->where('job_id', $job->id)],
            'container_ids' => ['nullable', 'array'],
            'container_ids.*' => [Rule::exists('containers', 'id')->where('job_id', $job->id)],
            'proof_number' => ['nullable', 'string', 'max:100'],
            'proof_file' => ['nullable', 'file', 'max:20480'],
            'notes' => ['nullable', 'string'],
            'backdate_reason' => ['nullable', 'string'],
        ]);

        $occurredAt = Carbon::parse($data['occurred_at']);
        $isBackdate = $occurredAt->lt(now()->startOfDay());

        if ($isBackdate && empty($data['backdate_reason'])) {
            throw ValidationException::withMessages([
                'backdate_reason' => 'Entri tanggal masa lalu (backdate) wajib menyertakan alasan tercatat (§2.7, Q12).',
            ]);
        }

        // Tentukan branch
        $branch = in_array($data['milestone_type'], [Milestone::TYPE_PIB_FILED, Milestone::TYPE_SPPB])
            ? Milestone::BRANCH_PABEAN
            : Milestone::BRANCH_LOGISTIK;

        // Upload bukti jika ada — disk privat 'local' (HARDENING 2026-11-04,
        // sebelumnya 'public' dengan path yang bisa ditebak).
        $filePath = null;
        if ($request->hasFile('proof_file')) {
            $filePath = $request->file('proof_file')->store("proofs/{$job->company_id}/{$job->id}", 'local');
        }

        // Daftar target kontainer (single vs batch). BUGFIX 2026-11-04:
        // $data['container_id'] tidak ada di $data saat UI tidak mengirimnya
        // (milestone level-Job: pib_filed/sppb/discharge/do_release) karena
        // kunci 'nullable' yang absen tidak masuk hasil validate() — akses
        // langsung melempar "Undefined array key" (500). Pakai empty() yang
        // aman untuk kunci yang tidak ada; [null] = milestone level-Job.
        $targetContainerIds = ! empty($data['container_ids'])
            ? $data['container_ids']
            : (! empty($data['container_id']) ? [$data['container_id']] : [null]);

        // Validasi kronologis untuk setiap kontainer (§2.2, Q15)
        foreach ($targetContainerIds as $cid) {
            Milestone::assertValidChronology($job, $data['milestone_type'], $occurredAt, $cid);
        }

        // Simpan milestone
        foreach ($targetContainerIds as $cid) {
            $milestone = Milestone::query()->create([
                'company_id' => $job->company_id,
                'job_id' => $job->id,
                'container_id' => $cid,
                'branch' => $branch,
                'milestone_type' => $data['milestone_type'],
                'occurred_at' => $occurredAt,
                'recorded_at' => now(),
                'is_backdate' => $isBackdate,
                'backdate_reason' => $data['backdate_reason'] ?? null,
                'proof_number' => $data['proof_number'] ?? null,
                'proof_file_path' => $filePath,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $request->user()->id,
                'is_active' => true,
            ]);

            Audit::record('milestone.recorded', $milestone, [
                'type' => $milestone->milestone_type,
                'occurred_at' => $milestone->occurred_at->toDateTimeString(),
            ], $milestone->backdate_reason);
        }

        $job->recomputeStatus();

        return redirect()->back();
    }

    public function verify(Request $request, Milestone $milestone): RedirectResponse
    {
        Gate::authorize('verify_milestone');
        Gate::authorize('view', $milestone->job);

        // KUNCI TUTUP (§2.3, Q11): verifikasi milestone adalah tulis.
        JobClose::assertNotClosed($milestone->job);

        $milestone->verify($request->user(), $request->input('notes'));

        return redirect()->back();
    }

    public function supersede(Request $request, Milestone $milestone): RedirectResponse
    {
        Gate::authorize('verify_milestone');
        Gate::authorize('view', $milestone->job);

        // KUNCI TUTUP (§2.3, Q11): koreksi pengganti adalah tulis.
        JobClose::assertNotClosed($milestone->job);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
            'occurred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $occurredAt = Carbon::parse($data['occurred_at']);

        // Validasi kronologis dengan tanggal baru
        Milestone::assertValidChronology($milestone->job, $milestone->milestone_type, $occurredAt, $milestone->container_id);

        $milestone->supersedeWith([
            'occurred_at' => $occurredAt,
            'notes' => $data['notes'] ?? $milestone->notes,
            'proof_number' => $milestone->proof_number,
            'proof_file_path' => $milestone->proof_file_path,
        ], $request->user(), $data['reason']);

        return redirect()->back();
    }

    /**
     * Unduh file bukti milestone lewat endpoint ber-Gate (HARDENING 2026-11-04).
     */
    public function downloadProof(Request $request, Job $job, Milestone $milestone): StreamedResponse
    {
        Gate::authorize('view', $job);

        if ((int) $milestone->job_id !== (int) $job->id) {
            abort(404, 'Milestone tidak ditemukan pada Job ini.');
        }

        return PrivateFile::download($milestone->proof_file_path);
    }

    /**
     * Tampilan Mobile PWA ringkas untuk Field di dermaga/lapangan (§2.7, Q12).
     */
    public function pwaIndex(Request $request): Response
    {
        $user = $request->user();

        $assignedJobs = Job::query()
            ->visibleTo($user)
            ->with(['shippingLine:id,code', 'containers'])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('pwa/field-milestone', [
            'jobs' => $assignedJobs,
        ]);
    }
}
