<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Kurs Pajak Kemenkeu (NDPBM - Rabu s.d Selasa) (§3.5, PV-3)
        Schema::create('tax_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kmk_number', 50); // e.g. KMK 51/MK/EF.2/2026
            $table->date('valid_from'); // Hari Rabu
            $table->date('valid_to');   // Hari Selasa
            $table->string('currency_code', 3);
            $table->decimal('rate_to_idr', 18, 4);
            $table->string('source', 30)->default('api_kemenkeu'); // api_kemenkeu, excel_download, manual
            $table->timestamps();

            $table->unique(['company_id', 'kmk_number', 'currency_code', 'valid_from'], 'tax_rate_unique_idx');
        });

        // 2. Header Kalkulasi PIB (§3.1, §3.2, Q28)
        Schema::create('customs_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('pib_number', 50)->nullable(); // No pib internal draf
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft'); // draft, approved, filed, stale
            $table->string('kmk_number', 50)->nullable();
            $table->decimal('total_cif_valas', 18, 4)->default(0);
            $table->decimal('total_cif_idr', 18, 4)->default(0); // presisi penuh tanpa pembulatan prematur
            $table->decimal('total_bm', 18, 4)->default(0);
            $table->decimal('total_ppn', 18, 4)->default(0);
            $table->decimal('total_pph22', 18, 4)->default(0);
            $table->decimal('total_ppnbm', 18, 4)->default(0);
            $table->decimal('total_pungutan_lain', 18, 4)->default(0);
            $table->decimal('total_pungutan', 18, 4)->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'version']);
        });

        // 3. Detail Seri Barang PIB (per Seri Barang - §3.2, Q28)
        Schema::create('customs_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customs_declaration_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('item_number'); // Seri 1, 2, 3...
            $table->string('hs_code', 12); // 8 digit
            $table->string('description')->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('fob_valas', 18, 4)->default(0);
            $table->decimal('freight_valas', 18, 4)->default(0);
            $table->decimal('insurance_valas', 18, 4)->default(0);
            $table->string('insurance_basis', 30)->default('input_riil'); // input_riil, fallback_fob_0.5, fallback_cfr_0.5, dalam_negeri_0
            $table->decimal('kurs_pajak_rate', 18, 4)->default(1);
            $table->decimal('cif_valas', 18, 4)->default(0);
            $table->decimal('cif_rupiah', 18, 4)->default(0); // presisi penuh
            $table->string('bm_type', 10)->default('mfn'); // mfn, fta
            $table->decimal('bm_rate', 6, 4)->default(0);
            $table->decimal('bm_amount', 18, 4)->default(0); // pembulatan ribuan ke atas
            $table->decimal('nilai_impor', 18, 4)->default(0); // CIF + BM + Pungutan lain
            $table->boolean('is_ppn_luxury')->default(false);
            $table->decimal('dpp_ppn', 18, 4)->default(0); // Nilai Impor * 11/12 atau 100%
            $table->decimal('ppn_rate', 6, 4)->default(0.1200);
            $table->decimal('ppn_amount', 18, 4)->default(0); // pembulatan ribuan ke atas
            $table->string('pph22_category', 30)->default('umum');
            $table->decimal('pph22_rate', 6, 4)->default(0.0250);
            $table->decimal('pph22_amount', 18, 4)->default(0); // pembulatan ribuan ke atas
            $table->boolean('is_pph22_exempt')->default(false); // Pengecualian Ps 4
            $table->string('exemption_reason')->nullable();
            $table->decimal('ppnbm_rate', 6, 4)->default(0);
            $table->decimal('ppnbm_amount', 18, 4)->default(0);
            $table->decimal('pungutan_lain_amount', 18, 4)->default(0);
            $table->decimal('total_item_levy', 18, 4)->default(0);
            $table->boolean('is_unverified_warning')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customs_items');
        Schema::dropIfExists('customs_declarations');
        Schema::dropIfExists('tax_exchange_rates');
    }
};
