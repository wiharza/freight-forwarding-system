<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penutupan Job (§2.3, Q11): status Closed adalah keadaan di atas status
 * turunan milestone — bukan kolom status manual (ADR 0002). Tiga kolom ini
 * menyimpan siapa/kapan/mengapa Job ditutup; riwayat penutupan & pembukaan
 * ulang tetap tercatat di audit trail (append-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('cached_status');
            $table->foreignId('closed_by_user_id')->nullable()->after('closed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('close_reason', 500)->nullable()->after('closed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn(['closed_at', 'closed_by_user_id', 'close_reason']);
        });
    }
};
