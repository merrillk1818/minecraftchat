<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(5, 10)->by($request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });

        RateLimiter::for('chat', function (Request $request) {
            return $request->user()
                ? Limit::perMinutes(10, 30)->by('u:'.$request->user()->id)
                : Limit::perMinutes(10, 10)->by('g:'.$request->ip());
        });

        RateLimiter::for('notes', function (Request $request) {
            return Limit::perMinutes(10, 20)->by('u:'.$request->user()->id);
        });
    }
}
