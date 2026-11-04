<?php

namespace Tests\Feature;

use App\Mail\FreeTimeAlertMail;
use App\Models\Company;
use App\Models\Container;
use App\Models\FreeTimeAlert;
use App\Models\Importir;
use App\Models\InternalExchangeRate;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\NotificationLog;
use App\Models\ShippingLine;
use App\Models\User;
use App\Services\FreeTimeAlertService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes alarm Free Time (§2.6, Q8, Tiket 15).
 *
 * Aritmetika tanggal (line: free 7 hari, offset H+1, kalender):
 * discharge 04 Nov -> start 05 Nov -> last free 12 Nov.
 * Tanggal acuan "hari ini" di semua tes: 10 Nov 2026.
 */
class FreeTimeAlertTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $creator;

    private User $finance;

    private User $finance2;

    private User $ops;

    private Job $job;

    private Container $container;

    private Carbon $asOf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->asOf = Carbon::parse('2026-11-10');

        $this->company = Company::factory()->create();

        $this->creator = User::factory()->create(['company_id' => $this->company->id]);
        $this->creator->roles()->sync([$this->roleId('cs')]);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->finance2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance2->roles()->sync([$this->roleId('finance')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        // Kurs Internal USD — DemurrageCalculator gagal tanpa baris kurs
        // sama sekali (temuan #4), jadi tes wajib menyediakannya.
        InternalExchangeRate::factory()->create([
            'company_id' => $this->company->id,
            'rate_date' => '2026-10-01',
            'currency_code' => 'USD',
            'rate_to_idr' => '16000.0000',
        ]);

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create([
            'company_id' => $this->company->id,
            'free_days_demurrage' => 7,
            'free_days_detention' => 7,
            'start_offset_demurrage' => 'H+1',
            'start_offset_detention' => 'H+1',
            'calendar_type_demurrage' => 'calendar',
            'calendar_type_detention' => 'calendar',
            'mode' => 'terpisah',
        ]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
            'created_by_user_id' => $this->creator->id,
            'cached_status' => Job::STATUS_DOKUMEN_LENGKAP,
        ]);

        $this->container = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU1111111',
            'size' => '20',
            'type' => 'Dry',
            'gross_weight_kg' => '20000',
        ]);
    }

    private function addMilestone(string $type, string $occurredAt, ?int $containerId = null): Milestone
    {
        return Milestone::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'container_id' => $containerId,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => $type,
            'occurred_at' => $occurredAt,
            'recorded_at' => $occurredAt,
            'is_active' => true,
            'created_by_user_id' => $this->creator->id,
        ]);
    }

    #[Test]
    public function h_minus_2_demurrage_berbunyi_ke_pic_dan_semua_finance(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');

        $fired = app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf);

        $this->assertSame(1, $fired);
        // (SQLite menyimpan kolom date sebagai 'YYYY-MM-DD 00:00:00', jadi
        // tanggal dicek lewat model — bukan assertDatabaseHas mentah.)
        $alert = FreeTimeAlert::query()
            ->where('container_id', $this->container->id)
            ->where('charge_type', FreeTimeAlert::CHARGE_DEMURRAGE)
            ->where('trigger', FreeTimeAlert::TRIGGER_H_MINUS_2)
            ->first();
        $this->assertNotNull($alert);
        $this->assertSame('2026-11-12', $alert->free_time_date->toDateString());
        $this->assertSame(FreeTimeAlert::STATUS_SENT, $alert->status);

        // PIC Job (pembuat) + 2 Finance = 3 penerima, masing-masing 1 email.
        Mail::assertSent(FreeTimeAlertMail::class, 3);
        Mail::assertSent(FreeTimeAlertMail::class, fn ($mail) => $mail->hasTo($this->creator->email));
        Mail::assertSent(FreeTimeAlertMail::class, fn ($mail) => $mail->hasTo($this->finance->email));
        Mail::assertSent(FreeTimeAlertMail::class, fn ($mail) => $mail->hasTo($this->finance2->email));

        $this->assertSame(3, NotificationLog::query()->where('channel', NotificationLog::CHANNEL_EMAIL)->count());
        $this->assertSame(3, NotificationLog::query()->where('channel', NotificationLog::CHANNEL_BADGE)->count());

        // Badge: PIC + Finance punya 1 belum dibaca; Ops tidak punya.
        $this->assertSame(1, NotificationLog::unreadCountFor($this->creator));
        $this->assertSame(1, NotificationLog::unreadCountFor($this->finance));
        $this->assertSame(1, NotificationLog::unreadCountFor($this->finance2));
        $this->assertSame(0, NotificationLog::unreadCountFor($this->ops));
    }

    #[Test]
    public function pemicu_yang_sama_tidak_berbunyi_dua_kali(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');

        $service = app(FreeTimeAlertService::class);
        $this->assertSame(1, $service->checkForJob($this->job, $this->asOf));
        $this->assertSame(0, $service->checkForJob($this->job, $this->asOf));

        $this->assertSame(1, FreeTimeAlert::query()->count());
        Mail::assertSent(FreeTimeAlertMail::class, 3);
    }

    #[Test]
    public function h_0_demurrage_berbunyi_pada_last_free_date(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-02 10:00:00');

        $this->assertSame(1, app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf));

        $alert = FreeTimeAlert::query()
            ->where('charge_type', FreeTimeAlert::CHARGE_DEMURRAGE)
            ->where('trigger', FreeTimeAlert::TRIGGER_H_0)
            ->first();
        $this->assertNotNull($alert);
        $this->assertSame('2026-11-10', $alert->free_time_date->toDateString());
    }

    #[Test]
    public function h_plus_1_demurrage_berbunyi_sehari_setelah_last_free_date(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-01 10:00:00');

        $this->assertSame(1, app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf));

        $alert = FreeTimeAlert::query()
            ->where('charge_type', FreeTimeAlert::CHARGE_DEMURRAGE)
            ->where('trigger', FreeTimeAlert::TRIGGER_H_PLUS_1)
            ->first();
        $this->assertNotNull($alert);
        $this->assertSame('2026-11-09', $alert->free_time_date->toDateString());
    }

    #[Test]
    public function tidak_ada_alarm_sebelum_h_minus_2(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-03 10:00:00');

        $this->assertSame(0, app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf));

        $this->assertSame(0, FreeTimeAlert::query()->count());
        Mail::assertNotSent(FreeTimeAlertMail::class);
    }

    #[Test]
    public function detention_h_minus_2_berbunyi_sedangkan_demurrage_final_diam(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');
        $this->addMilestone(Milestone::TYPE_GATE_OUT_FULL, '2026-11-04 15:00:00', $this->container->id);

        $this->assertSame(1, app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf));

        // Hanya detention; demurrage sudah final (gate-out) jadi tidak ada
        // lagi alarm demurrage untuk kontainer ini.
        $detAlert = FreeTimeAlert::query()
            ->where('charge_type', FreeTimeAlert::CHARGE_DETENTION)
            ->where('trigger', FreeTimeAlert::TRIGGER_H_MINUS_2)
            ->first();
        $this->assertNotNull($detAlert);
        $this->assertSame('2026-11-12', $detAlert->free_time_date->toDateString());
        $this->assertDatabaseMissing('free_time_alerts', [
            'charge_type' => FreeTimeAlert::CHARGE_DEMURRAGE,
        ]);
    }

    #[Test]
    public function job_tertutup_tidak_dikirim_alarm(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');
        $this->job->update(['closed_at' => now(), 'cached_status' => Job::STATUS_CLOSED]);

        $this->assertSame(0, app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf));
        $this->assertSame(0, FreeTimeAlert::query()->count());
    }

    #[Test]
    public function pembuat_yang_juga_finance_hanya_menerima_satu(): void
    {
        Mail::fake();

        $picFinance = User::factory()->create(['company_id' => $this->company->id]);
        $picFinance->roles()->sync([$this->roleId('cs'), $this->roleId('finance')]);

        $this->job->update(['created_by_user_id' => $picFinance->id]);
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');

        app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf);

        // picFinance (1) + finance + finance2 = 3, tanpa duplikat.
        Mail::assertSent(FreeTimeAlertMail::class, 3);
        $this->assertSame(1, NotificationLog::unreadCountFor($picFinance));
    }

    #[Test]
    public function isi_email_menyebut_job_kontainer_dan_last_free_date(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');

        app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf);

        Mail::assertSent(FreeTimeAlertMail::class, function (FreeTimeAlertMail $mail) {
            $html = $mail->render();

            return str_contains($html, $this->job->job_number)
                && str_contains($html, 'MSKU1111111')
                && str_contains($html, '12 Nov 2026')
                && str_contains($html, route('jobs.show', $this->job));
        });
    }

    #[Test]
    public function command_dengan_opsi_date_mengevaluasi_tanggal_simulasi(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');

        $exit = Artisan::call('notifications:check-free-time', ['--date' => '2026-11-10']);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('free_time_alerts', [
            'trigger' => FreeTimeAlert::TRIGGER_H_MINUS_2,
        ]);
    }

    #[Test]
    public function command_menolak_format_tanggal_salah(): void
    {
        $exit = Artisan::call('notifications:check-free-time', ['--date' => '10-11-2026']);

        $this->assertSame(1, $exit);
    }

    #[Test]
    public function entri_milestone_discharge_memicu_evaluasi_ulang(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-11-10 09:00:00');

        try {
            $this->actingAs($this->ops)->post(route('jobs.milestones.store', $this->job), [
                'milestone_type' => Milestone::TYPE_DISCHARGE,
                'occurred_at' => '2026-11-04 10:00:00',
                'backdate_reason' => 'Bongkar tercatat terlambat dari lapangan.',
            ])->assertRedirect();
        } finally {
            Carbon::setTestNow();
        }

        // Hook di MilestoneController::store mengevaluasi Job yang sama —
        // H-2 langsung berbunyi tanpa menunggu command harian.
        $this->assertDatabaseHas('free_time_alerts', [
            'charge_type' => FreeTimeAlert::CHARGE_DEMURRAGE,
            'trigger' => FreeTimeAlert::TRIGGER_H_MINUS_2,
        ]);
    }

    #[Test]
    public function kotak_masuk_menampilkan_dan_menandai_belum_dibaca(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');
        app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf);

        $log = NotificationLog::query()
            ->where('channel', NotificationLog::CHANNEL_BADGE)
            ->where('user_id', $this->creator->id)
            ->firstOrFail();

        $this->actingAs($this->creator)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('notifications/index')
                ->where('unread_count', 1)
                ->has('notifications.data', 1)
            );

        $this->actingAs($this->creator)
            ->post(route('notifications.read', $log))
            ->assertRedirect();

        $this->assertSame(0, NotificationLog::unreadCountFor($this->creator));
        $this->assertDatabaseHas('notification_reads', [
            'user_id' => $this->creator->id,
            'notification_log_id' => $log->id,
        ]);
    }

    #[Test]
    public function tandai_semua_dibaca_mengosongkan_badge(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');
        app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf);

        $this->assertSame(1, NotificationLog::unreadCountFor($this->creator));

        $this->actingAs($this->creator)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, NotificationLog::unreadCountFor($this->creator));
    }

    #[Test]
    public function pengguna_tidak_bisa_menandai_notifikasi_orang_lain(): void
    {
        Mail::fake();
        $this->addMilestone(Milestone::TYPE_DISCHARGE, '2026-11-04 10:00:00');
        app(FreeTimeAlertService::class)->checkForJob($this->job, $this->asOf);

        $log = NotificationLog::query()
            ->where('channel', NotificationLog::CHANNEL_BADGE)
            ->where('user_id', $this->creator->id)
            ->firstOrFail();

        $this->actingAs($this->ops)
            ->post(route('notifications.read', $log))
            ->assertNotFound();

        $this->assertSame(1, NotificationLog::unreadCountFor($this->creator));
    }

    #[Test]
    public function command_terdaftar_di_scheduler_harian(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(
            fn ($event) => str_contains((string) $event->command, 'notifications:check-free-time')
        ));
    }
}
