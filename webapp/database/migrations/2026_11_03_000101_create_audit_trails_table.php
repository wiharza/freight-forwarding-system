<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §1.5 / ADR 0006 — append-only: tidak pernah diedit atau dihapus.
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('object_type');
            $table->unsignedBigInteger('object_id');
            $table->json('changes')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['object_type', 'object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
