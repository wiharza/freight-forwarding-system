<?php

use App\Http\Controllers\Admin\LegalTariffController;
use App\Http\Controllers\Admin\LineTariffController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Finance\InternalExchangeRateController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\FtaGateController;
use App\Http\Controllers\JobCloseController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\PibCalculatorController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SuratJalanController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\VendorRouteController;
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
        Route::get('importirs', [MasterDataController::class, 'importirs'])->name('importirs');
        Route::post('importirs', [MasterDataController::class, 'storeImportir'])->name('importirs.store');

        Route::get('shipping-lines', [MasterDataController::class, 'shippingLines'])->name('shipping-lines');
        Route::post('shipping-lines', [MasterDataController::class, 'storeShippingLine'])->name('shipping-lines.store');

        // Master kapal (DQ-18)
        Route::get('vessels', [MasterDataController::class, 'vessels'])->name('vessels');
        Route::post('vessels', [MasterDataController::class, 'storeVessel'])->name('vessels.store');

        Route::get('vendors', [MasterDataController::class, 'vendors'])->name('vendors');
        Route::post('vendors', [MasterDataController::class, 'storeVendor'])->name('vendors.store');

        Route::get('locations', [MasterDataController::class, 'locations'])->name('locations');
        Route::post('locations', [MasterDataController::class, 'storeLocation'])->name('locations.store');

        Route::get('container-types', [MasterDataController::class, 'containerTypes'])->name('container-types');
        Route::post('container-types', [MasterDataController::class, 'storeContainerType'])->name('container-types.store');

        Route::get('currencies', [MasterDataController::class, 'currencies'])->name('currencies');
        Route::patch('currencies/{currency}', [MasterDataController::class, 'updateCurrency'])->name('currencies.update');

        // Tarif Line ber-versi & Konfigurasi Free Time (§4.2, §4.3)
        Route::get('line-tariffs', [LineTariffController::class, 'index'])->name('line-tariffs');
        Route::post('line-tariffs', [LineTariffController::class, 'store'])->name('line-tariffs.store');
        Route::post('line-tariffs/{tariff}/approve', [LineTariffController::class, 'approve'])->name('line-tariffs.approve');
        Route::patch('shipping-lines/{line}/config', [LineTariffController::class, 'updateConfig'])->name('shipping-lines.config');

        // Master Legal Berversi (§3.6, ADR 0008, Tiket 05)
        Route::get('mfn-tariffs', [LegalTariffController::class, 'mfn'])->name('mfn-tariffs');
        Route::post('mfn-tariffs', [LegalTariffController::class, 'storeMfn'])->name('mfn-tariffs.store');
        Route::post('mfn-tariffs/{tariff}/approve', [LegalTariffController::class, 'approveMfn'])->name('mfn-tariffs.approve');

        Route::get('fta-tariffs', [LegalTariffController::class, 'fta'])->name('fta-tariffs');
        Route::post('fta-tariffs', [LegalTariffController::class, 'storeFta'])->name('fta-tariffs.store');

        Route::get('pph22-rules', [LegalTariffController::class, 'pph22'])->name('pph22-rules');
        Route::get('luxury-goods', [LegalTariffController::class, 'luxury'])->name('luxury-goods');

        // Kurs Pajak Kemenkeu (§3.5)
        Route::get('tax-rates', [PibCalculatorController::class, 'taxRatesIndex'])->name('tax-rates');
        Route::post('tax-rates', [PibCalculatorController::class, 'storeTaxRate'])->name('tax-rates.store');

        // Vendor Routes (§5, Tiket 10)
        Route::get('vendor-routes', [VendorRouteController::class, 'index'])->name('vendor-routes');
        Route::post('vendor-routes', [VendorRouteController::class, 'store'])->name('vendor-routes.store');
        Route::delete('vendor-routes/{route}', [VendorRouteController::class, 'destroy'])->name('vendor-routes.destroy');
    });

