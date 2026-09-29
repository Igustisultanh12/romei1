<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Withdrawal;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class CreateWithdrawalAction {
    public function execute(User $user, array $data): Withdrawal {
        return DB::transaction(function () use ($user, $data) {
            // 1. Kunci data wallet dengan Pessimistic Locking
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            // 2. Validasi kecukupan saldo secara mutlak
            if ($wallet->balance < $data['amount']) {
                throw new \Exception('Saldo Anda tidak mencukupi untuk melakukan penarikan ini.');
            }

            // 3. Buat rekaman data withdrawal
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'bank_name' => $data['bank_name'],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
                'amount' => $data['amount'],
                'status' => 'pending',
            ]);

            // 4. Potong saldo wallet user dan catat mutasi transaksinya
            $wallet->withdraw(
                amount: $data['amount'],
                type: 'withdrawal',
                description: "Pengajuan penarikan dana ke rekening {$data['bank_name']} ({$data['account_number']})",
                referenceId: $withdrawal->id
            );

            return $withdrawal;
        });
    }
}