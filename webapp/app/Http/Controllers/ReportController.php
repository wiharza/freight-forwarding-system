<?php

namespace App\Http\Controllers;

use App\Models\ContainerDeposit;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobCost;
use App\Models\PaymentAllocation;
use App\Models\TitipanDana;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function jobList(Request $request): Response
    {
        // §7 akses: Ops melihat (a); Field tidak ada akses laporan
        $user = $request->user();
        if ($user->hasRole(\App\Models\Role::FIELD) && $user->roles()->count() === 1) {
            abort(403, 'Field tidak memiliki akses laporan.');
        }

        $jobs = Job::query()
            ->visibleTo($user)
            ->with(['importir:id,name', 'shippingLine:id,code', 'containers:id,job_id,size,type,container_number'])
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('reports/jobs', [
            'jobs' => $jobs,
        ]);
    }

    public function exposurePerImportir(Request $request): Response
    {
        Gate::authorize('laporan_keuangan');

        $importirs = Importir::query()->get();
        $exposures = $importirs->map(function ($imp) {
            $totalRealCost = JobCost::query()
                ->whereHas('job', fn ($q) => $q->where('importir_id', $imp->id))
                ->where('status', 'aktual')
                ->where('payer_flag', '!=', 'importir_langsung')
                ->sum('amount_idr');

            $totalAllocated = PaymentAllocation::query()
                ->whereHas('job', fn ($q) => $q->where('importir_id', $imp->id))
                ->sum('amount');

            // BUGFIX 2026-11-04: eksposur lama hanya menghitung JobCost
            // (sumber Blok B) sehingga importir yang sudah lunas penuh tampil
            // NEGATIF — Blok (a) pungutan negara dan Blok (c) jasa tidak
            // pernah masuk. Eksposur bruto = biaya aktual + pungutan & jasa
            // yang sudah ditagihkan − alokasi; dibatasi bawah 0. Blok B
            // tidak dihitung dua kali (sudah tercakup di JobCost).
            $totalBilledBlocks = Invoice::query()
                ->where('importir_id', $imp->id)
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->get()
                ->sum(fn ($inv) => (float) $inv->blok_a_total + (float) $inv->blok_c_service_fee + (float) $inv->blok_c_ppn);

            $titipanDana = TitipanDana::query()
                ->where('importir_id', $imp->id)
                ->get()
                ->sum(fn ($t) => $t->sisaSaldo());

            return [
                'importir_id' => $imp->id,
                'importir_name' => $imp->name,
                'exposure_bruto' => max(0, (float) $totalRealCost + $totalBilledBlocks - (float) $totalAllocated),
                'titipan_dana_saldo' => $titipanDana,
            ];
        });

        return Inertia::render('reports/exposure', [
            'exposures' => $exposures,
        ]);
    }

    /**
     * Estimasi vs Aktual per Job (§7d) — BUGFIX 2026-11-04: method ini
     * tidak ada padahal rutenya terdaftar, sehingga laporan selalu 500.
     */
    public function estimateVsActual(Request $request): Response
    {
        Gate::authorize('laporan_keuangan');

        $rows = Job::query()
            ->with(['importir:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(function ($job) {
                $estimasi = (float) $job->costs()->where('status', JobCost::STATUS_ESTIMASI)->sum('amount_idr');
                $aktual = (float) $job->costs()->where('status', JobCost::STATUS_AKTUAL)->sum('amount_idr');

                return [
                    'job_number' => $job->job_number,
                    'importir_name' => $job->importir?->name,
                    'total_estimasi' => $estimasi,
                    'total_aktual' => $aktual,
                    'selisih' => $aktual - $estimasi,
                ];
            });

        return Inertia::render('reports/estimate-vs-actual', [
            'rows' => $rows,
        ]);
    }

    public function aging(Request $request): Response
    {
        Gate::authorize('laporan_keuangan');

        $invoices = Invoice::query()
            ->where('status', Invoice::STATUS_LOCKED)
            ->with(['importir:id,name'])
            ->get()
            ->map(function ($inv) {
                $dueDate = $inv->due_date;
                // BUGFIX 2026-11-04: Carbon 3 diffInDays($date, false) bertanda
                // — untuk invoice jatuh tempo nilainya NEGATIF sehingga SEMUA
                // invoice telat masuk bucket '>90', dan yang belum jatuh tempo
                // malah masuk '61-90'/'31-60'. Hitung hari telat sebagai
                // bilangan positif (0 bila belum jatuh tempo) lalu bucket
                // dari angka itu.
                $daysOverdue = ($dueDate && $dueDate->isPast()) ? (int) $dueDate->diffInDays(now(), true) : 0;
                $bucket = '0-30';
                if ($daysOverdue > 90) {
                    $bucket = '>90';
                } elseif ($daysOverdue > 60) {
                    $bucket = '61-90';
                } elseif ($daysOverdue > 30) {
                    $bucket = '31-60';
                }

                return [
                    'invoice_number' => $inv->invoice_number,
                    'importir_name' => $inv->importir?->name,
                    'due_date' => $dueDate?->toDateString(),
                    'total_invoice' => (float) $inv->total_invoice,
                    'total_paid' => (float) $inv->total_paid,
                    'sisa' => $inv->sisaTagihan(),
                    'days_overdue' => $daysOverdue,
                    'bucket' => $bucket,
                ];
            });

        return Inertia::render('reports/aging', [
            'invoices' => $invoices,
        ]);
    }

    public function demurragePerLine(Request $request): Response
    {
        Gate::authorize('laporan_keuangan');

        $jobs = Job::query()->with(['shippingLine:id,code,name', 'containers', 'milestones' => fn ($q) => $q->where('is_active', true)])->get();
        $summary = collect();

        foreach ($jobs as $job) {
            if (! $job->shippingLine) {
                continue;
            }

            foreach ($job->containers as $c) {
                $gateOut = $job->milestones->where('container_id', $c->id)->where('milestone_type', 'gate_out_full')->first();
                $gateIn = $job->milestones->where('container_id', $c->id)->where('milestone_type', 'gate_in_empty')->first();

                // BUGFIX 2026-11-04: Carbon 3 diffInDays() bertanda secara
                // default — gateOut yang terjadi SETELAH ata menghasilkan
                // selisih negatif sehingga max(0, negatif − free) selalu 0
                // dan laporan selalu menampilkan 0 hari. Pakai nilai absolut.
                $demDays = $gateOut && $job->ata ? \Carbon\Carbon::parse($gateOut->occurred_at)->diffInDays(\Carbon\Carbon::parse($job->ata), true) : 0;
                $detDays = ($gateIn && $gateOut) ? \Carbon\Carbon::parse($gateIn->occurred_at)->diffInDays(\Carbon\Carbon::parse($gateOut->occurred_at), true) : 0;

                $summary->push([
                    'line' => $job->shippingLine->code,
                    'container_number' => $c->container_number,
                    'size' => $c->size,
                    'demurrage_days' => max(0, $demDays - ($job->shippingLine->free_days_demurrage ?? 7)),
                    'detention_days' => max(0, $detDays - ($job->shippingLine->free_days_detention ?? 7)),
                ]);
            }
        }

        $grouped = $summary->groupBy('line')->map(function ($group, $line) {
            return [
                'line' => $line,
                'total_containers' => $group->count(),
                'total_demurrage_days' => $group->sum('demurrage_days'),
                'total_detention_days' => $group->sum('detention_days'),
            ];
        })->values();

        return Inertia::render('reports/demurrage-per-line', [
            'summary' => $grouped,
        ]);
    }

    public function depositFlow(Request $request): Response
    {
        Gate::authorize('laporan_keuangan');

        $deposits = ContainerDeposit::query()
            ->with(['container:id,container_number,size', 'job:id,job_number'])
            ->orderBy('paid_date')
            ->get()
            ->map(function ($d) {
                return [
                    'job_number' => $d->job?->job_number,
                    'container_number' => $d->container?->container_number,
                    'amount_usd' => (float) $d->amount_usd,
                    'status' => $d->status,
                    'forfeit_usd' => (float) $d->forfeit_amount_usd,
                    'paid_date' => $d->paid_date?->toDateString(),
                    'returned_date' => $d->returned_date?->toDateString(),
                    'days_held' => $d->paid_date ? now()->diffInDays($d->paid_date) : 0,
                ];
            });

        return Inertia::render('reports/deposit-flow', [
            'deposits' => $deposits,
        ]);
    }
}