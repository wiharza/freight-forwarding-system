<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'free_days_demurrage',
        'free_days_detention',
        'start_offset_demurrage',
        'start_offset_detention',
        'calendar_type_demurrage',
        'calendar_type_detention',
        'mode',
    ];

    public function tariffs(): HasMany
    {
        return $this->hasMany(LineTariff::class);
    }
}
