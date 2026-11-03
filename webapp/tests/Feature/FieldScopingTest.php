<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class FieldScopingTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $ops;

    private User $fieldOnly;

    private User $fieldPlusOps;

    private User $tanpaPeran;

    private Job $assigned;

    private Job $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->fieldOnly = User::factory()->create(['company_id' => $this->company->id]);
        $this->fieldOnly->roles()->sync([$this->roleId('field')]);

        $this->fieldPlusOps = User::factory()->create(['company_id' => $this->company->id]);
        $this->fieldPlusOps->roles()->sync([$this->roleId('field'), $this->roleId('ops')]);

        $this->tanpaPeran = User::factory()->create(['company_id' => $this->company->id]);

        $this->assigned = Job::factory()->create(['company_id' => $this->company->id]);
        $this->other = Job::factory()->create(['company_id' => $this->company->id]);

        $this->assigned->assignField($this->fieldOnly, $this->ops);
        $this->assigned->assignField($this->fieldPlusOps, $this->ops);
    }

    #[Test]
    public function ops_melihat_semua_job(): void
    {
        $visible = Job::query()->visibleTo($this->ops)->pluck('id');

        $this->assertCount(2, $visible);
    }

    #[Test]
    public function field_murni_hanya_melihat_job_yang_ditugaskan(): void
    {
        $visible = Job::query()->visibleTo($this->fieldOnly)->pluck('id');

        $this->assertSame([$this->assigned->id], $visible->all());
    }

    #[Test]
    public function field_yang_juga_ops_melihat_semua_job(): void
    {
        $visible = Job::query()->visibleTo($this->fieldPlusOps)->pluck('id');

        $this->assertCount(2, $visible);
    }

    #[Test]
    public function user_tanpa_peran_melihat_kosong(): void
    {
        $visible = Job::query()->visibleTo($this->tanpaPeran)->pluck('id');

        $this->assertCount(0, $visible);
    }

    #[Test]
    public function kebijakan_view_menolak_field_pada_job_yang_tidak_ditugaskan(): void
    {
        $this->assertTrue($this->fieldOnly->can('view', $this->assigned));
        $this->assertFalse($this->fieldOnly->can('view', $this->other));
        $this->assertTrue($this->ops->can('view', $this->other));
    }
}
