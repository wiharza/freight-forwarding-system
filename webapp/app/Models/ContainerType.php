<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContainerType extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'size',
        'type',
        'description',
    ];
}
