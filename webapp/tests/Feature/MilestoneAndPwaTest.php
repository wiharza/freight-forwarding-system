<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\Importir;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class MilestoneAndPwaTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;
    private User $cs;
    private User $ops;
    private User $ops2;
    private User $field;
    private Job $job;
    private Container $c1;
    private Container $c2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->ops2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops2->roles()->sync([$this->roleId('ops')]);

        $this->field = User::factory()->create(['company_id' => $this->company->id]);
        $this->field->roles()->sync([$this->roleId('field')]);

        app(\App\Support\CompanyContext::class)->set($this->company->id);

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'cached_status' => Job::STATUS_DRAFT,
        ]);

        $this->c1 = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU1111111',
            'size' => '20',
            'type' => 'Dry',
            'gross_weight_kg' => '20000',
        ]);

        $this->c2 = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU2222222',
            'size' => '40',
            'type' => 'Dry',
            'gross_weight_kg' => '25000',
        ]);

        $this->job->assignField($this->field, $this->ops);
    }

    #[Test]
    public function validasi_kronologis_menolak_sppb_mendahului_pib_filed(): void
    {
        // 1. Catat PIB filed tanggal 2026-11-03 10:00
        $this->job->milestones()->create([
            'company_id' => $this->company->id,
            'branch' => Milestone::BRANCH_PABEAN,
            'milestone_type' => Milestone::TYPE_PIB_FILED,
            'occurred_at' => '2026-11-03 10:00:00',
            'recorded_at' => now(),
            'is_active' => true,
        ]);

        // 2. Coba catat SPPB mendahului PIB filed (2026-11-03 08:00) -> WAJIB GAGAL
        $this->expectException(ValidationException::class);

        Milestone::assertValidChronology(
            $this->job,
            Milestone::TYPE_SPPB,
            Carbon::parse('2026-11-03 08:00:00')
        );
    }

    #[Test]
    public function validasi_kronologis_menolak_gate_out_sebelum_do_terbit_atau_discharge(): void
    {
        // Catat Discharge tanggal 2026-11-01 12:00
        $this->job->milestones()->create([
            'company_id' => $this->company->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_DISCHARGE,
            'occurred_at' => '2026-11-01 12:00:00',
            'recorded_at' => now(),
            'is_active' => true,
        ]);

        // Coba DO sebelum discharge -> GAGAL
        $this->expectException(ValidationException::class);
        Milestone::assertValidChronology(
            $this->job,
            Milestone::TYPE_DO_RELEASE,
            Carbon::parse('2026-11-01 10:00:00')
        );
    }

    #[Test]
    public function validasi_kronologis_menolak_gate_in_sebelum_gate_out_pada_kontainer(): void
    {
        // Catat Gate-Out C1 tanggal 2026-11-03 14:00
        $this->job->milestones()->create([
            'company_id' => $this->company->id,
            'container_id' => $this->c1->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
            'occurred_at' => '2026-11-03 14:00:00',
            'recorded_at' => now(),
            'is_active' => true,
        ]);

        // Coba Gate-In C1 sebelum Gate-Out (2026-11-03 12:00) -> WAJIB GAGAL
        $this->expectException(ValidationException::class);
        Milestone::assertValidChronology(
            $this->job,
            Milestone::TYPE_GATE_IN_EMPTY,
            Carbon::parse('2026-11-03 12:00:00'),
            $this->c1->id
        );
    }

    #[Test]
    public function entri_batch_mencatat_gate_out_untuk_beberapa_kontainer_sekaligus(): void
    {
        // Setup discharge dan DO terlebih dahulu
        $this->job->milestones()->createMany([
            [
                'company_id' => $this->company->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_DISCHARGE,
                'occurred_at' => now()->subDays(2),
                'recorded_at' => now()->subDays(2),
                'is_active' => true,
            ],
            [
                'company_id' => $this->company->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_DO_RELEASE,
                'occurred_at' => now()->subDays(1),
                'recorded_at' => now()->subDays(1),
                'is_active' => true,
            ],
        ]);

        // Staf Field menginput batch gate-out untuk C1 dan C2 sekaligus
        $response = $this->actingAs($this->field)->post(route('jobs.milestones.store', $this->job->id), [
            'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
            'container_ids' => [$this->c1->id, $this->c2->id],
            'occurred_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasNoErrors();

        // Keduanya tercatat tersendiri per kontainer (§2.7, Q12)
        $this->assertDatabaseHas('milestones', [
            'job_id' => $this->job->id,
            'container_id' => $this->c1->id,
            'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
        ]);
        $this->assertDatabaseHas('milestones', [
            'job_id' => $this->job->id,
            'container_id' => $this->c2->id,
            'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
        ]);

        // Karena semua kontainer (2/2) sudah gate-out, status menjadi Gate-Out Selesai
        $this->assertEquals(Job::STATUS_GATE_OUT_SELESAI, $this->job->fresh()->cached_status);
    }

    #[Test]
    public function status_agregat_mencatat_sebagian_vs_selesai_untuk_multi_kontainer(): void
    {
        $this->job->milestones()->createMany([
            [
                'company_id' => $this->company->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_DISCHARGE,
                'occurred_at' => now()->subDays(3),
                'recorded_at' => now()->subDays(3),
                'is_active' => true,
            ],
            [
                'company_id' => $this->company->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_DO_RELEASE,
                'occurred_at' => now()->subDays(2),
                'recorded_at' => now()->subDays(2),
                'is_active' => true,
            ],
        ]);

        // 1 dari 2 kontainer gate-out (C1 saja)
        $this->actingAs($this->field)->post(route('jobs.milestones.store', $this->job->id), [
            'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
            'container_id' => $this->c1->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
        ]);

        // Status progres adalah Gate-Out Sebagian (§2.3)
        $this->assertEquals(Job::STATUS_GATE_OUT_SEBAGIAN, $this->job->fresh()->cached_status);

        // 1 dari 2 kontainer gate-in empty (C1 saja)
        $this->actingAs($this->field)->post(route('jobs.milestones.store', $this->job->id), [
            'milestone_type' => Milestone::TYPE_GATE_IN_EMPTY,
            'container_id' => $this->c1->id,
            'occurred_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        // Status progres adalah Gate-In Empty Sebagian (§2.3)
        $this->assertEquals(Job::STATUS_GATE_IN_SEBAGIAN, $this->job->fresh()->cached_status);
    }

    #[Test]
    public function verifikasi_milestone_menegakkan_maker_checker(): void
    {
        $milestone = $this->job->milestones()->create([
            'company_id' => $this->company->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_DISCHARGE,
            'occurred_at' => now(),
            'recorded_at' => now(),
            'created_by_user_id' => $this->ops->id, // diinput oleh Ops 1
            'is_active' => true,
        ]);

        // Ops 1 mencoba verifikasi inputannya sendiri -> WAJIB GAGAL (ADR 0006)
        $this->expectException(ValidationException::class);
        $milestone->verify($this->ops);
    }

    #[Test]
    public function koreksi_pengganti_menonaktifkan_lama_dan_mencatat_alasan_di_audit_trail(): void
    {
        $oldMilestone = $this->job->milestones()->create([
            'company_id' => $this->company->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_DISCHARGE,
            'occurred_at' => '2026-11-01 10:00:00',
            'recorded_at' => now(),
            'created_by_user_id' => $this->ops->id,
            'is_active' => true,
        ]);

        // Ops 2 mengoreksi pengganti karena salah jam fisik
        $response = $this->actingAs($this->ops2)->post(route('milestones.supersede', $oldMilestone->id), [
            'occurred_at' => '2026-11-01 14:30:00',
            'reason' => 'Koreksi jam sandar kapal sesuai berita acara dermaga',
        ]);

        $response->assertSessionHasNoErrors();

        $oldMilestone->refresh();
        $this->assertFalse($oldMilestone->is_active); // lama nonaktif, tidak dihapus (§2.7)

        $newMilestone = Milestone::query()->where('supersedes_id', $oldMilestone->id)->firstOrFail();
        $this->assertTrue($newMilestone->is_active);
        $this->assertEquals('2026-11-01 14:30:00', $newMilestone->occurred_at->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'milestone.superseded',
            'reason' => 'Koreksi jam sandar kapal sesuai berita acara dermaga',
        ]);
    }
}
