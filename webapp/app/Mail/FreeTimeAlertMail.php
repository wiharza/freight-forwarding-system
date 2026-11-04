<?php

namespace App\Mail;

use App\Models\Container;
use App\Models\FreeTimeAlert;
use App\Models\Job;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email alarm Free Time (§2.6, Q8, Tiket 15) — bahasa Indonesia lugas:
 * nomor Job, nomor kontainer, last free date, estimasi biaya saat ini,
 * dan link langsung ke Job. Dikirim ke PIC Job + Finance, bukan ke Importir.
 */
class FreeTimeAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $chargeLabel;

    public readonly string $triggerLabel;

    public readonly string $jobUrl;

    public function __construct(
        public readonly Job $job,
        public readonly Container $container,
        public readonly string $chargeType,
        public readonly string $trigger,
        /** Hasil DemurrageCalculator::calculateForContainer untuk kontainer ini. */
        public readonly array $calc,
        public readonly Carbon $freeTimeDate,
        public readonly Carbon $asOf,
    ) {
        $this->chargeLabel = FreeTimeAlert::CHARGE_LABELS[$chargeType] ?? $chargeType;
        $this->triggerLabel = FreeTimeAlert::TRIGGER_LABELS[$trigger] ?? $trigger;
        $this->jobUrl = route('jobs.show', $job);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Alarm Free Time {$this->chargeLabel} {$this->triggerLabel}: {$this->job->job_number} / {$this->container->container_number}",
        );
    }

    public function content(): Content
    {
        $section = $this->calc[$this->chargeType] ?? [];

        return new Content(
            html: 'emails.free-time-alert',
            text: 'emails.free-time-alert-text',
            with: [
                'job' => $this->job,
                'container' => $this->container,
                'chargeLabel' => $this->chargeLabel,
                'triggerLabel' => $this->triggerLabel,
                'freeTimeDate' => $this->freeTimeDate,
                'asOf' => $this->asOf,
                'remainingDays' => (int) $this->asOf->copy()->startOfDay()
                    ->diffInDays($this->freeTimeDate->copy()->startOfDay(), false),
                'costUsd' => (float) ($section['cost_usd'] ?? 0),
                'costIdr' => (float) ($section['cost_idr'] ?? 0),
                'jobUrl' => $this->jobUrl,
            ],
        );
    }
}
