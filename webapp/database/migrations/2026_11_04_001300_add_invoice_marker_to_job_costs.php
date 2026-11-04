<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HARDENING 2026-11-04 (invoice rawan salah tagih): penanda biaya yang
     * sudah ditagih. Sebelumnya tidak ada penanda apa pun — satu JobCost
     * bisa masuk ke dua invoice berbeda tanpa ada yang mencegah (preview
     * dan submit pun memakai filter berbeda, sehingga keduanya bisa
     * menyimpang diam-diam).
     */
    public function up(): void
    {
        Schema::table('job_costs', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('invoiced_at')->nullable();
            $table->index(['job_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::table('job_costs', function (Blueprint $table) {
            $table->dropIndex(['job_id', 'invoice_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['invoice_id', 'invoiced_at']);
        });
    }
};
