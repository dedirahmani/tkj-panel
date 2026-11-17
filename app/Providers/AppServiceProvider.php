<?php

namespace App\Providers;

use App\Support\AuthFailureLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Event;
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
        // Panel berjalan di 127.0.0.1 di balik Nginx/Cloudflare Tunnel. Tanpa proxy
        // tepercaya, IP setiap pengunjung terlihat sebagai 127.0.0.1.
        $proxies = trim((string) config('tkjpanel.trusted_proxies'));
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Sumber log untuk jail fail2ban "tkjpanel".
        Event::listen(Failed::class, function (Failed $event): void {
            $request = request();
            AuthFailureLog::write($request, $event->credentials['email'] ?? $request->input('email'));
        });
    }
}
