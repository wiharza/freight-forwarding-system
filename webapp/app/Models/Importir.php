<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Importir extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'name',
        'npwp',
        'nib',
        'status_api',
        'address',
        'phone',
        'email',
        'pic_name',
    ];
}
