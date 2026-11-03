<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Importir (§1.7, Q7): dedup berbasis NPWP per company
        Schema::create('importirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('npwp');
            $table->string('nib')->nullable();
            $table->string('status_api')->default('API-U'); // API-U, API-P, NON-API
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('pic_name')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'npwp']);
        });

        // 2. Shipping Line (§4.2, Q9)
        Schema::create('shipping_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // 3. Vendor Trucking (§5.1, Q13): vendor_type eksternal / internal disiapkan awal
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('vendor_type')->default('eksternal'); // eksternal, internal
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        // 4. Pelabuhan, Depot, Gudang (§1.7)
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type'); // port, depot, warehouse
            $table->string('city')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code', 'type']);
        });

        // 5. Jenis Kontainer (20'/40'/45' + tipe: Dry/Reefer/High Cube/dst - §2.1, Q10)
        Schema::create('container_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('size'); // 20, 40, 45
            $table->string('type'); // Dry, Reefer, Open Top, High Cube
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'size', 'type']);
        });

        // 6. Master Mata Uang (§6.6, Q24): flag aktif + tanggal berlaku (v1 hanya USD aktif)
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 3); // USD, IDR, CNY, dsb.
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('container_types');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('shipping_lines');
        Schema::dropIfExists('importirs');
    }
};
