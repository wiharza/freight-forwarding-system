<?php

namespace App\Http\Controllers;

use App\Models\FreeTimeAlert;
use App\Models\NotificationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kotak masuk notifikasi (§2.6, Q8, Tiket 15) — badge di header dihitung
 * dari baris badge yang belum dibaca (lihat NotificationLog::unreadFor).
 * Pengguna hanya melihat dan menandai miliknya sendiri, di company-nya.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $logs = NotificationLog::query()
            ->inboxFor($user)
            ->where('company_id', $user->company_id)
            ->with(['job:id,job_number', 'reads'])
            ->orderByDesc('sent_at')
            ->paginate(15)
            ->through(fn (NotificationLog $log) => [
                'id' => $log->id,
                'subject' => $log->subject,
                'body' => $log->body,
                'trigger' => $log->trigger,
                'trigger_label' => $log->trigger ? (FreeTimeAlert::TRIGGER_LABELS[$log->trigger] ?? $log->trigger) : null,
                'charge_type' => $log->charge_type,
                'charge_label' => $log->charge_type ? (FreeTimeAlert::CHARGE_LABELS[$log->charge_type] ?? $log->charge_type) : null,
                'container_number' => $log->container_number,
                'free_time_date' => $log->free_time_date?->toDateString(),
                'job_id' => $log->job_id,
                'job_number' => $log->job?->job_number,
                'sent_at' => $log->sent_at?->toDateTimeString(),
                // Pakai relasi reads yang sudah di-eager-load (bukan query baru).
                'is_read' => $log->reads->contains('user_id', $user->id),
            ]);

        return Inertia::render('notifications/index', [
            'notifications' => $logs,
            'unread_count' => NotificationLog::unreadCountFor($user),
        ]);
    }

    public function markAsRead(Request $request, NotificationLog $log): RedirectResponse
    {
        $this->authorizeLog($request, $log);

        $log->markAsReadBy($request->user());

        return redirect()->back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $user = $request->user();

        $unread = NotificationLog::query()->unreadFor($user)
            ->where('company_id', $user->company_id)
            ->get();

        foreach ($unread as $log) {
            $log->markAsReadBy($user);
        }

        return redirect()->back();
    }

    private function authorizeLog(Request $request, NotificationLog $log): void
    {
        abort_unless(
            $log->channel === NotificationLog::CHANNEL_BADGE
                && $log->user_id === $request->user()->id
                && $log->company_id === $request->user()->company_id,
            404
        );
    }
}
