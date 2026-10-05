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

    // Cek Payment Gateway
    try {
        $activeProvider = \App\Services\Payment\PaymentGatewayManager::getActiveProvider();
        $this->info("[OK] Payment Gateway ({$activeProvider}): Configured & Ready");
    } catch (\Exception $e) {
        $this->error('[FAIL] Payment Gateway: (' . $e->getMessage() . ')');
    }
})->purpose('Check connection status for CEIRKU and Payment Gateway APIs');

/**
 * Command: Artisan ceirku:sync-orders
 * Melakukan polling status order IMEI ke CEIRKU sesuai pedoman (action: getimeiorder).
 */
Artisan::command('ceirku:sync-orders', function (CeirkuService $ceirku) {
    $this->info('Memulai polling status order CEIRKU...');

    $processingTransactions = Transaction::whereNotNull('metadata')
        ->where('created_at', '>=', now()->subDays(7))
        ->get()
        ->filter(function ($tx) {
            $metadata = $tx->metadata;
            if (!is_array($metadata)) {
                return false;
            }
            $status = strtoupper((string) ($metadata['ceirku_status'] ?? ''));
            $orderId = $metadata['ceirku_order_id'] ?? null;
            return $status === 'PROCESSING' && !empty($orderId) && $orderId !== 'N/A';
        });

    $this->info("Ditemukan {$processingTransactions->count()} transaksi dalam status PROCESSING.");

    foreach ($processingTransactions as $tx) {
        $metadata = $tx->metadata ?? [];
        $orderId = $metadata['ceirku_order_id'] ?? null;
        if (!$orderId || $orderId === 'N/A') {
            continue;
        }

        $res = CeirkuService::getImeiOrder($orderId);
        if (!$res['success']) {
            $this->warn("Gagal memeriksa order #{$orderId}: " . ($res['message'] ?? 'Error'));
            continue;
        }

        $status = $res['status']; // 'SUCCESS', 'FAILED', atau 'PROCESSING'
        $code   = $res['code'] ?? '';

        if ($status === 'SUCCESS') {
            $metadata['ceirku_status'] = 'SUCCESS';
            $metadata['ceirku_result'] = $code ?: 'Selesai / Terdaftar resmi';
            $metadata['synced_at']     = now()->toDateTimeString();
            $tx->update(['metadata' => $metadata]);

            if ($tx->payable_type === ImeiRegistration::class && $tx->payable) {
                $tx->payable()->update(['status' => 'approved', 'expired_at' => now()->addDays(90)]);
            }

            $this->info("Order #{$orderId} berhasil: {$code}");
        } elseif ($status === 'FAILED') {
            $metadata['ceirku_status'] = 'FAILED';
            $metadata['ceirku_result'] = $code ?: 'Ditolak pusat';
            $metadata['synced_at']     = now()->toDateTimeString();
            $tx->update(['metadata' => $metadata]);

            // Refund otomatis ke saldo wallet jika ditolak oleh pusat
            $user = $tx->user;
            if ($user && $user->wallet && $tx->amount > 0) {
                $user->wallet->increment('balance', $tx->amount);
                Transaction::create([
                    'invoice_number' => 'REF-' . strtoupper(uniqid()),
                    'amount'         => $tx->amount,
                    'status'         => 'SUCCESS',
                    'user_id'        => $user->id,
                    'payable_type'   => \App\Models\Wallet::class,
                    'payable_id'     => $user->wallet->id,
                    'description'    => "Refund otomatis via penolakan order pusat #{$orderId}. Alasan: {$code}",
                    'metadata'       => [
                        'refund_from_invoice' => $tx->invoice_number,
                        'ceirku_order_id'     => $orderId,
                    ],
                ]);
            }

            if ($tx->payable_type === ImeiRegistration::class && $tx->payable) {
                $tx->payable()->update(['status' => 'rejected']);
            }

            $this->warn("Order #{$orderId} ditolak: {$code}");
        }
    }

    $this->info('Polling status order CEIRKU selesai.');
})->purpose('Poll CEIRKU order statuses using getimeiorder action');


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

/**
 * 4. Polling Status Order CEIRKU Secara Berkala (Setiap 5 Menit)
 * Menjalankan getimeiorder untuk memperbarui status pesanan pending/processing.
 */
Schedule::command('ceirku:sync-orders')->everyFiveMinutes();