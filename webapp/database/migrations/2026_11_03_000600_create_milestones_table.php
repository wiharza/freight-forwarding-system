<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Milestone Dua Cabang (§2.2, §2.7, Q12, Q15, ADR 0002)
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_id')->nullable()->constrained('containers')->cascadeOnDelete();
            $table->string('branch', 10); // pabean, logistik
            $table->string('milestone_type', 30); // discharge, do_release, pib_filed, sppb, gate_out_full, delivered, gate_in_empty
            $table->dateTime('occurred_at');
            $table->dateTime('recorded_at');
            $table->boolean('is_backdate')->default(false);
            $table->string('backdate_reason')->nullable();
            $table->string('proof_number')->nullable(); // no DO / no SPPB
            $table->string('proof_file_path')->nullable(); // foto kontainer / scan DO / scan SPPB
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            // Koreksi pengganti (§2.7): tidak pernah dihapus, milestone baru merujuk ID yang digantikan
            $table->foreignId('supersedes_id')->nullable()->constrained('milestones')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['job_id', 'milestone_type', 'is_active']);
            $table->index(['container_id', 'milestone_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
