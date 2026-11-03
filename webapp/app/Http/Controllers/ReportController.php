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

            $titipanDana = TitipanDana::query()
                ->where('importir_id', $imp->id)
                ->get()
                ->sum(fn ($t) => $t->sisaSaldo());

            return [
                'importir_id' => $imp->id,
                'importir_name' => $imp->name,
                'exposure_bruto' => $totalRealCost - $totalAllocated,
                'titipan_dana_saldo' => $titipanDana,
            ];
        });

        return Inertia::render('reports/exposure', [
            'exposures' => $exposures,
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
                $daysOverdue = $dueDate ? now()->diffInDays($dueDate, false) : 0;
                $bucket = '0-30';
                if ($daysOverdue < 0) {
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
                    'days_overdue' => max(0, -$daysOverdue),
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

                $demDays = $gateOut && $job->ata ? \Carbon\Carbon::parse($gateOut->occurred_at)->diffInDays(\Carbon\Carbon::parse($job->ata)) : 0;
                $detDays = ($gateIn && $gateOut) ? \Carbon\Carbon::parse($gateIn->occurred_at)->diffInDays(\Carbon\Carbon::parse($gateOut->occurred_at)) : 0;

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