<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ApiMonitorController as AdminApiMonitorController;
use App\Http\Controllers\Webhook\DokuWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes - ROMEI Platform Live Engine
|--------------------------------------------------------------------------
|
| Semua rute di dalam file ini otomatis mendapatkan prefix '/api' secara 
| bawaan oleh framework Laravel 11 mas Sultan.
|
*/

// GROUP MONITORING ADMIN (Diakses oleh Axios Vue Frontend)
Route::prefix('admin/monitoring')->group(function () {
    
    // 1. Endpoint murni JSON untuk menangani AJAX request tembak invoice DOKU dari Vue
    Route::post('/test-payment', [AdminApiMonitorController::class, 'testPayment']);
    
    // 2. Endpoint Polling Realtime: Melayani cek status transaksi secara berkala dari Vue mas Sultan
    Route::get('/check-status/{invoice_id}', [AdminApiMonitorController::class, 'checkStatus']);
    
});

/*
|--------------------------------------------------------------------------
| ASYNCHRONOUS IPN WEBHOOK RECEIVER (DOKU PRODUCTION COMPLIANT)
|--------------------------------------------------------------------------
| Jalur rute: /api/v1/callback/doku
| Catatan: Rute ini WAJIB dikecualikan dari VerifyCsrfToken di bootstrap/app.php
| agar server DOKU dari luar bisa menembak sukses tanpa ganjalan error 419.
*/
Route::post('/webhook/doku/qris', [DokuWebhookController::class, 'handleQrisCallback'])->name('api.webhook.doku.qris');