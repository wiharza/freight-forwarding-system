<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\Importir;
use App\Models\Job;
use App\Models\NotificationLog;
use App\Models\ShippingLine;
use App\Models\TelegramConfig;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $finance;

    private Job $job;

    private Container $c1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create([
            'company_id' => $this->company->id,
            'free_days_demurrage' => 7,
        ]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
        ]);

        $this->c1 = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU1111111',
            'size' => '20',
            'type' => 'Dry',
            'gross_weight_kg' => '20000',
        ]);
    }

    #[Test]
    public function notifikasi_email_tercatat_di_log(): void
    {
        app(NotificationService::class)->sendEmail(
            'admin@example.local',
            'Admin',
            'Test Alarm',
            'Test body',
            'demurrage_alarm',
            $this->job->id,
            $this->c1->container_number
        );

        $this->assertDatabaseHas('notification_logs', [
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'recipient' => 'admin@example.local',
            'related_type' => 'demurrage_alarm',
            'container_number' => $this->c1->container_number,
        ]);
    }

    #[Test]
    public function log_notifikasi_tidak_bisa_dihapus(): void
    {
        $log = NotificationLog::create([
            'company_id' => $this->company->id,
            'channel' => 'email',
            'recipient' => 'test@test.com',
            'subject' => 'Test',
            'sent_successfully' => true,
            'sent_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    #[Test]
    public function telegram_config_aktif_terdeteksi(): void
    {
        TelegramConfig::create([
            'company_id' => $this->company->id,
            'bot_token' => 'test-token',
            'chat_id' => '12345',
            'is_active' => true,
        ]);

        $config = TelegramConfig::query()->where('is_active', true)->first();
        $this->assertNotNull($config);
        $this->assertEquals('test-token', $config->bot_token);
    }
}
