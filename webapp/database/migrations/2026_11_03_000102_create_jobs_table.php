<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Job minimal untuk tiket 02 (scoping Field) — tiket 06 melengkapi
        // importir, B/L, dan relasi kontainer.
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('job_number')->unique();
            $table->timestamps();
        });

        Schema::create('field_job_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_id', 'field_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_job_assignments');
        Schema::dropIfExists('jobs');
    }
};
