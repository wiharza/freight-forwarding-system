<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\Importir;
use App\Models\Job;
use App\Models\ShippingLine;
use App\Models\SuratJalan;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vendor;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes regresi temuan audit 8 item (2026-11-05), item 7 — Trip dan
 * Surat Jalan terkunci bila Job tertutup (`JobClose::assertNotClosed`,
 * pola yang sama dengan 11 titik tulis yang sudah dikunci fitur close):
 * trip store + update status, SJ store + sign + upload POD.
 */
class Audit8ItemTutupTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $finance;

    private User $ops;

    private Job $job;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
        ]);

        $this->container = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU1234565',
            'size' => "40'",
            'type' => 'High Cube',
        ]);
    }

    #[Test]
    public function trip_tidak_bisa_dibuat_untuk_job_tertutup(): void
    {
        $this->tutupJob();

        $vendor = Vendor::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->ops)->post(route('trips.store'), [
            'job_id' => $this->job->id,
            'vendor_id' => $vendor->id,
            'trip_number' => 'TRP-001',
            'scheduled_date' => '2026-11-06',
            'container_ids' => [$this->container->id],
        ]);

        $response->assertSessionHasErrors('job_closed');
        $this->assertDatabaseMissing('trips', ['trip_number' => 'TRP-001']);
    }

    #[Test]
    public function status_trip_tidak_bisa_diubah_untuk_job_tertutup(): void
    {
        $trip = $this->buatTrip();
        $this->tutupJob();

        $response = $this->actingAs($this->ops)->patch(route('trips.status', $trip->id), [
            'status' => Trip::STATUS_CONFIRMED,
        ]);

        $response->assertSessionHasErrors('job_closed');
        $this->assertSame(Trip::STATUS_ASSIGNED, $trip->fresh()->status);
    }

    #[Test]
    public function surat_jalan_tidak_bisa_dibuat_atau_ditandatangani_untuk_job_tertutup(): void
    {
        $trip = $this->buatTrip();

        // Surat Jalan dibuat saat Job masih terbuka (kontrol positif).
        $sj = $this->buatSuratJalan($trip);
        $this->assertSame(SuratJalan::STATUS_DRAFT, $sj->status);

        $this->tutupJob();

        // Buat SJ baru → ditolak.
        $response = $this->actingAs($this->ops)->post(route('surat-jalan.store', $trip->id), $this->payloadSj());
        $response->assertSessionHasErrors('job_closed');

        // Tanda tangan SJ yang sudah ada → ditolak.
        $response = $this->actingAs($this->ops)->post(route('surat-jalan.sign', $sj->id));
        $response->assertSessionHasErrors('job_closed');
        $this->assertSame(SuratJalan::STATUS_DRAFT, $sj->fresh()->status);

        // Upload POD → ditolak.
        $response = $this->actingAs($this->ops)->post(route('surat-jalan.upload-pod', $sj->id), [
            'file' => UploadedFile::fake()->create('pod.pdf', 100, 'application/pdf'),
        ]);
        $response->assertSessionHasErrors('job_closed');
        $this->assertNull($sj->fresh()->pod_file_path);
    }

    #[Test]
    public function trip_dan_surat_jalan_tetap_bisa_dibuat_untuk_job_terbuka(): void
    {
        $trip = $this->buatTrip();
        $sj = $this->buatSuratJalan($trip);

        $response = $this->actingAs($this->ops)->post(route('surat-jalan.sign', $sj->id));
        $response->assertSessionHasNoErrors();
        $this->assertSame(SuratJalan::STATUS_SIGNED, $sj->fresh()->status);
    }

    private function buatTrip(): Trip
    {
        $vendor = Vendor::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->ops)->post(route('trips.store'), [
            'job_id' => $this->job->id,
            'vendor_id' => $vendor->id,
            'trip_number' => 'TRP-'.random_int(100, 999),
            'scheduled_date' => '2026-11-06',
            'container_ids' => [$this->container->id],
        ]);
        $response->assertSessionHasNoErrors();

        return Trip::query()->latest('id')->firstOrFail();
    }

    private function payloadSj(): array
    {
        return [
            'sj_number' => 'SJ-'.random_int(100, 999),
            'sj_date' => '2026-11-06',
            'driver_name' => 'Budi',
            'driver_phone' => '08123456789',
            'vehicle_number' => 'BK 1234 AB',
            'origin_address' => 'Pelabuhan Belawan',
            'destination_address' => 'Gudang Medan',
            'containers_info' => [
                ['container_number' => 'MSKU1234565', 'seal_number' => 'SEAL-1', 'gross_weight_kg' => 20000],
            ],
        ];
    }

    private function buatSuratJalan(Trip $trip): SuratJalan
    {
        $response = $this->actingAs($this->ops)->post(route('surat-jalan.store', $trip->id), $this->payloadSj());
        $response->assertSessionHasNoErrors();

        return SuratJalan::query()->latest('id')->firstOrFail();
    }

    /** Tutup Job secara langsung (bypass alur close — yang diuji adalah kuncinya). */
    private function tutupJob(): void
    {
        $this->job->update([
            'closed_at' => now(),
            'closed_by_user_id' => $this->finance->id,
            'close_reason' => 'Ditutup untuk pengujian kunci tutup.',
            'cached_status' => Job::STATUS_CLOSED,
        ]);
    }
}
