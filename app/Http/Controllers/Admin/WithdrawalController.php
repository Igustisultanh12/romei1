<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\Wallet;
use App\Notifications\WithdrawalStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WithdrawalController extends Controller {
    
    /**
     * Menyetujui penarikan dana dan memicu DOKU Disbursement API
     */
    public function approve(Request $request, $id) {
        $withdrawal = Withdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return back()->withErrors(['message' => 'Transaksi ini sudah diproses sebelumnya.']);
        }

        try {
            // Skenario integrasi DOKU Disbursement API
            $response = Http::withHeaders([
                'Client-Id' => config('services.doku.client_id'),
                'Signature' => 'SHA256=' . config('services.doku.secret_key'), // Sesuai dokumentasi signature DOKU
            ])->post(config('services.doku.disbursement_url') . '/v1/transfer', [
                'amount' => (int) $withdrawal->amount,
                'destination_bank' => $withdrawal->bank_name,
                'destination_account' => $withdrawal->account_number,
                'reference_number' => 'WD-' . $withdrawal->id
            ]);

            if ($response->successful() && $response->json('status') === 'SUCCESS') {
                $withdrawal->update([
                    'status' => 'paid',
                    'disbursement_ref' => $response->json('disbursement_id')
                ]);

                // Kirim notifikasi sukses ke user
                $withdrawal->user->notify(new WithdrawalStatusNotification($withdrawal));

                return back()->with('success', 'Penarikan dana berhasil dicairkan ke rekening tujuan.');
            }

            throw new \Exception($response->json('message') ?? 'Gagal mendapatkan respon sukses dari DOKU.');

        } catch (\Exception $e) {
            Log::critical("DOKU Disbursement Failure untuk WD ID {$withdrawal->id}: " . $e->getMessage());
            return back()->withErrors(['message' => 'Gagal memproses transfer otomatis via DOKU. Silakan coba lagi.']);
        }
    }

    /**
     * Menolak pengajuan penarikan dana dan mengembalikan saldo ke wallet user
     */
    public function reject(Request $request, $id) {
        $request->validate(['rejection_reason' => 'required|string|max:255']);
        $withdrawal = Withdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return back()->withErrors(['message' => 'Transaksi ini tidak dapat dibatalkan.']);
        }

        DB::transaction(function () use ($withdrawal, $request) {
            // 1. Kunci wallet milik target user
            $wallet = Wallet::where('user_id', $withdrawal->user_id)->lockForUpdate()->firstOrFail();

            // 2. Kembalikan saldo yang sempat dipotong di awal (Deposit Kembali)
            $wallet->deposit(
                amount: $withdrawal->amount,
                type: 'refund',
                description: "Pembalikan dana atas penolakan penarikan #WD-{$withdrawal->id}. Alasan: {$request->rejection_reason}",
                referenceId: $withdrawal->id
            );

            // 3. Update status data penarikan menjadi rejected
            $withdrawal->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason
            ]);
        });

        // Kirim notifikasi penolakan ke user
        $withdrawal->user->notify(new WithdrawalStatusNotification($withdrawal));

        return back()->with('success', 'Pengajuan penarikan ditolak, dana telah dikembalikan ke wallet user.');
    }
}