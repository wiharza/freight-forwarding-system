<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Support\CompanyContext;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes reproduksi + regresi temuan audit lanjutan 2026-11-05, item 6:
 * seeder standalone wajib tenant eksplisit.
 */
class AuditLanjutanSeederTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private Company $company2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        $this->company2 = Company::factory()->create();
    }

    // ------------------------------------------------------------------
    // Temuan 6 — seeder standalone wajib tenant eksplisit
    // ------------------------------------------------------------------

    #[Test]
    public function master_data_seeder_standalone_tanpa_tenant_ditolak(): void
    {
        // Konteks tenant TIDAK di-set (fresh) — seeder lama diam-diam memakai
        // company ber-ID terendah (company 1 di sini).
        app(CompanyContext::class)->set(null);

        $this->expectException(RuntimeException::class);

        (new MasterDataSeeder)->run();
    }

    #[Test]
    public function master_data_seeder_dengan_tenant_eksplisit_memakai_tenant_itu(): void
    {
        app(CompanyContext::class)->set(null);
        putenv('SEED_COMPANY_ID='.$this->company2->id);

        try {
            (new MasterDataSeeder)->run();
        } finally {
            putenv('SEED_COMPANY_ID');
        }

        // Data mendarat di company 2 saja — bukan company 1 (ID terendah).
        $this->assertDatabaseHas('currencies', ['company_id' => $this->company2->id, 'code' => 'USD']);
        $this->assertDatabaseMissing('currencies', ['company_id' => $this->company->id]);
        $this->assertDatabaseHas('importirs', ['company_id' => $this->company2->id]);
    }
}
