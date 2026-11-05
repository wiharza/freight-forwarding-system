<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan audit lanjutan 2026-11-05 (item 3):
 *  - Barang lelang: PPh 22 = 7,5% dari HARGA JUAL LELANG (PMK 51/2025
 *    Pasal 3 ayat (1)), bukan dari Nilai Impor. Butuh kolom harga lelang.
 *  - Pembebasan PPh 22 Pasal 4: wajib referensi dokumen pendukung
 *    (spesifikasi §3.3: dokumen pendukung diupload, alasan tampil di
 *    Snapshot PIB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customs_items', function (Blueprint $table) {
            $table->boolean('is_auction')->default(false)->after('is_pph22_exempt');
            $table->decimal('auction_price_idr', 18, 4)->nullable()->after('is_auction');
            $table->string('exemption_document_ref', 100)->nullable()->after('exemption_reason');
        });
    }

    public function down(): void
    {
        Schema::table('customs_items', function (Blueprint $table) {
            $table->dropColumn(['is_auction', 'auction_price_idr', 'exemption_document_ref']);
        });
    }
};
