<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Counter nomor invoice per tahun (temuan audit 8 item 2026-11-05,
 * item 4). Baris dikunci (`lockForUpdate`) di dalam transaksi pembuatan
 * invoice — pengganti `max + 1` yang bisa bertabrakan saat dua invoice
 * dibuat bersamaan. Nomor invoice bersifat global (bukan per company),
 * sama seperti penomoran `max + 1` sebelumnya.
 */
class InvoiceNumberCounter extends Model
{
    /**
     * Primary key adalah `year` (bukan `id`) — tanpa deklarasi ini,
     * Eloquent meng-UPDATE dengan `WHERE id IS NULL` sehingga
     * increment() diam-diam tidak mengubah apa pun dan semua invoice
     * mendapat nomor yang sama.
     */
    protected $primaryKey = 'year';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'year',
        'next_sequence',
    ];

    protected $casts = [
        'year' => 'integer',
        'next_sequence' => 'integer',
    ];
}
