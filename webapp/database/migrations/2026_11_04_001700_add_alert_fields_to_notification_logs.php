<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tautkan tiap baris log ke pemicu alarm yang menyebabkannya
        // (§2.6, Q8, Tiket 15): penerima sebagai user (bukan hanya alamat
        // email mentah), Job, pemicu, jenis biaya, dan tanggal free time.
        // Tanpa ini, badge per pengguna tidak bisa dihitung dari log.
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('company_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('job_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->string('trigger', 20)->nullable()->after('related_type');
            $table->string('charge_type', 20)->nullable()->after('trigger');
            $table->date('free_time_date')->nullable()->after('charge_type');

            $table->index(['user_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'channel']);
            $table->dropColumn(['user_id', 'job_id', 'trigger', 'charge_type', 'free_time_date']);
        });
    }
};
