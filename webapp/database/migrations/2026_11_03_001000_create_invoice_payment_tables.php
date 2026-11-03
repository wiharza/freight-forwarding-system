<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Invoice Header (§6.4–6.5, Q17, Q31, ADR 0004, ADR 0007)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('importir_id')->constrained('importirs')->cascadeOnDelete();
            $table->string('invoice_number', 50)->unique(); // berurutan tanpa lompatan
            $table->date('invoice_date');
            $table->date('due_date')->nullable(); // untuk aging (§7c)
            $table->string('status', 20)->default('draft'); // draft, approved, locked, cancelled
            // Blok (a) — pungutan negara (dari Snapshot PIB, tanpa PPN jasa)
            $table->decimal('blok_a_bm', 18, 4)->default(0);
            $table->decimal('blok_a_ppn_impor', 18, 4)->default(0);
            $table->decimal('blok_a_pph22', 18, 4)->default(0);
            $table->decimal('blok_a_ppnbm', 18, 4)->default(0);
            $table->decimal('blok_a_pungutan_lain', 18, 4)->default(0);
            $table->decimal('blok_a_total', 18, 4)->default(0);
            $table->unsignedBigInteger('snapshot_pib_id')->nullable(); // referensi snapshot
            // Blok (b) — biaya pihak ketiga (DPP & PPN dari faktur vendor)
            $table->decimal('blok_b_total', 18, 4)->default(0);
            $table->decimal('blok_b_ppn', 18, 4)->default(0);
            // Blok (c) — jasa PPJK + PPN
            $table->decimal('blok_c_service_fee', 18, 4)->default(0);
            $table->decimal('blok_c_dpp_lain', 18, 4)->default(0); // DPP Nilai Lain 11/12
            $table->decimal('blok_c_ppn_rate', 6, 4)->default(0.1200);
            $table->decimal('blok_c_ppn', 18, 4)->default(0);
            // Total invoice
            $table->decimal('total_invoice', 18, 4)->default(0);
            $table->decimal('total_paid', 18, 4)->default(0);
            // Approval & batas
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('faktur_pajak_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Invoice Items (per baris biaya dari ledger) (§6.4)
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_cost_id')->nullable()->constrained('job_costs')->nullOnDelete();
            $table->string('blok', 1); // a, b, c
            $table->string('description');
            $table->string('currency_code', 3)->default('IDR');
            $table->decimal('amount_valas', 18, 4)->default(0);
            $table->decimal('kurs', 18, 4)->default(1);
            $table->decimal('dpp', 18, 4)->default(0);
            $table->decimal('ppn_rate', 6, 4)->default(0);
            $table->decimal('ppn_amount', 18, 4)->default(0);
            $table->decimal('total_line', 18, 4)->default(0);
            $table->boolean('kena_ppn_jasa')->default(false); // flag per baris
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'blok']);
        });

        // 3. Pembayaran per Invoice (§6.3, Q18, Q22)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('importir_id')->constrained('importirs')->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 18, 4)->default(0);
            $table->string('method', 30)->default('transfer'); // transfer, kas, deposit_kontainer
            $table->string('reference')->nullable(); // no kas bon / no transfer
            $table->boolean('is_unallocated')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['importir_id', 'is_unallocated']);
        });

        // 4. Alokasi Pembayaran per Baris (§6.3, Q22 — 2-langkah manual)
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->decimal('amount', 18, 4)->default(0);
            $table->string('blok', 1); // a, b, c
            $table->text('reason')->nullable(); // alasan penyimpangan urutan
            $table->foreignId('allocated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Saklar Blokir per Importir (§6.3, Q18)
        Schema::table('importirs', function (Blueprint $table) {
            $table->boolean('blokir_sebelum_filing')->default(false);
            $table->boolean('blokir_sebelum_gate_out')->default(false);
            $table->integer('payment_terms_days')->default(14); // untuk aging (§7c)
        });

        // 6. Batas Approve per User Finance (§6.5, Q33, ADR 0007)
        Schema::create('finance_approval_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('limit_amount', 18, 4)->default(50000000); // Rp 50jt default
            $table->boolean('unlimited')->default(false); // untuk Direktur
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('importirs', function (Blueprint $table) {
            $table->dropColumn(['blokir_sebelum_filing', 'blokir_sebelum_gate_out', 'payment_terms_days']);
        });
        Schema::dropIfExists('finance_approval_limits');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};