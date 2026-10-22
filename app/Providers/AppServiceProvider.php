<?php

namespace App\Providers;

use App\Application\Store;
use App\Infrastructure\PdoStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PdoStore::class, fn (): PdoStore => new PdoStore(DB::connection()->getPdo(), fn (): string => now()->format('Y-m-d H:i:s')));
        $this->app->bind(Store::class, fn (): PdoStore => $this->app->make(PdoStore::class));
    }

    public function boot(): void
    {
        require_once app_path('Support/presentation.php');
        RateLimiter::for('nyota-login', fn (Request $request): array => [Limit::perMinute(30)->by($request->ip()), Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())]);
    }
}
