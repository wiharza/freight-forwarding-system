<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Job;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function record_mencatat_siapa_kapan_objek_dan_alasan(): void
    {
        $user = User::factory()->create();
        $job = Job::factory()->create();

        $this->actingAs($user);

        $audit = Audit::record('job.created', $job, ['job_number' => $job->job_number], 'input job baru');

        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame($job->company_id, $audit->company_id);
        $this->assertSame('job.created', $audit->action);
        $this->assertSame(Job::class, $audit->object_type);
        $this->assertSame($job->id, $audit->object_id);
        $this->assertSame(['job_number' => $job->job_number], $audit->changes);
        $this->assertSame('input job baru', $audit->reason);
        $this->assertDatabaseCount((new AuditTrail)->getTable(), 1);
    }

    #[Test]
    public function audit_trail_tidak_bisa_diedit(): void
    {
        $user = User::factory()->create();
        $job = Job::factory()->create();

        $this->actingAs($user);
        $audit = Audit::record('job.created', $job);

        $this->expectException(LogicException::class);

        $audit->update(['reason' => 'diubah diam-diam']);
    }

    #[Test]
    public function audit_trail_tidak_bisa_dihapus(): void
    {
        $user = User::factory()->create();
        $job = Job::factory()->create();

        $this->actingAs($user);
        $audit = Audit::record('job.created', $job);

        $this->expectException(LogicException::class);

        $audit->delete();
    }

    #[Test]
    public function record_tanpa_user_login_tetap_tersimpan(): void
    {
        // Konteks CLI/cron: user_id null.
        $job = Job::factory()->create();

        $audit = Audit::record('job.created', $job);

        $this->assertNull($audit->user_id);
        $this->assertSame($job->company_id, $audit->company_id);
    }
}
