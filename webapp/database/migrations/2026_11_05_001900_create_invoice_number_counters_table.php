<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan audit 8 item 2026-11-05 (item 4): penomoran invoice `max + 1`
 * tidak tahan konkurensi — dua invoice bersamaan menghitung urutan yang
 * sama dan yang kedua gagal di unique constraint (500), bukan error yang
 * bersih. Counter per tahun ini dikunci (`lockForUpdate`) di dalam
 * transaksi pembuatan invoice yang sama, sehingga nomor selalu unik
 * tanpa menabrak constraint. Satu baris per tahun; `next_sequence`
 * adalah urutan berikutnya yang akan dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_sequence')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_counters');
    }
};
