<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS untuk semua URL yang di-generate Laravel ketika
        // request datang via HTTPS (di belakang reverse proxy nginx/docker).
        if (request()?->isSecure() || request()?->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
