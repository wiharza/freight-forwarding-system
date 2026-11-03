<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Container;
use App\Models\Milestone;
use App\Models\NotificationLog;
use App\Models\TelegramConfig;
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
            'company_id' => app(\App\Support\CompanyContext::class)->id(),
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