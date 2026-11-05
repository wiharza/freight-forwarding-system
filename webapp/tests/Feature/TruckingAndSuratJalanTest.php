<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\Importir;
use App\Models\Job;
use App\Models\Location;
use App\Models\ShippingLine;
use App\Models\SuratJalan;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRoute;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class TruckingAndSuratJalanTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $ops;

    private User $field;

    private Job $job;

    private Container $c1;

    private Container $c2;

    private Vendor $vendor;

    private VendorRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->roles()->sync([$this->roleId('admin')]);

        $this->field = User::factory()->create(['company_id' => $this->company->id]);
        $this->field->roles()->sync([$this->roleId('field')]);

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

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

        $this->c2 = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU2222222',
            'size' => '40',
            'type' => 'Dry',
            'gross_weight_kg' => '25000',
        ]);

        $this->c20a = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'SITU3333333',
            'size' => '20',
            'type' => 'Dry',
            'gross_weight_kg' => '20000',
        ]);
        $this->c20b = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'SITU4444445',
            'size' => '20',
            'type' => 'Dry',
            'gross_weight_kg' => '20000',
        ]);

        $this->vendor = Vendor::factory()->create([
            'company_id' => $this->company->id,
            'vendor_type' => Vendor::TYPE_EKSTERNAL,
        ]);

        $port = Location::factory()->create([
            'company_id' => $this->company->id,
            'type' => Location::TYPE_PORT,
            'name' => 'Tanjung Priok',
        ]);
        $gudang = Location::factory()->create([
            'company_id' => $this->company->id,
            'type' => Location::TYPE_WAREHOUSE,
            'name' => 'Gudang Importir',
        ]);

        $this->route = VendorRoute::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'origin_location_id' => $port->id,
            'destination_location_id' => $gudang->id,
            'container_size' => '40',
            'base_price_usd' => '500.0000',
            'effective_from' => '2026-01-01',
        ]);
    }

    #[Test]
    public function ops_dapat_membuat_trip_dengan_kontainer_batch(): void
    {
        $payload = [
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
            'trip_number' => 'TRP-001',
            'scheduled_date' => '2026-11-10',
            'driver_name' => 'Budi Santoso',
            'driver_phone' => '081234567890',
            'vehicle_number' => 'B 1234 ABC',
            'origin_location_id' => $this->vendor->id, // sementara pakai vendor id sebagai placeholder
            'destination_location_id' => $this->vendor->id,
            'container_ids' => [$this->c1->id, $this->c2->id],
        ];

        $response = $this->actingAs($this->ops)->post(route('trips.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('trips', [
            'job_id' => $this->job->id,
            'trip_number' => 'TRP-001',
            'vendor_id' => $this->vendor->id,
            'status' => Trip::STATUS_ASSIGNED,
        ]);

        $trip = Trip::query()->where('trip_number', 'TRP-001')->firstOrFail();
        $this->assertCount(2, $trip->containers);
    }

    #[Test]
    public function trip_20ft_bisa_dua_kontainer_per_trip(): void
    {
        $trip = Trip::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
            'trip_number' => 'TRP-20FT-001',
            'scheduled_date' => '2026-11-15',
        ]);

        $trip->containers()->sync([
            $this->c20a->id => ['sequence' => 1],
            $this->c20b->id => ['sequence' => 2],
        ]);

        $this->assertCount(2, $trip->containers);
        $this->assertEquals(1, $trip->containers->first()->pivot->sequence);
    }

    #[Test]
    public function surat_jalan_dibuat_per_trip_dengan_field_wajib(): void
    {
        $trip = Trip::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
        ]);
        $trip->containers()->sync([
            $this->c1->id => ['sequence' => 1],
        ]);

        $payload = [
            'sj_number' => 'SJ-001',
            'sj_date' => '2026-11-10',
            'driver_name' => 'Budi Santoso',
            'driver_phone' => '081234567890',
            'vehicle_number' => 'B 1234 ABC',
            'origin_address' => 'Tanjung Priok, Jakarta',
            'destination_address' => 'Gudang Importir, Bekasi',
            'containers_info' => [
                [
                    'container_number' => $this->c1->container_number,
                    'seal_number' => 'SEAL-001',
                    'gross_weight_kg' => 20000,
                ],
            ],
        ];

        $response = $this->actingAs($this->ops)->post(route('surat-jalan.store', ['trip' => $trip->id]), $payload);

        $response->assertSessionHasNoErrors();
        $sj = SuratJalan::where('sj_number', 'SJ-001')->firstOrFail();
        $this->assertEquals(SuratJalan::STATUS_DRAFT, $sj->status);
        $this->assertEquals('Budi Santoso', $sj->driver_name);
        $this->assertEquals('B 1234 ABC', $sj->vehicle_number);
    }

    #[Test]
    public function surat_jalan_dapat_ditandatangani_dan_menjadi_pod(): void
    {
        $sj = SuratJalan::factory()->create([
            'company_id' => $this->company->id,
            'trip_id' => $this->vendor->trips()->first()->id ?? Trip::factory()->create(['company_id' => $this->company->id, 'job_id' => $this->job->id, 'vendor_id' => $this->vendor->id])->id,
            'sj_number' => 'SJ-001',
            'sj_date' => '2026-11-10',
            'driver_name' => 'Budi',
            'driver_phone' => '081234567890',
            'vehicle_number' => 'B 1234 ABC',
            'origin_address' => 'Tanjung Priok',
            'destination_address' => 'Gudang Importir',
            'status' => SuratJalan::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->ops)->post(route('surat-jalan.sign', $sj->id));

        $response->assertSessionHasNoErrors();
        $sj->refresh();
        $this->assertEquals(SuratJalan::STATUS_SIGNED, $sj->status);
        $this->assertNotNull($sj->pod_signed_at);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'surat_jalan.signed',
        ]);
    }

    #[Test]
    public function pod_bisa_diupload_sebagai_bukti_terkirim(): void
    {
        Storage::fake('public');

        $sj = SuratJalan::factory()->create([
            'company_id' => $this->company->id,
            'trip_id' => $this->vendor->trips()->first()->id ?? Trip::factory()->create(['company_id' => $this->company->id, 'job_id' => $this->job->id, 'vendor_id' => $this->vendor->id])->id,
            'sj_number' => 'SJ-001',
            'status' => SuratJalan::STATUS_DRAFT,
        ]);

        $file = UploadedFile::fake()->create('pod_sj001.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($this->ops)->post(route('surat-jalan.upload-pod', $sj->id), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $sj->refresh();

        $this->assertNotNull($sj->pod_file_path);
        $this->assertEquals(SuratJalan::STATUS_SIGNED, $sj->status);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'surat_jalan.pod_uploaded',
        ]);
    }

    #[Test]
    public function vendor_route_dibuat_dengan_harga_per_rute_per_ukuran(): void
    {
        $port = Location::factory()->create([
            'company_id' => $this->company->id,
            'type' => Location::TYPE_PORT,
            'name' => 'Tanjung Priok',
        ]);
        $gudang = Location::factory()->create([
            'company_id' => $this->company->id,
            'type' => Location::TYPE_WAREHOUSE,
            'name' => 'Gudang Utama',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.master.vendor-routes.store'), [
            'vendor_id' => $this->vendor->id,
            'origin_location_id' => $port->id,
            'destination_location_id' => $gudang->id,
            'container_size' => '40',
            'base_price_usd' => '550.0000',
            'include_toll' => true,
            'include_unloading' => true,
            'effective_from' => '2026-11-01',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // Verify route was created with correct values (SQLite stores dates with time suffix)
        $route = VendorRoute::query()
            ->where('vendor_id', $this->vendor->id)
            ->where('container_size', '40')
            ->where('include_toll', true)
            ->where('include_unloading', true)
            ->where('effective_from', '>=', '2026-11-01')
            ->where('effective_from', '<', '2026-11-02')
            ->first();

        $this->assertNotNull($route, 'Vendor route with specified parameters should be created');
        $this->assertEquals(550.0, (float) $route->base_price_usd);
    }
}
