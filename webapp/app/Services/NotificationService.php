<?php

namespace App\Services;

use App\Mail\FreeTimeAlertMail;
use App\Models\Container;
use App\Models\Job;
use App\Models\NotificationLog;
use App\Models\Role;
use App\Models\TelegramConfig;
use App\Models\User;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function sendDemurrageAlarm(Container $container, array $demData, string $alarmLevel): void
    {
        $job = $container->job;
        $line = $job->shippingLine;
        $lineCode = $line?->code ?? '-';

        $this->sendEmail(
            'finance@example.local',
            'Finance',
            "Alarm Demurrage {$alarmLevel}: {$job->job_number} / {$container->container_number}",
            "Job: {$job->job_number}\nKontainer: {$container->container_number} ({$container->size}')\nLast Free: {$demData['last_free_date']}\nEstimasi: USD {$demData['cost_usd']}",
            'demurrage_alarm',
            $job->id,
            $container->container_number
        );

        $this->sendTelegram(
            "ALARM {$alarmLevel} Demurrage\nJob: {$job->job_number}\nKontainer: {$container->container_number}\nLast Free: {$demData['last_free_date']}\nEstimasi: USD {$demData['cost_usd']}",
            'demurrage_alarm',
            $job->id,
            $container->container_number
        );
    }

    /**
     * Kirim satu Mailable dan catat hasilnya di log notifikasi (§2.6, Q8).
     * Selalu menulis baris log — gagal kirim pun tercatat (sent_successfully
     * false + error_message), supaya tidak ada yang mengklaim "tidak
     * diberitahu" tanpa jejak. `$extraLog` menautkan baris ke pemicu alarm
     * (user_id, job_id, trigger, charge_type, free_time_date, company_id).
     */
    public function sendMailable(Mailable $mailable, string $to, ?string $toName, string $subject, string $relatedType, ?int $relatedId = null, ?string $containerNumber = null, array $extraLog = []): bool
    {
        $sentSuccessfully = false;
        $errorMessage = null;

        try {
            Mail::to($to, $toName)->send($mailable);
            $sentSuccessfully = true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        // Isi email di log bersifat best-effort: kegagalan render tidak
        // boleh menggagalkan pencatatan pengiriman itu sendiri.
        try {
            $body = $mailable->render();
        } catch (\Throwable) {
            $body = $subject;
        }

        NotificationLog::query()->create(array_merge([
            'company_id' => $extraLog['company_id'] ?? app(CompanyContext::class)->id(),
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'recipient' => $to,
            'recipient_name' => $toName,
            'subject' => $subject,
            'body' => $body,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'container_number' => $containerNumber,
            'sent_successfully' => $sentSuccessfully,
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ], $extraLog));

        return $sentSuccessfully;
    }

    /**
     * Kirim alarm Free Time untuk satu pemicu (§2.6, Q8, Tiket 15).
     *
     * Penerima v1: PIC Job + Finance (internal, bukan blast ke Importir).
     * "PIC Job" = pembuat Job (created_by_user_id) — tidak ada kolom
     * pic_user_id di skema, jadi pembuat adalah penanggung jawab Job yang
     * tercatat. Tiap penerima mendapat SATU email + SATU baris badge
     * (kotak masuk). Baris badge selalu dibuat walau email gagal, supaya
     * alarm tetap terlihat di dashboard.
     *
     * @return array{emailed: int[], failed: int[]} id pengguna per hasil
     */
    public function notifyFreeTimeAlert(Job $job, Container $container, string $chargeType, string $trigger, array $calc, Carbon $asOf): array
    {
        $recipients = $this->freeTimeRecipients($job);
        $freeTimeDate = Carbon::parse($calc[$chargeType]['last_free_date']);
        $emailed = [];
        $failed = [];

        foreach ($recipients as $user) {
            $mail = new FreeTimeAlertMail($job, $container, $chargeType, $trigger, $calc, $freeTimeDate, $asOf);

            $extraLog = [
                'company_id' => $job->company_id,
                'user_id' => $user->id,
                'job_id' => $job->id,
                'trigger' => $trigger,
                'charge_type' => $chargeType,
                'free_time_date' => $freeTimeDate->toDateString(),
            ];

            $ok = $this->sendMailable(
                $mail,
                $user->email,
                $user->name,
                $mail->envelope()->subject,
                'free_time_alert',
                $job->id,
                $container->container_number,
                $extraLog
            );

            if ($ok) {
                $emailed[] = $user->id;
            } else {
                $failed[] = $user->id;
            }

            // Baris badge = entri kotak masuk pengguna (§2.6). Dibuat apa pun
            // hasil email — alarm harus tetap terlihat di dashboard.
            try {
                $badgeBody = $mail->render();
            } catch (\Throwable) {
                $badgeBody = $mail->envelope()->subject;
            }

            NotificationLog::query()->create([
                'company_id' => $job->company_id,
                'channel' => NotificationLog::CHANNEL_BADGE,
                'recipient' => $user->email,
                'recipient_name' => $user->name,
                'subject' => $mail->envelope()->subject,
                'body' => $badgeBody,
                'related_type' => 'free_time_alert',
                'related_id' => $job->id,
                'container_number' => $container->container_number,
                'sent_successfully' => true,
                'sent_at' => now(),
                'user_id' => $user->id,
                'job_id' => $job->id,
                'trigger' => $trigger,
                'charge_type' => $chargeType,
                'free_time_date' => $freeTimeDate->toDateString(),
            ]);
        }

        return ['emailed' => $emailed, 'failed' => $failed];
    }

    /**
     * PIC Job (pembuat Job) + semua pengguna peran Finance di company yang
     * sama, tanpa duplikat, hanya yang punya alamat email.
     */
    public function freeTimeRecipients(Job $job): Collection
    {
        $recipients = collect();

        if ($job->creator && $job->creator->email) {
            $recipients->push($job->creator);
        }

        $finance = User::query()
            ->where('company_id', $job->company_id)
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::FINANCE))
            ->get();

        return $recipients->concat($finance)
            ->unique('id')
            ->filter(fn (User $u) => filled($u->email))
            ->values();
    }

    public function sendEmail(string $to, string $toName, string $subject, string $body, string $relatedType, ?int $relatedId = null, ?string $containerNumber = null): void
    {
        $sentSuccessfully = false;
        $errorMessage = null;

        try {
            Mail::raw($body, function ($message) use ($to, $toName, $subject) {
                $message->to($to, $toName)->subject($subject);
            });
            $sentSuccessfully = true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        NotificationLog::query()->create([
            'company_id' => app(CompanyContext::class)->id(),
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'recipient' => $to,
            'recipient_name' => $toName,
            'subject' => $subject,
            'body' => $body,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'container_number' => $containerNumber,
            'sent_successfully' => $sentSuccessfully,
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ]);
    }

    public function sendTelegram(string $message, string $relatedType, ?int $relatedId = null, ?string $containerNumber = null): void
    {
        $config = TelegramConfig::query()->where('is_active', true)->first();
        if (! $config) {
            return;
        }

        $sentSuccessfully = false;
        $errorMessage = null;

        try {
            $response = Http::post("https://api.telegram.org/bot{$config->bot_token}/sendMessage", [
                'chat_id' => $config->chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
            $sentSuccessfully = $response->successful();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        NotificationLog::query()->create([
            'company_id' => $config->company_id,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'recipient' => $config->chat_id,
            'subject' => 'Telegram Notification',
            'body' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'container_number' => $containerNumber,
            'sent_successfully' => $sentSuccessfully,
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ]);
    }
}
