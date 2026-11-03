<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPE_PORT = 'port';
    public const TYPE_DEPOT = 'depot';
    public const TYPE_WAREHOUSE = 'warehouse';

    protected $fillable = [
        'code',
        'name',
        'type',
        'city',
    ];
}
