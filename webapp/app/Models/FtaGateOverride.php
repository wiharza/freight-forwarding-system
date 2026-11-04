<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FtaGateOverride extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'customs_declaration_id',
        'customs_item_id',
        'fta_form_id',
        'reason',
        'attachment_path',
        'attachment_name',
        'created_by_user_id',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(CustomsDeclaration::class, 'customs_declaration_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CustomsItem::class, 'customs_item_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FtaForm::class, 'fta_form_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
