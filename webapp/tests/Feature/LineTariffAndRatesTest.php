<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Currency;
use App\Models\InternalExchangeRate;
use App\Models\LineTariff;
use App\Models\ShippingLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class LineTariffAndRatesTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;
    private User $admin1;
    private User $admin2;
    private User $finance;
    private ShippingLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();

        $this->admin1 = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin1->roles()->sync([$this->roleId('admin')]);

        $this->admin2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin2->roles()->sync([$this->roleId('admin')]);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->line = ShippingLine::factory()->create(['company_id' => $this->company->id, 'code' => 'SITC']);

        // Pastikan currency USD aktif dan CNY non-aktif
        Currency::query()->firstOrCreate(
            ['company_id' => $this->company->id, 'code' => 'USD'],
            ['name' => 'US Dollar', 'is_active' => true, 'effective_date' => now()->toDateString()]
        );
        Currency::query()->firstOrCreate(
            ['company_id' => $this->company->id, 'code' => 'CNY'],
            ['name' => 'Chinese Yuan', 'is_active' => false]
        );
    }

    #[Test]
    public function konfigurasi_free_time_line_dapat_diperbarui(): void
    {
        $response = $this->actingAs($this->admin1)->patch(route('admin.master.shipping-lines.config', $this->line->id), [
            'free_days_demurrage' => 10,
            'free_days_detention' => 5,
            'start_offset_demurrage' => 'H+0',
            'start_offset_detention' => 'H+1',
            'calendar_type_demurrage' => 'working',
            'calendar_type_detention' => 'calendar',
            'mode' => 'gabungan',
        ]);

        $response->assertSessionHasNoErrors();
        $this->line->refresh();

        $this->assertEquals(10, $this->line->free_days_demurrage);
        $this->assertEquals('H+0', $this->line->start_offset_demurrage);
        $this->assertEquals('working', $this->line->calendar_type_demurrage);
        $this->assertEquals('gabungan', $this->line->mode);
    }

    #[Test]
    public function pengusulan_tarif_line_memerlukan_maker_checker_untuk_approval(): void
    {
        // Admin1 mengajukan tarif (draft)
        $response = $this->actingAs($this->admin1)->post(route('admin.master.line-tariffs.store'), [
            'shipping_line_id' => $this->line->id,
            'charge_type' => 'demurrage',
            'container_size' => '20',
            'day_from' => 1,
            'day_to' => 3,
            'currency_code' => 'USD',
            'rate_per_day' => '35.0000',
            'effective_from' => '2026-11-01',
        ]);

        $response->assertSessionHasNoErrors();
        $tariff = LineTariff::query()->first();
        $this->assertEquals(LineTariff::STATUS_DRAFT, $tariff->status);
        $this->assertEquals($this->admin1->id, $tariff->created_by_user_id);

        // Admin1 (pembuat) MENCOBA approve sendiri -> WAJIB GAGAL (ADR 0006)
        $this->expectException(ValidationException::class);
        $tariff->approve($this->admin1);
    }

    #[Test]
    public function admin_lain_dapat_menyetujui_tarif_line(): void
    {
        $tariff = LineTariff::factory()->create([
            'company_id' => $this->company->id,
            'shipping_line_id' => $this->line->id,
            'created_by_user_id' => $this->admin1->id,
            'status' => LineTariff::STATUS_DRAFT,
        ]);

        // Admin2 menyetujui -> BERHASIL
        $tariff->approve($this->admin2, 'Disetujui sesuai kontrak pelayaran');

        $tariff->refresh();
        $this->assertEquals(LineTariff::STATUS_ACTIVE, $tariff->status);
        $this->assertEquals($this->admin2->id, $tariff->approved_by_user_id);

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'line_tariff.approved',
            'object_id' => $tariff->id,
        ]);
    }

    #[Test]
    public function finance_dapat_menginput_kurs_internal_resmi_harian(): void
    {
        $response = $this->actingAs($this->finance)->post(route('finance.rates.store'), [
            'rate_date' => '2026-11-03',
            'currency_code' => 'USD',
            'rate_to_idr' => '15850.5000',
            'cutoff_time' => '09:00',
        ]);

        $response->assertSessionHasNoErrors();

        $rate = InternalExchangeRate::query()
            ->where('company_id', $this->company->id)
            ->where('currency_code', 'USD')
            ->first();

        $this->assertNotNull($rate);
        $this->assertEquals('2026-11-03', $rate->rate_date->toDateString());
        $this->assertEquals('15850.5000', (string) $rate->rate_to_idr);
        $this->assertEquals('09:00:00', $rate->cutoff_time);
    }

    #[Test]
    public function kurs_internal_menolak_mata_uang_nonaktif(): void
    {
        // CNY berstatus is_active = false
        $response = $this->actingAs($this->finance)->post(route('finance.rates.store'), [
            'rate_date' => '2026-11-03',
            'currency_code' => 'CNY',
            'rate_to_idr' => '2200.0000',
        ]);

        $response->assertSessionHasErrors(['currency_code']);
        $this->assertDatabaseMissing('internal_exchange_rates', [
            'currency_code' => 'CNY',
        ]);
    }

    #[Test]
    public function kurs_yang_terkunci_oleh_invoice_tidak_dapat_diedit(): void
    {
        $rate = InternalExchangeRate::factory()->create([
            'company_id' => $this->company->id,
            'rate_date' => '2026-11-01',
            'currency_code' => 'USD',
            'rate_to_idr' => '15800.0000',
            'is_locked' => true, // sudah dipakai invoice
        ]);

        $this->expectException(LogicException::class);
        $rate->update(['rate_to_idr' => '16000.0000']);
    }

    #[Test]
    public function resolver_kurs_mengembalikan_flag_resmi_hari_ini_atau_fallback_draf(): void
    {
        // Set kurs kemarin
        InternalExchangeRate::factory()->create([
            'company_id' => $this->company->id,
            'rate_date' => '2026-11-02',
            'currency_code' => 'USD',
            'rate_to_idr' => '15800.0000',
        ]);

        // Cek resolve untuk hari ini (belum ada kurs 2026-11-03)
        $resolved = InternalExchangeRate::resolveRate('USD', \Illuminate\Support\Carbon::parse('2026-11-03'));
        $this->assertEquals('15800.0000', $resolved['rate']);
        $this->assertFalse($resolved['is_official_today']); // draf flag §6.6

        // Input kurs hari ini
        InternalExchangeRate::factory()->create([
            'company_id' => $this->company->id,
            'rate_date' => '2026-11-03',
            'currency_code' => 'USD',
            'rate_to_idr' => '15850.0000',
        ]);

        $resolvedToday = InternalExchangeRate::resolveRate('USD', \Illuminate\Support\Carbon::parse('2026-11-03'));
        $this->assertEquals('15850.0000', $resolvedToday['rate']);
        $this->assertTrue($resolvedToday['is_official_today']);
    }
}
