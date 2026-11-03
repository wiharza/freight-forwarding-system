<?php

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

// §2.5 — manajemen user hanya untuk Admin (Admin bukan superuser, tapi
// user_management adalah fiturnya).
Route::middleware(['auth', 'can:user_management'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', UserController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
    });

// §2.5 — master data hanya untuk Admin
Route::middleware(['auth', 'can:master_data'])
    ->prefix('admin/master')
    ->name('admin.master.')
    ->group(function () {
        Route::get('importirs', [\App\Http\Controllers\Admin\MasterDataController::class, 'importirs'])->name('importirs');
        Route::post('importirs', [\App\Http\Controllers\Admin\MasterDataController::class, 'storeImportir'])->name('importirs.store');

        Route::get('shipping-lines', [\App\Http\Controllers\Admin\MasterDataController::class, 'shippingLines'])->name('shipping-lines');
        Route::post('shipping-lines', [\App\Http\Controllers\Admin\MasterDataController::class, 'storeShippingLine'])->name('shipping-lines.store');

        // Master kapal (DQ-18)
        Route::get('vessels', [\App\Http\Controllers\Admin\MasterDataController::class, 'vessels'])->name('vessels');
        Route::post('vessels', [\App\Http\Controllers\Admin\MasterDataController::class, 'storeVessel'])->name('vessels.store');

        Route::get('vendors', [\App\Http\Controllers\Admin\MasterDataController::class, 'vendors'])->name('vendors');
        Route::post('vendors', [\App\Http\Controllers\Admin\MasterDataController::class, 'storeVendor'])->name('vendors.store');

        Route::get('locations', [\App\Http\Controllers\Admin\MasterDataController::class, 'locations'])->name('locations');
        Route::post('locations', [\App\Http\Controllers\Admin\MasterDataController::class, 'storeLocation'])->name('locations.store');

        Route::get('container-types', [\App\Http\Controllers\Admin\MasterDataController::class, 'containerTypes'])->name('container-types');
        Route::post('container-types', [\App\Http\Controllers\Admin\MasterDataController::class, 'storeContainerType'])->name('container-types.store');

        Route::get('currencies', [\App\Http\Controllers\Admin\MasterDataController::class, 'currencies'])->name('currencies');
        Route::patch('currencies/{currency}', [\App\Http\Controllers\Admin\MasterDataController::class, 'updateCurrency'])->name('currencies.update');

        // Tarif Line ber-versi & Konfigurasi Free Time (§4.2, §4.3)
        Route::get('line-tariffs', [\App\Http\Controllers\Admin\LineTariffController::class, 'index'])->name('line-tariffs');
        Route::post('line-tariffs', [\App\Http\Controllers\Admin\LineTariffController::class, 'store'])->name('line-tariffs.store');
        Route::post('line-tariffs/{tariff}/approve', [\App\Http\Controllers\Admin\LineTariffController::class, 'approve'])->name('line-tariffs.approve');
        Route::patch('shipping-lines/{line}/config', [\App\Http\Controllers\Admin\LineTariffController::class, 'updateConfig'])->name('shipping-lines.config');

        // Master Legal Berversi (§3.6, ADR 0008, Tiket 05)
        Route::get('mfn-tariffs', [\App\Http\Controllers\Admin\LegalTariffController::class, 'mfn'])->name('mfn-tariffs');
        Route::post('mfn-tariffs', [\App\Http\Controllers\Admin\LegalTariffController::class, 'storeMfn'])->name('mfn-tariffs.store');
        Route::post('mfn-tariffs/{tariff}/approve', [\App\Http\Controllers\Admin\LegalTariffController::class, 'approveMfn'])->name('mfn-tariffs.approve');

        Route::get('fta-tariffs', [\App\Http\Controllers\Admin\LegalTariffController::class, 'fta'])->name('fta-tariffs');
        Route::post('fta-tariffs', [\App\Http\Controllers\Admin\LegalTariffController::class, 'storeFta'])->name('fta-tariffs.store');

        Route::get('pph22-rules', [\App\Http\Controllers\Admin\LegalTariffController::class, 'pph22'])->name('pph22-rules');
        Route::get('luxury-goods', [\App\Http\Controllers\Admin\LegalTariffController::class, 'luxury'])->name('luxury-goods');

        // Kurs Pajak Kemenkeu (§3.5)
        Route::get('tax-rates', [\App\Http\Controllers\PibCalculatorController::class, 'taxRatesIndex'])->name('tax-rates');
        Route::post('tax-rates', [\App\Http\Controllers\PibCalculatorController::class, 'storeTaxRate'])->name('tax-rates.store');

        // Vendor Routes (§5, Tiket 10)
        Route::get('vendor-routes', [\App\Http\Controllers\VendorRouteController::class, 'index'])->name('vendor-routes');
        Route::post('vendor-routes', [\App\Http\Controllers\VendorRouteController::class, 'store'])->name('vendor-routes.store');
        Route::delete('vendor-routes/{route}', [\App\Http\Controllers\VendorRouteController::class, 'destroy'])->name('vendor-routes.destroy');
    });

