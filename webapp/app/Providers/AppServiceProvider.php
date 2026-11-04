<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CompanyContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // §8.1 — uang SELALU desimal (NUMERIC/decimal), tidak pernah float.
        Blueprint::macro('money', function (string $column, int $precision = 18, int $scale = 4) {
            return $this->decimal($column, $precision, $scale);
        });

        // §2.5 — matriks peran × fitur dari config/features.php.
        foreach (config('features', []) as $feature => $roles) {
            Gate::define($feature, fn (User $user) => $user->hasAnyRole((array) $roles));
        }
    }
}
