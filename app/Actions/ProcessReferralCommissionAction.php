<?
namespace App\Actions;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessReferralCommissionAction {
    /**
     * Eksekusi pemberian komisi referral.
     * * @param Transaction $transaction Transaksi utama pendaftaran IMEI yang baru saja sukses
     */
    public function execute(Transaction $transaction): void {
        // Ambil data user yang melakukan pembayaran
        $buyer = $transaction->user;

        // Jika user tidak didaftarkan melalui kode referral siapapun, lewati proses
        if (!$buyer->referred_by) {
            return;
        }

        // Tentukan nominal komisi (Misal: Rp10.000 flat per pendaftaran sukses)
        $commissionAmount = 10000.00;

        DB::transaction(function () use ($buyer, $commissionAmount, $transaction) {
            // 1. Kunci data wallet milik pengajak (Upline) untuk menghindari race condition
            $uplineWallet = Wallet::where('user_id', $buyer->referred_by)
                ->lockForUpdate()
                ->firstOrCreate(['user_id' => $buyer->referred_by]);

            // 2. Tambahkan saldo komisi ke wallet upline
            $uplineWallet->deposit(
                amount: $commissionAmount,
                type: 'referral_commission',
                description: "Bonus komisi referral dari pendaftaran IMEI oleh {$buyer->name} (#{$transaction->invoice_number})",
                referenceId: $transaction->id
            );

            Log::info("Komisi Referral sukses dikirim ke User ID: {$buyer->referred_by} sebesar Rp10.000 melalui pendaftaran oleh User ID: {$buyer->id}");
        });
    }
}