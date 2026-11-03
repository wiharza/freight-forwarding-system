<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vessel extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'name',
        'imo_number',
        'call_sign',
        'shipping_line_id',
    ];

    public function shippingLine(): BelongsTo
    {
        return $this->belongsTo(ShippingLine::class);
    }
}
