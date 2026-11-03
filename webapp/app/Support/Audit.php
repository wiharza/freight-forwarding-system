<?php

namespace App\Support;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat audit trail (§1.5): siapa, kapan, objek apa, apa yang berubah,
 * dan alasannya. Wajib dipanggil untuk setiap approval, override, dan
 * perubahan angka yang menjadi dasar pemberitahuan pabean.
 */
class Audit
{
    public static function record(string $action, Model $object, ?array $changes = null, ?string $reason = null): AuditTrail
    {
        return AuditTrail::query()->create([
            'company_id' => $object->company_id ?? app(CompanyContext::class)->id(),
            'user_id' => auth()->id(),
            'action' => $action,
            'object_type' => $object::class,
            'object_id' => $object->getKey(),
            'changes' => $changes,
            'reason' => $reason,
        ]);
    }
}
