<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Transaction;
use App\Models\ImeiRegistration;
use App\Services\CeirkuService;
use App\Services\Payment\DokuService;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

/**
 * Command: Artisan inspire
 * Menampilkan kata-kata motivasi bawaan Laravel.
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Command: Artisan romei:api-check
 * Mengecek status koneksi ke API CEIRKU dan DOKU secara realtime melalui terminal.
 */
Artisan::command('romei:api-check', function (CeirkuService $ceirku, DokuService $doku) {
    $this->info('Checking API Connections...');

    // Cek CEIRKU
    try {
        $ceirkuStatus = $ceirku->checkDevice('123456789012345'); // Dummy IMEI untuk test ping
        $this->info('[OK] API CEIRKU: Connected');
    } catch (\Exception $e) {
        $this->error('[FAIL] API CEIRKU: Disconnected (' . $e->getMessage() . ')');
    }

    // Cek DOKU
    try {
        $dokuStatus = $doku->verifyPayment('PING-TEST');
        $this->info('[OK] API DOKU: Connected');
    } catch (\Exception $e) {
        $this->error('[FAIL] API DOKU: Disconnected (' . $e->getMessage() . ')');
    }
})->purpose('Check connection status for CEIRKU and DOKU APIs');


/*
|--------------------------------------------------------------------------
| Task Scheduling (Laravel Scheduler)
|--------------------------------------------------------------------------
*/

/**
 * 1. Sinkronisasi & Pembersihan Transaksi Expired (Setiap Jam)
 * Mengubah status transaksi pending yang berumur lebih dari 1 jam menjadi 'expired'.
 */
Schedule::call(function () {
    Log::info('Scheduler: Memulai pengecekan transaksi expired...');

    $expiredTransactions = Transaction::where('status', 'pending')
        ->where('created_at', '<=', now()->subHour())
        ->get();

    foreach ($expiredTransactions as $tx) {
        $tx->update(['status' => 'expired']);
        
        // Jika transaksi terkait dengan pendaftaran IMEI, update status registrasinya
        if ($tx->payable_type === ImeiRegistration::class) {
            $tx->payable()->update(['status' => 'expired']);
        }
    }

    Log::info('Scheduler: Berhasil membersihkan ' . $expiredTransactions->count() . ' transaksi expired.');
})->hourly();

/**
 * 2. Sinkronisasi IMEI Expired (Setiap Hari)
 * Mengubah status registrasi IMEI yang masa aktifnya (3 bulan) sudah habis.
 */
Schedule::call(function () {
    Log::info('Scheduler: Memulai pengecekan masa aktif IMEI...');

    $expiredImeis = ImeiRegistration::where('status', 'approved')
        ->where('expired_at', '<=', now())
        ->get();

    foreach ($expiredImeis as $imei) {
        $imei->update(['status' => 'expired']);
    }

    Log::info('Scheduler: Berhasil mengubah ' . $expiredImeis->count() . ' data IMEI menjadi expired.');
})->dailyAt('00:01');

/**
 * 3. Pembersihan Log Lama (Setiap Minggu)
 * Menghapus log sistem atau log API yang sudah berusia lebih dari 30 hari agar penyimpanan server di aaPanel hemat.
 */
Schedule::call(function () {
    Log::info('Scheduler: Membersihkan log lama dari database...');
    
    $days = 30;
    
    // Menghapus data dari tabel api_logs & audit_logs jika sudah melewati 30 hari
    DB::table('api_logs')->where('created_at', '<=', now()->subDays($days))->delete();
    DB::table('audit_logs')->where('created_at', '<=', now()->subDays($days))->delete();
    
    Log::info('Scheduler: Pembersihan log lama selesai.');
})->weekly();