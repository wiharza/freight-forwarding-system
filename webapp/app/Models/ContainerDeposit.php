<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerDeposit extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_DITAHAN = 'ditahan';

    public const STATUS_KEMBALI_PENUH = 'dikembalikan_penuh';

    public const STATUS_KEMBALI_SEBAGIAN = 'dikembalikan_sebagian';

    public const STATUS_HANGUS_SEBAGIAN = 'hangus_sebagian';

    public const STATUS_HANGUS_PENUH = 'hangus_penuh';

    protected $fillable = [
        'container_id',
        'job_id',
        'amount_usd',
        'amount_idr',
        'paid_date',
        'status',
        'forfeit_amount_usd',
        'forfeit_amount_idr',
        'returned_date',
        'debit_note_number',
        'debit_note_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:4',
            'amount_idr' => 'decimal:4',
            'forfeit_amount_usd' => 'decimal:4',
            'forfeit_amount_idr' => 'decimal:4',
            'paid_date' => 'date',
            'returned_date' => 'date',
        ];
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function postForfeitToLedger(User $actor): ?JobCost
    {
        if ($this->forfeit_amount_usd <= 0) {
            return null;
        }

        return JobCost::query()->create([
            'company_id' => $this->company_id,
            'job_id' => $this->job_id,
            'cost_type' => JobCost::TYPE_DEPOSIT_FORFEIT,
            'payer_flag' => JobCost::PAYER_PPJK,
            'currency_code' => 'USD',
            'amount_valas' => $this->forfeit_amount_usd,
            'kurs_internal_rate' => 1,
            'amount_idr' => $this->forfeit_amount_idr,
            'status' => JobCost::STATUS_AKTUAL,
            'bukti_number' => $this->debit_note_number,
            'bukti_path' => $this->debit_note_path,
            'notes' => "Deposit kontainer {$this->container->container_number} hangus (debit note)",
            'created_by_user_id' => $actor->id,
        ]);
    }
}
