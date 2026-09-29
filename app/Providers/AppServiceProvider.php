<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // Wajib diimport agar fungsi forceScheme aktif

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\PaymentGatewayInterface::class, 
            \App\Services\Payment\DokuService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // KUNCI UTAMA: Paksa skema URL ke HTTPS jika terdeteksi request lewat proxy/tunnel Cloudflare
        if (config('app.env') === 'production' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            URL::forceScheme('https');
        }

        // DYNAMIC MAIL GATEWAY CONFIGURATION LOADER
        // Menginjeksi konfigurasi SMTP dari database Setting secara real-time
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $settings = \App\Models\Setting::where('group', 'mail')->orWhere('key', 'like', 'mail_%')->pluck('value', 'key')->toArray();
                if (!empty($settings['mail_host'])) {
                    config([
                        'mail.default'                 => $settings['mail_mailer'] ?? config('mail.default'),
                        'mail.mailers.smtp.host'       => $settings['mail_host'] ?? config('mail.mailers.smtp.host'),
                        'mail.mailers.smtp.port'       => (int) ($settings['mail_port'] ?? config('mail.mailers.smtp.port')),
                        'mail.mailers.smtp.encryption' => ($settings['mail_encryption'] ?? 'tls') === 'none' ? null : ($settings['mail_encryption'] ?? 'tls'),
                        'mail.mailers.smtp.username'   => $settings['mail_username'] ?? config('mail.mailers.smtp.username'),
                        'mail.mailers.smtp.password'   => $settings['mail_password'] ?? config('mail.mailers.smtp.password'),
                        'mail.from.address'            => $settings['mail_from_address'] ?? config('mail.from.address'),
                        'mail.from.name'               => $settings['mail_from_name'] ?? config('mail.from.name'),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika migrasi database belum dieksekusi
        }
    }
}