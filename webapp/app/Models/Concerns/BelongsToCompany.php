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

        static::addGlobalScope('company', function (Builder $query) {
            $query->where(
                $query->getModel()->getTable().'.company_id',
                app(CompanyContext::class)->id()
            );
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
