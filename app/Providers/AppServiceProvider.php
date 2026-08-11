<?php

namespace App\Providers;

use App\Models\ChatbotRule;
use App\Models\KamarReservasi;
use App\Observers\ChatbotRuleObserver;
use App\Observers\KamarReservasiObserver;
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
        ChatbotRule::observe(ChatbotRuleObserver::class);
        KamarReservasi::observe(KamarReservasiObserver::class);

        // Force HTTPS untuk semua URL yang di-generate Laravel ketika
        // request datang via HTTPS (di belakang reverse proxy nginx/docker).
        if (request()?->isSecure() || request()?->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
