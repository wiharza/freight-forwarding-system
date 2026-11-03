<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * ADR 0006 — maker-checker ditegakkan per user ID: pembuat sebuah record
 * tidak boleh menjadi penyetuju aksi atas record itu, walau ia memegang
 * beberapa peran sekaligus. Konvensi: record yang mendapat approval menyimpan
 * `created_by_user_id`.
 */
class MakerChecker
{
    public static function assertApproverDiffers(Model $user, object $record): void
    {
        $creatorId = $record->created_by_user_id ?? null;

        if ($creatorId !== null && (int) $creatorId === (int) $user->getAuthIdentifier()) {
            throw ValidationException::withMessages([
                'maker_checker' => 'Pembuat tidak boleh menyetujui karyanya sendiri (Maker-Checker, ADR 0006).',
            ]);
        }
    }
}
