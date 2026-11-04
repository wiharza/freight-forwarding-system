<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gerbang FTA (Tiket 10, Q19, §3.8): Form FTA sebagai Dokumen Asal
     * dengan metadata yang terikat ke baris HS PIB, override Customs
     * Specialist atas blokir keras, dan decision log MFN.
     *
     * - fta_forms: satu Form FTA per (job, nomor form); terikat ke baris
     *   HS PIB lewat customs_item_id (nullable — form boleh dicatat
     *   sebelum draf PIB ada, lalu dicocokkan lewat prefix 6 digit HS).
     * - fta_gate_overrides: override atas blokir keras, satu per
     *   (declaration, item), wajib alasan + lampiran (ADR 0006:
     *   overrider ≠ pembuat draf — ditegakkan di controller).
     * - fta_decisions: decision log append-only — pilihan "file MFN now /
     *   delay", peringatan 8-digit (T5) yang diloloskan, dan referensi
     *   refund eksternal MFN→FTA (manual, di luar v1).
     */
    public function up(): void
    {
        Schema::create('fta_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customs_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scheme_code', 20);
            $table->string('form_number', 100);
            $table->string('hs_code', 12); // digit murni (FormatRules::normalizeHsCode)
            $table->string('origin_country', 2); // kode negara ISO 2 huruf
            $table->string('port_of_loading', 100);
            $table->date('issued_at');
            $table->date('valid_until');
            $table->string('file_path')->nullable(); // disk 'local' (privat)
            $table->string('file_name')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_id', 'form_number']);
            $table->index(['job_id', 'hs_code']);
        });

        Schema::create('fta_gate_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customs_declaration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customs_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fta_form_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->string('attachment_path'); // disk 'local' (privat), wajib
            $table->string('attachment_name')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customs_declaration_id', 'customs_item_id']);
        });

        Schema::create('fta_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customs_declaration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customs_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fta_form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision_type', 20); // mfn_now, delay, warning_8digit
            $table->text('reason')->nullable();
            $table->text('risk_note')->nullable();
            $table->string('refund_reference', 100)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['job_id', 'decision_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fta_decisions');
        Schema::dropIfExists('fta_gate_overrides');
        Schema::dropIfExists('fta_forms');
    }
};
