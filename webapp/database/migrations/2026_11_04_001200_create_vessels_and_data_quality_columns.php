<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master kapal (DQ-18): nama + IMO berversi per company — sumber pilihan
        // di form Job agar tidak ada salah ketik nama kapal/nomor IMO.
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('imo_number', 15)->nullable(); // 7 digit, dinormalisasi
            $table->string('call_sign', 10)->nullable();
            $table->foreignId('shipping_line_id')->nullable()->constrained('shipping_lines')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'imo_number']);
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->foreignId('vessel_id')->nullable()->after('pod_id')->constrained('vessels')->nullOnDelete();
        });

        // Incoterm sebagai data keterangan PIB (DQ-21) — TIDAK mengubah logika
        // DPP yang terkunci di Q28.
        Schema::table('customs_declarations', function (Blueprint $table) {
            $table->string('incoterm', 3)->nullable()->after('kmk_number');
        });
    }

    public function down(): void
    {
        Schema::table('customs_declarations', function (Blueprint $table) {
            $table->dropColumn('incoterm');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign(['vessel_id']);
            $table->dropColumn('vessel_id');
        });

        Schema::dropIfExists('vessels');
    }
};
