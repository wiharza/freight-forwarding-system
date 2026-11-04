<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPE_EKSTERNAL = 'eksternal';

    public const TYPE_INTERNAL = 'internal';

    protected $fillable = [
        'name',
        'vendor_type',
        'contact_person',
        'phone',
        'address',
    ];

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
