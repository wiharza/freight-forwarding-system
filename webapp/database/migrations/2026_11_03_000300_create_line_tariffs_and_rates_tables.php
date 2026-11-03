<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Perluas tabel shipping_lines dengan konfigurasi free time (§4.2, Q9)
        Schema::table('shipping_lines', function (Blueprint $table) {
            $table->unsignedSmallInteger('free_days_demurrage')->default(7);
            $table->unsignedSmallInteger('free_days_detention')->default(7);
            $table->string('start_offset_demurrage', 4)->default('H+1'); // H+0, H+1
            $table->string('start_offset_detention', 4)->default('H+1'); // H+0, H+1
            $table->string('calendar_type_demurrage', 10)->default('calendar'); // calendar, working
            $table->string('calendar_type_detention', 10)->default('calendar'); // calendar, working
            $table->string('mode', 10)->default('terpisah'); // terpisah, gabungan
        });

        // 2. Tabel Tarif Line ber-versi (§4.3, Q9)
        Schema::create('line_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_line_id')->constrained()->cascadeOnDelete();
            $table->string('charge_type', 12); // demurrage, detention
            $table->string('container_size', 5); // 20, 40, 45
            $table->unsignedSmallInteger('day_from'); // 1, 4, 8...
            $table->unsignedSmallInteger('day_to')->nullable(); // 3, 7, null (artinya ke atas)
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('rate_per_day', 18, 4); // NUMERIC(18, 4)
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 10)->default('active'); // active, draft, archived
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'shipping_line_id', 'charge_type', 'container_size', 'effective_from'], 'line_tariffs_lookup_idx');
        });

        // 3. Tabel Kurs Internal Harian (§6.6, Q14, Q24, ADR 0003)
        Schema::create('internal_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('rate_date');
            $table->string('currency_code', 3);
            $table->decimal('rate_to_idr', 18, 4);
            $table->time('cutoff_time')->default('09:00:00');
            $table->boolean('is_locked')->default(false); // Terkunci jika sudah dipakai invoice final
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'rate_date', 'currency_code'], 'unique_daily_rate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_exchange_rates');
        Schema::dropIfExists('line_tariffs');

        Schema::table('shipping_lines', function (Blueprint $table) {
            $table->dropColumn([
                'free_days_demurrage',
                'free_days_detention',
                'start_offset_demurrage',
                'start_offset_detention',
                'calendar_type_demurrage',
                'calendar_type_detention',
                'mode',
            ]);
        });
    }
};
