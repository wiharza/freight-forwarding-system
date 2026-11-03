<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master Tarif MFN per HS (BTKI 2022 + delta PMK 62/2025 - §3.6)
        Schema::create('mfn_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('hs_code', 12); // format 8 digit e.g. 8471.30.20
            $table->string('description')->nullable();
            $table->decimal('bm_rate', 6, 4)->default(0); // 0.0500 = 5%
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('verification_status', 20)->default('terverifikasi'); // terverifikasi, perlu_verifikasi (ADR 0008)
            $table->string('legal_basis')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'hs_code', 'effective_from']);
        });

        // 2. Master Tarif Preferensi FTA per skema (PMK 88/2023 - 9 skema aktif - §3.6, PV-9)
        Schema::create('fta_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('scheme_code', 20); // ACFTA, ATIGA, AKFTA, AIFTA, AANZFTA, IJEPA, IA-CEPA, IC-CEPA, RCEP
            $table->string('hs_code', 12);
            $table->decimal('fta_rate', 6, 4)->default(0); // 0.0000 = 0%
            $table->string('coo_type', 20)->nullable(); // Form E, Form D, Form AK, Form AI, Form ANZ, dsb.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('verification_status', 20)->default('terverifikasi');
            $table->string('legal_basis')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'scheme_code', 'hs_code', 'effective_from']);
        });

        // 3. Mapping HS -> Lampiran PPh 22 Impor (PMK 51/2025 - 215 pos seed - §3.2, PV-8)
        Schema::create('pph22_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('hs_code', 12); // Pos 4 digit (e.g. 33.04) atau 8 digit
            $table->string('lampiran', 5); // A (10%), B (7.5%), KHUSUS (0.5%/0.25%)
            $table->decimal('rate', 6, 4); // 0.1000, 0.0750, 0.0050, 0.0025
            $table->string('notes')->nullable();
            $table->string('verification_status', 20)->default('terverifikasi');
            $table->string('legal_basis')->default('PMK 51/2025');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'hs_code']);
        });

        // 4. Daftar Barang Mewah (PPN 12% penuh & PPnBM ambang Nilai Impor - §3.2, §3.6, PV-6)
        Schema::create('luxury_goods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('hs_code', 12);
            $table->string('category', 50); // kendaraan, pesawat_kapal, hunian, umum
            $table->boolean('is_ppn_luxury')->default(false); // jika true, PPN 12% penuh (bukan 11/12)
            $table->boolean('has_ppnbm')->default(false);
            $table->decimal('ppnbm_rate', 6, 4)->default(0);
            $table->decimal('import_value_threshold_usd', 18, 4)->nullable(); // Ambang USD 30.000 / 50.000
            $table->string('verification_status', 20)->default('terverifikasi');
            $table->string('legal_basis')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'hs_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('luxury_goods');
        Schema::dropIfExists('pph22_rules');
        Schema::dropIfExists('fta_tariffs');
        Schema::dropIfExists('mfn_tariffs');
    }
};
