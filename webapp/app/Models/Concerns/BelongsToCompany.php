<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0001 — setiap model bisnis memakai trait ini sehingga:
 *  1. company_id terisi otomatis saat creating (dari CompanyContext), dan
 *  2. semua query terisolasi per company via global scope.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if (! $model->company_id) {
                $model->company_id = app(CompanyContext::class)->id();
            }
        });

        // TEMUAN AUDIT 8 ITEM (2026-11-05, item 1): User dikecualikan dari
        // scope tenant — login harus mencari user di SEMUA tenant (tenant
        // baru diketahui SETELAH user ditemukan). Tanpa pengecualian ini,
        // user tenant kedua tidak bisa login (scope menyaring company_id =
        // tenant ber-ID terendah), dan CompanyContext::id() yang gagal
        // tertutup akan melempar saat proses autentikasi.
        if (static::isTenantScoped()) {
            static::addGlobalScope('company', function (Builder $query) {
                $query->where(
                    $query->getModel()->getTable().'.company_id',
                    app(CompanyContext::class)->id()
                );
            });
        }
    }

    /**
     * Model identitas (User) tidak di-scope per tenant — lihat komentar di
     * bootBelongsToCompany(). Model bisnis lain selalu true.
     */
    protected static function isTenantScoped(): bool
    {
        return true;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
