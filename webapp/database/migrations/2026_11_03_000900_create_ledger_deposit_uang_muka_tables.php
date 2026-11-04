<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ledger Real-Cost per Job (§6.1, Q16)
        Schema::create('job_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('cost_type', 30); // ocean_freight, thc, demurrage, detention, do_fee, admin_terminal, karantina, trucking, container_deposit_forfeit
            $table->string('payer_flag', 20)->default('ppjk'); // ppjk, importir_langsung
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('amount_valas', 18, 4)->default(0);
            $table->decimal('kurs_internal_rate', 18, 4)->default(1);
            $table->decimal('amount_idr', 18, 4)->default(0);
            $table->string('status', 20)->default('estimasi'); // estimasi, aktual
            $table->string('bukti_path')->nullable();
            $table->string('bukti_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'job_id', 'cost_type']);
        });

        // 2. Deposit Kontainer per Kontainer (§6.2, Q16, ADR 0005)
        Schema::create('container_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_usd', 18, 4)->default(0);
            $table->decimal('amount_idr', 18, 4)->default(0);
            $table->date('paid_date')->nullable();
            $table->string('status', 30)->default('ditahan'); // ditahan, dikembalikan_penuh, dikembalikan_sebagian, hangus_sebagian, hangus_penuh
            $table->decimal('forfeit_amount_usd', 18, 4)->default(0);
            $table->decimal('forfeit_amount_idr', 18, 4)->default(0);
            $table->date('returned_date')->nullable();
            $table->string('debit_note_number')->nullable();
            $table->string('debit_note_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Uang Muka per Job (§6.3.1, Q18)
        Schema::create('uang_mukas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4)->default(0);
            $table->date('received_date')->nullable();
            $table->string('method', 30)->default('transfer'); // transfer, kas, billing_code_langsung
            $table->string('reference')->nullable();
            $table->decimal('settled_amount', 18, 4)->default(0);
            $table->boolean('is_settled')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Titipan Dana per Importir (§6.3, Q18, Q22)
        Schema::create('titipan_danas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('importir_id')->constrained()->cascadeOnDelete();
            $table->date('received_date');
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('allocated_amount', 18, 4)->default(0);
            $table->string('method', 30)->default('transfer');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Alokasi Titipan Dana per Baris Biaya (§6.3, Q22)
        Schema::create('titipan_dana_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('titipan_dana_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_cost_id')->nullable()->constrained('job_costs')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable();
            $table->decimal('amount', 18, 4)->default(0);
            $table->string('reason')->nullable(); // alasan penyimpangan urutan
            $table->foreignId('allocated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titipan_dana_allocations');
        Schema::dropIfExists('titipan_danas');
        Schema::dropIfExists('uang_mukas');
        Schema::dropIfExists('container_deposits');
        Schema::dropIfExists('job_costs');
    }
};
