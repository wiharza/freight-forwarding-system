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

    public function company(): ?Company
    {
        return Company::query()->find($this->id());
    }
}
