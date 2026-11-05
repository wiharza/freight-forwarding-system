<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCompanyContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetCompanyContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // TEMUAN AUDIT 8 ITEM (2026-11-05, item 1): SetCompanyContext HARUS
        // berjalan SEBELUM route model binding (SubstituteBindings). Kalau
        // hanya di-append di akhir grup web, binding Job/Trip/dokumen jalan
        // duluan dan global scope BelongsToCompany membaca context yang
        // belum di-set — lalu jatuh ke tenant ber-ID terendah, sehingga Job
        // tenant lain bisa dibuka lewat URL. Priority list Laravel
        // mengurutkan middleware lintas grup: StartSession + Authenticate
        // tetap jalan duluan (ada di daftar default sebelum SubstituteBindings),
        // jadi $request->user() sudah tersedia saat context di-set.
        $middleware->prependToPriorityList(SubstituteBindings::class, SetCompanyContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
