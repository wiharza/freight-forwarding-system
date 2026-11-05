<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomsItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'customs_declaration_id',
        'item_number',
        'hs_code',
        'description',
        'currency_code',
        'fob_valas',
        'freight_valas',
        'insurance_valas',
        'insurance_basis',
        'kurs_pajak_rate',
        'cif_valas',
        'cif_rupiah',
        'bm_type',
        'bm_rate',
        'bm_amount',
        'nilai_impor',
        'is_ppn_luxury',
        'dpp_ppn',
        'ppn_rate',
        'ppn_amount',
        'pph22_category',
        'pph22_rate',
        'pph22_amount',
        'is_pph22_exempt',
        'exemption_reason',
        'exemption_document_ref',
        'is_auction',
        'auction_price_idr',
        'ppnbm_rate',
        'ppnbm_amount',
        'pungutan_lain_amount',
        'total_item_levy',
        'is_unverified_warning',
    ];

    protected function casts(): array
    {
        return [
            'fob_valas' => 'decimal:4',
            'freight_valas' => 'decimal:4',
            'insurance_valas' => 'decimal:4',
            'kurs_pajak_rate' => 'decimal:4',
            'cif_valas' => 'decimal:4',
            'cif_rupiah' => 'decimal:4',
            'bm_rate' => 'decimal:4',
            'bm_amount' => 'decimal:4',
            'nilai_impor' => 'decimal:4',
            'is_ppn_luxury' => 'boolean',
            'dpp_ppn' => 'decimal:4',
            'ppn_rate' => 'decimal:4',
            'ppn_amount' => 'decimal:4',
            'pph22_rate' => 'decimal:4',
            'pph22_amount' => 'decimal:4',
            'is_pph22_exempt' => 'boolean',
            'is_auction' => 'boolean',
            'auction_price_idr' => 'decimal:4',
            'ppnbm_rate' => 'decimal:4',
            'ppnbm_amount' => 'decimal:4',
            'pungutan_lain_amount' => 'decimal:4',
            'total_item_levy' => 'decimal:4',
            'is_unverified_warning' => 'boolean',
        ];
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(CustomsDeclaration::class, 'customs_declaration_id');
    }
}
