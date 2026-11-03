<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan company aktif dari user yang login untuk setiap request web —
 * dipakai global scope BelongsToCompany (ADR 0001).
 */
class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            app(CompanyContext::class)->set($user->company_id);
        }

        return $next($request);
    }
}
