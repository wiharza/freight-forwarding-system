<?php

namespace App\Services;

use App\Models\Container;
use App\Models\InternalExchangeRate;
use App\Models\Job;
use App\Models\LineTariff;
use App\Models\Milestone;
use App\Models\ShippingLine;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DemurrageCalculator
{
    /**
     * Hitung demurrage & detention lengkap untuk satu kontainer (§4.1–§4.3, Q9).
     */
    public function calculateForContainer(Container $container, ?CarbonInterface $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate) : now();
        $job = $container->job;
        $line = $job->shippingLine;

        // Ambil milestone aktif terkait kontainer ini
        $discharge = $job->milestones()
            ->where('is_active', true)
            ->where('milestone_type', Milestone::TYPE_DISCHARGE)
            ->first();

        $gateOut = $job->milestones()
            ->where('is_active', true)
            ->where('container_id', $container->id)
            ->where('milestone_type', Milestone::TYPE_GATE_OUT_FULL)
            ->first();

        $gateIn = $job->milestones()
            ->where('is_active', true)
            ->where('container_id', $container->id)
            ->where('milestone_type', Milestone::TYPE_GATE_IN_EMPTY)
            ->first();

        // Ambil kurs internal hari referensi
        $rateInfo = InternalExchangeRate::resolveRate('USD', $asOf);
        $rateToIdr = (float) $rateInfo['rate'];

        // Default konfigurasi line jika line belum punya setting
        $freeDaysDem = $line ? (int) $line->free_days_demurrage : 7;
        $freeDaysDet = $line ? (int) $line->free_days_detention : 7;
        $offsetDem = $line ? $line->start_offset_demurrage : 'H+1';
        $offsetDet = $line ? $line->start_offset_detention : 'H+1';
        $calDem = $line ? $line->calendar_type_demurrage : 'calendar';
        $calDet = $line ? $line->calendar_type_detention : 'calendar';
        $mode = $line ? $line->mode : 'terpisah';

        // 1. Kalkulasi Demurrage
        $demData = [
            'has_started' => false,
            'is_final' => false,
            'start_date' => null,
            'end_date' => null,
            'total_days' => 0,
            'overdue_days' => 0,
            'last_free_date' => null,
            'remaining_free_days' => 0,
            'lampu' => 'hijau', // hijau, kuning, merah
            'cost_usd' => 0.0,
            'cost_idr' => 0.0,
        ];

        if ($discharge) {
            $demData['has_started'] = true;
            $demStartDate = Carbon::parse($discharge->occurred_at);
            if ($offsetDem === 'H+1') {
                $demStartDate = $demStartDate->copy()->addDay();
            }
            $demData['start_date'] = $demStartDate->toDateString();

            // End date demurrage: tanggal gate-out full (final) atau asOfDate (estimasi berjalan)
            if ($gateOut) {
                $demEndDate = Carbon::parse($gateOut->occurred_at);
                $demData['is_final'] = true;
            } else {
                $demEndDate = $asOf->copy();
                $demData['is_final'] = false;
            }
            $demData['end_date'] = $demEndDate->toDateString();

            // Hitung hari durasi demurrage
            $demData['total_days'] = $this->countDays($demStartDate, $demEndDate, $calDem);
            $demData['last_free_date'] = $this->addDaysWithCalendar($demStartDate, $freeDaysDem, $calDem)->toDateString();

            $demData['overdue_days'] = max(0, $demData['total_days'] - $freeDaysDem);

            // Hitung sisa hari free time dari sekarang
            $daysUntilLastFree = $asOf->diffInDays(Carbon::parse($demData['last_free_date']), false);
            $demData['remaining_free_days'] = (int) $daysUntilLastFree;

            // Tentukan lampu indikator (§4.4)
            if ($demData['overdue_days'] > 0) {
                $demData['lampu'] = 'merah';
            } elseif ($demData['remaining_free_days'] <= 3) {
                $demData['lampu'] = 'kuning';
            } else {
                $demData['lampu'] = 'hijau';
            }

            // Hitung tarif denda tiering
            if ($line && $demData['overdue_days'] > 0) {
                $demData['cost_usd'] = $this->calculateTieredFee(
                    $line->id,
                    LineTariff::TYPE_DEMURRAGE,
                    $container->size,
                    $demData['overdue_days'],
                    $demStartDate
                );
                $demData['cost_idr'] = round($demData['cost_usd'] * $rateToIdr, 2);
            }
        }

        // 2. Kalkulasi Detention
        $detData = [
            'has_started' => false,
            'is_final' => false,
            'start_date' => null,
            'end_date' => null,
            'total_days' => 0,
            'overdue_days' => 0,
            'last_free_date' => null,
            'remaining_free_days' => 0,
            'lampu' => 'hijau',
            'cost_usd' => 0.0,
            'cost_idr' => 0.0,
        ];

        if ($gateOut) {
            $detData['has_started'] = true;
            // Anti double count: gate-out adalah hari terakhir demurrage SEKALIGUS Hari 0 detention
            $detStartDate = Carbon::parse($gateOut->occurred_at);
            if ($offsetDet === 'H+1') {
                $detStartDate = $detStartDate->copy()->addDay();
            }
            $detData['start_date'] = $detStartDate->toDateString();

            if ($gateIn) {
                $detEndDate = Carbon::parse($gateIn->occurred_at);
                $detData['is_final'] = true;
            } else {
                $detEndDate = $asOf->copy();
                $detData['is_final'] = false;
            }
            $detData['end_date'] = $detEndDate->toDateString();

            $detData['total_days'] = $this->countDays($detStartDate, $detEndDate, $calDet);
            $detData['last_free_date'] = $this->addDaysWithCalendar($detStartDate, $freeDaysDet, $calDet)->toDateString();

            $detData['overdue_days'] = max(0, $detData['total_days'] - $freeDaysDet);

            $daysUntilLastFreeDet = $asOf->diffInDays(Carbon::parse($detData['last_free_date']), false);
            $detData['remaining_free_days'] = (int) $daysUntilLastFreeDet;

            if ($detData['overdue_days'] > 0) {
                $detData['lampu'] = 'merah';
            } elseif ($detData['remaining_free_days'] <= 3) {
                $detData['lampu'] = 'kuning';
            } else {
                $detData['lampu'] = 'hijau';
            }

            if ($line && $detData['overdue_days'] > 0) {
                $detData['cost_usd'] = $this->calculateTieredFee(
                    $line->id,
                    LineTariff::TYPE_DETENTION,
                    $container->size,
                    $detData['overdue_days'],
                    $detStartDate
                );
                $detData['cost_idr'] = round($detData['cost_usd'] * $rateToIdr, 2);
            }
        }

        // 3. Gabungan Mode Khusus (§4.2)
        if ($mode === 'gabungan' && $discharge) {
            $combinedFreeDays = $freeDaysDem + $freeDaysDet;
            $combinedStartDate = Carbon::parse($discharge->occurred_at);
            if ($offsetDem === 'H+1') {
                $combinedStartDate = $combinedStartDate->copy()->addDay();
            }

            $combinedEndDate = $gateIn ? Carbon::parse($gateIn->occurred_at) : $asOf->copy();
            $combinedDays = $this->countDays($combinedStartDate, $combinedEndDate, $calDem);
            $combinedOverdue = max(0, $combinedDays - $combinedFreeDays);

            if ($combinedOverdue > 0 && $line) {
                $combinedCostUsd = $this->calculateTieredFee(
                    $line->id,
                    LineTariff::TYPE_DEMURRAGE,
                    $container->size,
                    $combinedOverdue,
                    $combinedStartDate
                );
                // Override kalkulasi terpisah dengan gabungan
                $demData['overdue_days'] = $combinedOverdue;
                $demData['cost_usd'] = $combinedCostUsd;
                $demData['cost_idr'] = round($combinedCostUsd * $rateToIdr, 2);
                $detData['overdue_days'] = 0;
                $detData['cost_usd'] = 0.0;
                $detData['cost_idr'] = 0.0;
            }
        }

        return [
            'container_id' => $container->id,
            'container_number' => $container->container_number,
            'size' => $container->size,
            'demurrage' => $demData,
            'detention' => $detData,
            'total_usd' => round($demData['cost_usd'] + $detData['cost_usd'], 2),
            'total_idr' => round($demData['cost_idr'] + $detData['cost_idr'], 2),
            'rate_to_idr' => $rateToIdr,
            'is_official_kurs' => $rateInfo['is_official_today'],
            'is_all_final' => $demData['is_final'] && $detData['is_final'],
        ];
    }

    /**
     * Hitung total rekapitulasi untuk seluruh kontainer dalam satu Job (§4.1).
     */
    public function calculateForJob(Job $job, ?CarbonInterface $asOfDate = null): array
    {
        $containerResults = [];
        $totalUsd = 0.0;
        $totalIdr = 0.0;
        $allFinal = true;

        foreach ($job->containers as $container) {
            $res = $this->calculateForContainer($container, $asOfDate);
            $containerResults[] = $res;
            $totalUsd += $res['total_usd'];
            $totalIdr += $res['total_idr'];
            if (! $res['is_all_final']) {
                $allFinal = false;
            }
        }

        return [
            'job_id' => $job->id,
            'job_number' => $job->job_number,
            'containers' => $containerResults,
            'total_usd' => round($totalUsd, 2),
            'total_idr' => round($totalIdr, 2),
            'is_final' => $allFinal,
        ];
    }

    /**
     * Hitung hari kalender vs hari kerja (Senin-Jumat) berbasis tanggal murni (start of day, §4.1).
     */
    public function countDays(Carbon $from, Carbon $to, string $calendarType): int
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->startOfDay();

        if ($fromDay->gt($toDay)) {
            return 0;
        }

        if ($calendarType === 'working') {
            return (int) $fromDay->diffInWeekdays($toDay) + 1;
        }

        return (int) $fromDay->diffInDays($toDay) + 1;
    }

    /**
     * Tambah hari dengan memperhatikan kalender penuh vs hari kerja.
     */
    public function addDaysWithCalendar(Carbon $start, int $days, string $calendarType): Carbon
    {
        $date = $start->copy();

        if ($calendarType === 'working') {
            return $date->addWeekdays($days);
        }

        return $date->addDays($days);
    }

    /**
     * Hitung biaya berjenjang (Tiering Rate System §4.3).
     */
    public function calculateTieredFee(int $shippingLineId, string $chargeType, string $containerSize, int $overdueDays, Carbon $effectiveDate): float
    {
        if ($overdueDays <= 0) {
            return 0.0;
        }

        // Ambil semua tier aktif yang berlaku pada tanggal keterlambatan
        $tiers = LineTariff::query()
            ->where('shipping_line_id', $shippingLineId)
            ->where('charge_type', $chargeType)
            ->where('container_size', $containerSize)
            ->where('status', LineTariff::STATUS_ACTIVE)
            ->where('effective_from', '<=', $effectiveDate->toDateString())
            ->where(function ($q) use ($effectiveDate) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $effectiveDate->toDateString());
            })
            ->orderBy('day_from')
            ->get();

        if ($tiers->isEmpty()) {
            return 0.0;
        }

        $totalFee = 0.0;

        // Loop setiap hari keterlambatan 1..overdueDays
        for ($day = 1; $day <= $overdueDays; $day++) {
            $matchingTier = $tiers->first(function ($t) use ($day) {
                return $day >= $t->day_from && ($t->day_to === null || $day <= $t->day_to);
            });

            if ($matchingTier) {
                $totalFee += (float) $matchingTier->rate_per_day;
            }
        }

        return $totalFee;
    }
}
