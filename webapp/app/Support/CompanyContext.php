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
     * Company aktif untuk request yang sedang berjalan.
     *
     * TEMUAN AUDIT 8 ITEM (2026-11-05, item 1): fallback ke company ber-ID
     * terendah DIHAPUS — fallback itu yang membuat Job tenant lain bisa
     * dibuka lewat URL saat context belum di-set (route model binding jalan
     * sebelum middleware, atau jalur console yang lupa mengeset context).
     * Sumber tenant sekarang, berurutan: (1) set() eksplisit — middleware
     * SetCompanyContext (web, sebelum binding) atau loop per-company di
     * console; (2) user yang login. Bila keduanya tidak ada, gagal
     * tertutup dengan pesan jelas — tidak pernah menebak tenant.
     */
    public function id(): int
    {
        if ($this->companyId !== null) {
            return $this->companyId;
        }

        if (auth()->check()) {
            return $this->companyId = (int) auth()->user()->company_id;
        }

        throw new \RuntimeException(
            'CompanyContext belum di-set dan tidak ada user yang login: tenant tidak boleh ditebak. '.
            'Di request web, middleware SetCompanyContext mengesetnya dari user yang login; '.
            'di console, set tenant secara eksplisit per company (lihat FreeTimeAlertService::checkAll).'
        );
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