// Modul Operasional Job (§2.1, Tiket 06)
Route::middleware(['auth'])->group(function () {
    Route::get('jobs', [\App\Http\Controllers\JobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/create', [\App\Http\Controllers\JobController::class, 'create'])->name('jobs.create');
    Route::post('jobs', [\App\Http\Controllers\JobController::class, 'store'])->name('jobs.store');
    Route::get('jobs/{job}', [\App\Http\Controllers\JobController::class, 'show'])->name('jobs.show');

    // Kalkulator PIB v2 (§3.2, Tiket 09)
    Route::get('jobs/{job}/calculator', [\App\Http\Controllers\PibCalculatorController::class, 'show'])->name('jobs.calculator');
    Route::post('jobs/{job}/calculator/draft', [\App\Http\Controllers\PibCalculatorController::class, 'storeDraft'])->name('jobs.calculator.draft');
    Route::post('jobs/{job}/calculator/{declaration}/approve', [\App\Http\Controllers\PibCalculatorController::class, 'approve'])->name('jobs.calculator.approve');

    Route::post('jobs/{job}/documents/{document}/upload', [\App\Http\Controllers\JobController::class, 'uploadDocument'])->name('jobs.documents.upload');
    Route::post('jobs/{job}/documents/{document}/verify', [\App\Http\Controllers\JobController::class, 'verifyDocument'])->name('jobs.documents.verify');
    Route::patch('jobs/{job}/problem-flag', [\App\Http\Controllers\JobController::class, 'updateProblemFlag'])->name('jobs.problem-flag');
    Route::post('jobs/{job}/assign-field', [\App\Http\Controllers\JobController::class, 'assignField'])->name('jobs.assign-field');

    // Milestone Dua Cabang (§2.2, §2.7, Tiket 07)
    Route::post('jobs/{job}/milestones', [\App\Http\Controllers\MilestoneController::class, 'store'])->name('jobs.milestones.store');
    Route::post('milestones/{milestone}/verify', [\App\Http\Controllers\MilestoneController::class, 'verify'])->name('milestones.verify');
    Route::post('milestones/{milestone}/supersede', [\App\Http\Controllers\MilestoneController::class, 'supersede'])->name('milestones.supersede');
    Route::get('pwa/field', [\App\Http\Controllers\MilestoneController::class, 'pwaIndex'])->name('pwa.field');

    // Trucking & Surat Jalan (§5, §6.1, Tiket 10, 13)
    Route::middleware(['auth', 'can:job_assignment'])->group(function () {
        Route::get('trips', [\App\Http\Controllers\TripController::class, 'index'])->name('trips.index');
        Route::get('trips/create', [\App\Http\Controllers\TripController::class, 'create'])->name('trips.create');
        Route::post('trips', [\App\Http\Controllers\TripController::class, 'store'])->name('trips.store');
        Route::get('trips/{trip}', [\App\Http\Controllers\TripController::class, 'show'])->name('trips.show');
        Route::patch('trips/{trip}/status', [\App\Http\Controllers\TripController::class, 'updateStatus'])->name('trips.status');
        Route::post('trips/{trip}/print-sj', [\App\Http\Controllers\TripController::class, 'printSuratJalan'])->name('trips.print-sj');

        Route::get('trips/{trip}/surat-jalan/create', [\App\Http\Controllers\SuratJalanController::class, 'create'])->name('surat-jalan.create');
        Route::post('trips/{trip}/surat-jalan', [\App\Http\Controllers\SuratJalanController::class, 'store'])->name('surat-jalan.store');
        Route::get('surat-jalan/{sj}', [\App\Http\Controllers\SuratJalanController::class, 'show'])->name('surat-jalan.show');
        Route::post('surat-jalan/{sj}/sign', [\App\Http\Controllers\SuratJalanController::class, 'sign'])->name('surat-jalan.sign');
        Route::post('surat-jalan/{sj}/upload-pod', [\App\Http\Controllers\SuratJalanController::class, 'uploadPod'])->name('surat-jalan.upload-pod');
    });

    Route::middleware(['auth', 'can:master_data'])->group(function () {
        Route::get('vendor-routes', [\App\Http\Controllers\VendorRouteController::class, 'index'])->name('vendor-routes');
        Route::post('vendor-routes', [\App\Http\Controllers\VendorRouteController::class, 'store'])->name('vendor-routes.store');
        Route::delete('vendor-routes/{route}', [\App\Http\Controllers\VendorRouteController::class, 'destroy'])->name('vendor-routes.destroy');
    });
});

// §2.5 — modul Finance (Kurs Internal, Invoice, dsb.)
Route::middleware(['auth', 'can:kurs_internal'])
    ->prefix('finance')
    ->name('finance.')
    ->group(function () {
        Route::get('rates', [\App\Http\Controllers\Finance\InternalExchangeRateController::class, 'index'])->name('rates.index');
        Route::post('rates', [\App\Http\Controllers\Finance\InternalExchangeRateController::class, 'store'])->name('rates.store');

        // Invoice (§6.4–6.5, Tiket 14)
        Route::get('invoices', [\App\Http\Controllers\Finance\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('jobs/{job}/invoice/create', [\App\Http\Controllers\Finance\InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('jobs/{job}/invoice', [\App\Http\Controllers\Finance\InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [\App\Http\Controllers\Finance\InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/approve', [\App\Http\Controllers\Finance\InvoiceController::class, 'approve'])->name('invoices.approve');
        Route::post('invoices/{invoice}/payments', [\App\Http\Controllers\Finance\InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
        Route::post('payments/{payment}/allocate', [\App\Http\Controllers\Finance\InvoiceController::class, 'allocate'])->name('payments.allocate');
        Route::get('exposure', [\App\Http\Controllers\Finance\InvoiceController::class, 'exposure'])->name('exposure');
    });

// Laporan (§7, Tiket 16)
Route::middleware(['auth'])->group(function () {
    Route::get('reports/jobs', [\App\Http\Controllers\ReportController::class, 'jobList'])->name('reports.jobs');
    Route::get('reports/exposure', [\App\Http\Controllers\ReportController::class, 'exposurePerImportir'])->name('reports.exposure');
    Route::get('reports/aging', [\App\Http\Controllers\ReportController::class, 'aging'])->name('reports.aging');
    Route::get('reports/demurrage', [\App\Http\Controllers\ReportController::class, 'demurragePerLine'])->name('reports.demurrage');
    Route::get('reports/deposit', [\App\Http\Controllers\ReportController::class, 'depositFlow'])->name('reports.deposit');
    Route::get('reports/estimate-vs-actual', [\App\Http\Controllers\ReportController::class, 'estimateVsActual'])->name('reports.estimate-vs-actual');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
