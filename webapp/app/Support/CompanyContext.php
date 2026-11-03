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
