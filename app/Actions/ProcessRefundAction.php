<?php
namespace App\Actions;

use App\Models\ImeiRegistration;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessRefundAction {
    public function execute(ImeiRegistration $registration, string $reason): void {
        DB::transaction(function () use ($registration, $reason) {
            // 1. Cari transaksi pembayaran utama yang terkait dengan registrasi ini
            $transaction = Transaction::where('payable_id', $registration->id)
                ->where('payable_type', ImeiRegistration::class)
                ->where('status', 'paid')
                ->first();

            if (!$transaction) {
                Log::warning("Refund dilewati: Transaksi sukses tidak ditemukan untuk Registrasi ID: {$registration->id}");
                return;
            }

            // 2. Kunci baris data wallet milik user menggunakan Pessimistic Locking
            $wallet = Wallet::where('user_id', $registration->user_id)->lockForUpdate()->firstOrCreate([
                'user_id' => $registration->user_id
            ]);

            // 3. Masukkan dana kembali ke wallet user
            $wallet->deposit(
                amount: $transaction->amount,
                type: 'refund',
                description: "Refund otomatis pendaftaran IMEI #{$registration->registration_number}. Alasan: {$reason}",
                referenceId: $registration->registration_number
            );

            // 4. Update status transaksi utama menjadi Refunded
            $transaction->update(['status' => 'refunded']);
            
            Log::info("Refund sukses diproses untuk User ID: {$registration->user_id} sebesar Rp " . number_format($transaction->amount, 0, ',', '.'));
        });
    }
}