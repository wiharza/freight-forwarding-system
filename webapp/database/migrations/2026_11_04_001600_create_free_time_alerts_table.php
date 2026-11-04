<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jejak pemicu alarm Free Time yang SUDAH berbunyi (§2.6, Q8, Tiket 15).
        // Satu baris = satu pemicu (kontainer + jenis + pemicu + tanggal
        // free time). Unique key inilah yang membuat pengiriman idempotent:
        // pemicu yang sama untuk tanggal free time yang sama tidak pernah
        // berbunyi dua kali, walau command dijalankan berulang kali.
        Schema::create('free_time_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->string('charge_type', 20); // demurrage | detention
            $table->string('trigger', 20); // h_minus_2 | h_0 | h_plus_1
            $table->date('free_time_date');
            $table->string('status', 20)->default('sent'); // sent | failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'container_id', 'charge_type', 'trigger', 'free_time_date'],
                'fta_unique_trigger'
            );
            $table->index(['company_id', 'sent_at']);
        });

        // Status baca badge per pengguna (§2.6, Q8, Tiket 15). Tabel terpisah
        // karena notification_logs bersifat append-only (tidak boleh di-update
        // — lihat NotificationLog::booted), jadi tanda baca tidak boleh
        // disimpan sebagai kolom di baris log.
        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_log_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['user_id', 'notification_log_id'], 'notif_read_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
        Schema::dropIfExists('free_time_alerts');
    }
};
