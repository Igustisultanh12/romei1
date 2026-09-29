<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 1. Definisikan Middleware Bawaan Grup Web & Proteksi Status Akun Suspended
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\BlockSuspendedUsers::class, // Mengunci proteksi cek suspend di level web rute
        ]);

        // 2. PERBAIKAN FATAL TUNNELING: Daftarkan Cloudflare/Reverse Proxy sebagai Trusted Proxy agar aset dibaca via HTTPS murni
        $middleware->trustProxies(at: '*');

        // 3. SINKRONISASI CORES: Penyatuan Pengecualian Token CSRF untuk Seluruh Webhook Integrasi ROMEI
        $middleware->validateCsrfTokens(except: [
            'api/webhook/doku/qris',            // Callback QRIS DOKU Live Payment Gateway
            'webhook/roamer-status',          // Webhook Sinkronisasi Log API CEIRKU Pusat
            'api/v1/webhook/whatsapp-v2',     // Webhook Callback Incoming Message WA Gateway Port 7777
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();