<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ApiMonitorController as AdminApiMonitorController;
use App\Http\Controllers\Webhook\DokuWebhookController;
use App\Http\Controllers\Webhook\QrquWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes - ROMEI Platform Live Engine
|--------------------------------------------------------------------------
|
| Semua rute di dalam file ini otomatis mendapatkan prefix '/api' secara 
| bawaan oleh framework Laravel 11.
|
*/

// GROUP MONITORING ADMIN (Diakses oleh Axios Vue Frontend)
Route::prefix('admin/monitoring')->group(function () {
    
    // 1. Endpoint murni JSON untuk menangani AJAX request tembak invoice dari Vue
    Route::post('/test-payment', [AdminApiMonitorController::class, 'testPayment']);
    
    // 2. Endpoint Polling Realtime: Melayani cek status transaksi secara berkala dari Vue
    Route::get('/check-status/{invoice_id}', [AdminApiMonitorController::class, 'checkStatus']);
    
});

/*
|--------------------------------------------------------------------------
| ASYNCHRONOUS IPN WEBHOOK RECEIVERS (DOKU & QRQU GATEWAYS)
|--------------------------------------------------------------------------
| Jalur rute: /api/webhook/doku/qris & /api/webhook/qrqu
| Catatan: Rute ini WAJIB dikecualikan dari VerifyCsrfToken di bootstrap/app.php
| agar server payment gateway luar bisa menembak sukses tanpa ganjalan error 419.
*/
Route::post('/webhook/doku/qris', [DokuWebhookController::class, 'handleQrisCallback'])->name('api.webhook.doku.qris');
Route::post('/webhook/qrqu', [QrquWebhookController::class, 'handleCallback'])->name('api.webhook.qrqu');
Route::post('/v1/webhook/qrqu', [QrquWebhookController::class, 'handleCallback'])->name('api.webhook.qrqu.v1');