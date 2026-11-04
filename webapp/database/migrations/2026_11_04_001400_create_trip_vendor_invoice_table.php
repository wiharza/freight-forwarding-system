<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BUGFIX 2026-11-04: tabel pivot trip_vendor_invoice TIDAK PERNAH dibuat
     * — migrasi 2026_11_03_000800 membuat trips/trip_containers/surat_jalans/
     * vendor_routes/vendor_invoices, tetapi bukan pivot ini, padahal
     * Trip::vendorInvoices() dan VendorInvoice::trips() keduanya
     * BelongsToMany lewat tabel ini. Akibatnya TripController::show (yang
     * eager-load 'vendorInvoices') SELALU 500 "no such table" — perbaikan
     * nama relasi saja (singular -> jamak) tidak cukup. Tabel ini melengkapi
     * skema sesuai relasi yang sudah ditulis di model.
     */
    public function up(): void
    {
        Schema::create('trip_vendor_invoice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_invoice_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['trip_id', 'vendor_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_vendor_invoice');
    }
};
