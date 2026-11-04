<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\JobClose;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Penutupan & pembukaan kembali Job (§2.3, Q11).
 *
 * Close: Finance/Admin, semua syarat close harus lolos — bila ada yang
 * gagal, close ditolak dengan daftar blocker dan percobaan yang gagal
 * TETAP tercatat di audit trail. Reopen: Admin/Finance dengan alasan,
 * maker-checker terhadap penutup (§2.5, ADR 0006).
 */
class JobCloseController extends Controller
{
    public function __construct(private readonly JobClose $jobClose) {}

    public function close(Request $request, Job $job): RedirectResponse
    {
        Gate::authorize('close_job');
        Gate::authorize('view', $job);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->jobClose->close($job, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            // Percobaan close yang gagal tetap tercatat (§2.3: audit trail
            // mencakup penolakan, bukan hanya keberhasilan).
            Audit::record('job.close_blocked', $job, [
                'errors' => $e->errors(),
            ], $data['reason']);

            throw $e;
        }

        return redirect()->back();
    }

    public function reopen(Request $request, Job $job): RedirectResponse
    {
        Gate::authorize('reopen_close');
        Gate::authorize('view', $job);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->jobClose->reopen($job, $request->user(), $data['reason']);

        return redirect()->back();
    }
}
