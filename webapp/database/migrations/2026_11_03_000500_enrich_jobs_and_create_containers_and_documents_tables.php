<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Lengkapi tabel jobs (§2.1, §2.3, Q10, Q11)
        Schema::table('jobs', function (Blueprint $table) {
            $table->foreignId('importir_id')->nullable()->constrained('importirs')->cascadeOnDelete();
            $table->foreignId('shipping_line_id')->nullable()->constrained('shipping_lines')->nullOnDelete();
            $table->string('bl_number')->nullable();
            $table->date('bl_date')->nullable();
            $table->foreignId('pol_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('pod_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('vessel_name')->nullable();
            $table->string('voyage_number')->nullable();
            $table->date('eta')->nullable();
            $table->date('ata')->nullable();
            $table->boolean('is_fcl')->default(true); // v1 HANYA FCL (§1.2, Q2, Q10)
            $table->boolean('claim_fta')->default(false);
            $table->string('fta_scheme', 20)->nullable(); // e.g. ACFTA, ATIGA
            $table->string('problem_flag', 30)->nullable(); // Hold, Dispute, Dokumen Bermasalah
            $table->string('cached_status', 50)->default('Draft');
            $table->string('referensi_excel_lama')->nullable(); // migrasi cutover (§1.7)
            $table->date('tanggal_cutover')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // 2. Entitas First-Class Kontainer (§2.1, Q10)
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('container_number', 20); // e.g. MSKU1234567
            $table->string('size', 5); // 20, 40, 45
            $table->string('type', 30); // Dry, Reefer, High Cube, Open Top
            $table->string('seal_number', 50)->nullable();
            $table->decimal('gross_weight_kg', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'job_id', 'container_number']);
        });

        // 3. Checklist Dokumen Berfase (§2.4, Q21)
        Schema::create('job_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 20); // pra_filing, pasca_filing, pasca_delivery
            $table->string('document_type', 50); // invoice, packing_list, bl, form_fta, do, sppb, gate_out_proof, gate_in_proof, pod
            $table->string('status', 20)->default('belum_ada'); // belum_ada, diupload, diverifikasi, tidak_berlaku
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->json('metadata')->nullable(); // no_form, tgl_terbit, hs_code_form, dsb
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'document_type']);
        });

        // 4. Override Checklist Dokumen per Importir (§2.4, Q21)
        Schema::create('importir_checklist_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('importir_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->boolean('is_required')->default(true);
            $table->string('reason')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique(['importir_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importir_checklist_overrides');
        Schema::dropIfExists('job_documents');
        Schema::dropIfExists('containers');

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign(['importir_id']);
            $table->dropForeign(['shipping_line_id']);
            $table->dropForeign(['pol_id']);
            $table->dropForeign(['pod_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn([
                'importir_id',
                'shipping_line_id',
                'bl_number',
                'bl_date',
                'pol_id',
                'pod_id',
                'vessel_name',
                'voyage_number',
                'eta',
                'ata',
                'is_fcl',
                'claim_fta',
                'fta_scheme',
                'problem_flag',
                'cached_status',
                'referensi_excel_lama',
                'tanggal_cutover',
                'created_by_user_id',
            ]);
        });
    }
};
