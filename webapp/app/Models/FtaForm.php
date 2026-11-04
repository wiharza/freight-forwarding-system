<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FtaForm extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'job_id',
        'customs_item_id',
        'scheme_code',
        'form_number',
        'hs_code',
        'origin_country',
        'port_of_loading',
        'issued_at',
        'valid_until',
        'file_path',
        'file_name',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CustomsItem::class, 'customs_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