// Modul Operasional Job (§2.1, Tiket 06)
Route::middleware(['auth'])->group(function () {
    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/create', [JobController::class, 'create'])->name('jobs.create');
    Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');

    // Kalkulator PIB v2 (§3.2, Tiket 09)
    Route::get('jobs/{job}/calculator', [PibCalculatorController::class, 'show'])->name('jobs.calculator');
    Route::post('jobs/{job}/calculator/draft', [PibCalculatorController::class, 'storeDraft'])->name('jobs.calculator.draft');
    Route::post('jobs/{job}/calculator/{declaration}/approve', [PibCalculatorController::class, 'approve'])->name('jobs.calculator.approve');

    // Gerbang FTA (Tiket 10, Q19, §3.8)
    Route::post('jobs/{job}/fta-forms', [FtaGateController::class, 'storeForm'])->name('jobs.fta-forms.store');
    Route::get('jobs/{job}/fta-forms/{form}/download', [FtaGateController::class, 'downloadForm'])->name('jobs.fta-forms.download');
    Route::post('jobs/{job}/calculator/{declaration}/fta-override', [FtaGateController::class, 'storeOverride'])->name('jobs.calculator.fta-override');
    Route::post('jobs/{job}/calculator/{declaration}/fta-decision', [FtaGateController::class, 'storeDecision'])->name('jobs.calculator.fta-decision');
    Route::patch('jobs/{job}/fta-decisions/{decision}/refund-reference', [FtaGateController::class, 'updateRefundReference'])->name('jobs.fta-decisions.refund-reference');

    Route::post('jobs/{job}/documents/{document}/upload', [JobController::class, 'uploadDocument'])->name('jobs.documents.upload');
    Route::post('jobs/{job}/documents/{document}/verify', [JobController::class, 'verifyDocument'])->name('jobs.documents.verify');
    Route::post('jobs/{job}/documents/{document}/unverify', [JobController::class, 'unverifyDocument'])->name('jobs.documents.unverify');
    Route::get('jobs/{job}/documents/{document}/download', [JobController::class, 'downloadDocument'])->name('jobs.documents.download');
    Route::get('jobs/{job}/documents/{document}/preview', [JobController::class, 'previewDocument'])->name('jobs.documents.preview');
    Route::patch('jobs/{job}/problem-flag', [JobController::class, 'updateProblemFlag'])->name('jobs.problem-flag');
    Route::post('jobs/{job}/assign-field', [JobController::class, 'assignField'])->name('jobs.assign-field');

    // Penutupan & pembukaan kembali Job (§2.3, Q11)
    Route::post('jobs/{job}/close', [JobCloseController::class, 'close'])->name('jobs.close');
    Route::post('jobs/{job}/reopen', [JobCloseController::class, 'reopen'])->name('jobs.reopen');

    // Milestone Dua Cabang (§2.2, §2.7, Tiket 07)
    Route::post('jobs/{job}/milestones', [MilestoneController::class, 'store'])->name('jobs.milestones.store');
    Route::get('jobs/{job}/milestones/{milestone}/proof', [MilestoneController::class, 'downloadProof'])->name('jobs.milestones.proof');
    Route::post('milestones/{milestone}/verify', [MilestoneController::class, 'verify'])->name('milestones.verify');
    Route::post('milestones/{milestone}/supersede', [MilestoneController::class, 'supersede'])->name('milestones.supersede');
    Route::get('pwa/field', [MilestoneController::class, 'pwaIndex'])->name('pwa.field');

    // Trucking & Surat Jalan (§5, §6.1, Tiket 10, 13)
    Route::middleware(['auth', 'can:job_assignment'])->group(function () {
        Route::get('trips', [TripController::class, 'index'])->name('trips.index');
        Route::get('trips/create', [TripController::class, 'create'])->name('trips.create');
        Route::post('trips', [TripController::class, 'store'])->name('trips.store');
        Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');
        Route::patch('trips/{trip}/status', [TripController::class, 'updateStatus'])->name('trips.status');
        Route::post('trips/{trip}/print-sj', [TripController::class, 'printSuratJalan'])->name('trips.print-sj');

        Route::get('trips/{trip}/surat-jalan/create', [SuratJalanController::class, 'create'])->name('surat-jalan.create');
        Route::post('trips/{trip}/surat-jalan', [SuratJalanController::class, 'store'])->name('surat-jalan.store');
        Route::get('surat-jalan/{sj}', [SuratJalanController::class, 'show'])->name('surat-jalan.show');
        Route::post('surat-jalan/{sj}/sign', [SuratJalanController::class, 'sign'])->name('surat-jalan.sign');
        Route::post('surat-jalan/{sj}/upload-pod', [SuratJalanController::class, 'uploadPod'])->name('surat-jalan.upload-pod');
        Route::get('surat-jalan/{sj}/pod/download', [SuratJalanController::class, 'downloadPod'])->name('surat-jalan.pod.download');
    });

    Route::middleware(['auth', 'can:master_data'])->group(function () {
        Route::get('vendor-routes', [VendorRouteController::class, 'index'])->name('vendor-routes');
        Route::post('vendor-routes', [VendorRouteController::class, 'store'])->name('vendor-routes.store');
        Route::delete('vendor-routes/{route}', [VendorRouteController::class, 'destroy'])->name('vendor-routes.destroy');
    });
});

// §2.5 — modul Finance (Kurs Internal, Invoice, dsb.)
Route::middleware(['auth', 'can:kurs_internal'])
    ->prefix('finance')
    ->name('finance.')
    ->group(function () {
        Route::get('rates', [InternalExchangeRateController::class, 'index'])->name('rates.index');
        Route::post('rates', [InternalExchangeRateController::class, 'store'])->name('rates.store');

        // Invoice (§6.4–6.5, Tiket 14)
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('jobs/{job}/invoice/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('jobs/{job}/invoice', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
        Route::post('payments/{payment}/allocate', [InvoiceController::class, 'allocate'])->name('payments.allocate');
        Route::get('exposure', [InvoiceController::class, 'exposure'])->name('exposure');
    });

// Laporan (§7, Tiket 16)
Route::middleware(['auth'])->group(function () {
    Route::get('reports/jobs', [ReportController::class, 'jobList'])->name('reports.jobs');
    Route::get('reports/exposure', [ReportController::class, 'exposurePerImportir'])->name('reports.exposure');
    Route::get('reports/aging', [ReportController::class, 'aging'])->name('reports.aging');
    Route::get('reports/demurrage', [ReportController::class, 'demurragePerLine'])->name('reports.demurrage');
    Route::get('reports/deposit', [ReportController::class, 'depositFlow'])->name('reports.deposit');
    Route::get('reports/estimate-vs-actual', [ReportController::class, 'estimateVsActual'])->name('reports.estimate-vs-actual');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
