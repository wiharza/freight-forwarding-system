<?php

namespace App\Support;

use App\Models\Company;

/**
 * Menyimpan company aktif untuk request yang sedang berjalan.
 * Tiket 02 akan mengisinya dari user yang login (middleware);
 * untuk saat ini default-nya company pertama (deployment single-company).
 */
class CompanyContext
{
    protected ?int $companyId = null;

    /**
     * Company aktif untuk request yang sedang berjalan. Bila belum di-set,
     * fallback-nya adalah company dengan id TERENDAH (deployment
     * single-company). PERHATIAN: fallback ini BUKAN "tenant demo" — seeder
     * dan kode yang membuat data demo WAJIB memanggil set() dengan id tenant
     * yang dimaksud secara eksplisit (lihat DatabaseSeeder), supaya data
     * tidak mendarat di tenant yang salah bila ada company lain ber-id lebih
     * rendah.
     */
    public function id(): int
    {
        if ($this->companyId === null) {
            $this->companyId = (int) (Company::query()->min('id') ?? 0);
        }

        return $this->companyId;
    }

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    /**
     * Tenant eksplisit untuk seeder (TEMUAN LANJUTAN 2026-11-05 — pola
     * yang sama dengan P1-2 audit sebelumnya: fallback ke company pertama
     * membuat data diam-diam mendarat di tenant yang salah). Seeder WAJIB
     * memakai ini, bukan id(): tenant diambil dari set() eksplisit
     * (jalur DatabaseSeeder) atau env SEED_COMPANY_ID; bila keduanya
     * tidak ada, gagal tertutup dengan pesan yang jelas — tidak pernah
     * menebak tenant ber-ID terendah.
     */
    public function requireExplicitId(string $caller = 'Seeder'): int
    {
        if ($this->companyId !== null) {
            return $this->companyId;
        }

        $envId = (int) (env('SEED_COMPANY_ID', 0));
        if ($envId > 0 && Company::query()->whereKey($envId)->exists()) {
            $this->companyId = $envId;

            return $envId;
        }

        throw new \RuntimeException(
            "{$caller} membutuhkan tenant eksplisit: jalankan lewat DatabaseSeeder (php artisan db:seed), ".
            'atau set SEED_COMPANY_ID=<id company yang sudah ada>. Tanpa tenant eksplisit, seeder tidak boleh menebak tenant ber-ID terendah.'
        );
    }

    public function company(): ?Company
    {
        return Company::query()->find($this->id());
    }
}
