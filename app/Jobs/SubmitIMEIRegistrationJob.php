<?
namespace App\Jobs;

use App\Models\ImeiRegistration;
use App\Services\CeirkuService;
use App\Notifications\RegistrationApprovedNotification;
use App\Notifications\RegistrationFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitIMEIRegistrationJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Batasan percobaan jika API timeout/error
    public $tries = 3;
    public $backoff = 60; // tunggu 1 menit sebelum coba lagi

    public function __construct(protected ImeiRegistration $registration) {}

    public function handle(CeirkuService $ceirkuService) {
        try {
            // Hit API ke CEIRKU
            $response = $ceirkuService->submitRegistration([
                'imei_1' => $this->registration->imei1,
                'imei_2' => $this->registration->imei2,
                'duration' => 90 // 3 Bulan
            ]);

            if ($response['status'] === 'SUCCESS') {
                $this->registration->update([
                    'status' => 'approved',
                    'expired_at' => now()->addDays(90)
                ]);

                // Kirim notifikasi sukses ke user via Laravel Notifications
                $this->registration->user->notify(new RegistrationApprovedNotification($this->registration));
            } else {
                $this->failRegistration($response['message'] ?? 'Ditolak oleh CEIRKU.');
            }

        } catch (\Exception $e) {
            Log::error('CEIRKU Submission Error: ' . $e->getMessage());
            
            // Jika sudah mencapai batas trial maksimal, jalankan sistem Refund otomatis
            if ($this->attempts() >= $this->tries) {
                $this->failRegistration('System timeout / API error.');
            }
            
            throw $e; // lempar kembali agar queue me-retry sesuai aturan $backoff
        }
    }

    protected function failRegistration(string $reason) {
        $this->registration->update(['status' => 'rejected']);
        
        // Trigger Action Refund Otomatis ke Saldo Wallet User
        app(\App\Actions\ProcessRefundAction::class)->execute($this->registration, $reason);

        // Notifikasi kegagalan & info dana di-refund ke wallet
        $this->registration->user->notify(new RegistrationFailedNotification($this->registration, $reason));
    }
}