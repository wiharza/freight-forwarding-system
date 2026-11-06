<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan user 2026-11-06: referensi dokumen pembebasan PPh 22 Pasal 4
 * (spesifikasi §3.3) bukan lagi teks bebas — harus menunjuk ke dokumen
 * yang SUDAH DIUPLOAD di checklist dokumen Job yang sama (FK ke
 * job_documents). Teks bebas memungkinkan "SK-123/MK/2026" diketik tanpa
 * bukti apa pun; FK memaksa buktinya benar-benar ada.
 *
 * Kolom lama `exemption_document_ref` DIHAPUS (clean break): kolom itu baru
 * berumur sehari (ditambahkan PR #13, 2026-11-05), sistem belum live, dan
 * tidak ada data produksi — hanya data uji di VPS. Tidak ada kode lain
 * yang membacanya setelah perubahan ini (PibCalculator, controller,
 * pib.tsx, dan tes diperbarui ke `exemption_document_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customs_items', function (Blueprint $table) {
            $table->foreignId('exemption_document_id')->nullable()->after('exemption_reason')->constrained('job_documents')->nullOnDelete();
            $table->dropColumn('exemption_document_ref');
        });
    }

    public function down(): void
    {
        Schema::table('customs_items', function (Blueprint $table) {
            $table->dropForeign(['exemption_document_id']);
            $table->dropColumn('exemption_document_id');
            $table->string('exemption_document_ref', 100)->nullable()->after('exemption_reason');
        });
    }
};
