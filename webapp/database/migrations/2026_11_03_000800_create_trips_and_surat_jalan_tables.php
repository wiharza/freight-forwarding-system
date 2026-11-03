<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Trip (Pengiriman Trucking per Trip) (§5, §6.1, Q13)
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('trip_number', 50); // Nomor trip unik per job
            $table->date('scheduled_date'); // Tanggal jadwal pengiriman
            $table->dateTime('actual_departure')->nullable(); // Actual keberangkatan
            $table->dateTime('actual_arrival')->nullable(); // Actual tiba
            $table->string('status', 20)->default('assigned'); // assigned, confirmed, in_transit, delivered, cancelled
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('driver_name')->nullable(); // Nama driver (bisa non-user)
            $table->string('driver_phone')->nullable(); // Nomor HP driver
            $table->string('vehicle_number', 20)->nullable(); // Nopol truk
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 2. Tabel Detail Kontainer per Trip (1 Trip bisa 1 kontainer 40' atau 2 kontainer 20')
        Schema::create('trip_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->integer('sequence')->default(1); // Urutan muatan
            $table->timestamps();

            $table->unique(['trip_id', 'container_id']);
        });

        // 3. Tabel Surat Jalan (§5, Q13)
        Schema::create('surat_jalans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('sj_number', 50); // Nomor Surat Jalan
            $table->date('sj_date'); // Tanggal Surat Jalan
            $table->string('driver_name'); // Nama driver
            $table->string('driver_phone'); // No HP driver
            $table->string('vehicle_number', 20); // Nopol
            $table->text('origin_address'); // Alamat asal (pelabuhan/depot)
            $table->text('destination_address'); // Alamat tujuan (gudang importir)
            $table->json('containers_info')->nullable(); // Array info kontainer
            $table->string('status', 20)->default('draft'); // draft, printed, signed, archived
            $table->string('file_path')->nullable(); // Path PDF Surat Jalan
            $table->string('pod_file_path')->nullable(); // Path foto POD (Surat Jalan bertanda tangan)
            $table->dateTime('pod_signed_at')->nullable(); // Waktu POD ditandatangani
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Tabel Rute Harga Trucking (§5.1, Q13)
        Schema::create('vendor_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('container_size', 5); // 20, 40, 45
            $table->decimal('base_price_usd', 18, 4); // Harga dasar USD
            $table->boolean('include_toll')->default(false);
            $table->boolean('include_unloading')->default(false);
            $table->boolean('include_waiting')->default(false);
            $table->decimal('waiting_rate_per_hour_usd', 18, 4)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'container_size', 'effective_from']);
        });

        // 5. Tabel Invoice Vendor Trucking (untuk perbandingan estimasi vs final)
        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('amount_usd', 18, 4);
            $table->decimal('rate_to_idr', 18, 4);
            $table->decimal('amount_idr', 18, 4);
            $table->boolean('include_toll')->default(false);
            $table->boolean('include_unloading')->default(false);
            $table->decimal('waiting_hours', 8, 4)->default(0);
            $table->decimal('waiting_charge_usd', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoices');
        Schema::dropIfExists('vendor_routes');
        Schema::dropIfExists('surat_jalans');
        Schema::dropIfExists('trip_containers');
        Schema::dropIfExists('trips');
    }
};