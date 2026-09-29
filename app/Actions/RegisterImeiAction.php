<?
namespace App\Actions;

use App\DTOs\ImeiRegistrationDTO;
use App\Models\ImeiRegistration;
use App\Services\CeirkuService;
use Illuminate\Support\Facades\DB;

class RegisterImeiAction {
    public function __construct(protected CeirkuService $ceirku) {}

    public function execute(ImeiRegistrationDTO $data) {
        return DB::transaction(function () use ($data) {
            // 1. Verifikasi Device ke CEIRKU
            $deviceStatus = $this->ceirku->checkDevice($data->imei1);

            if ($deviceStatus->isBlocked || $deviceStatus->isSimLocked) {
                throw new \Exception("Perangkat tidak memenuhi syarat.");
            }

            // 2. Simpan Registrasi
            return ImeiRegistration::create([
                'user_id' => $data->userId,
                'package_id' => $data->packageId,
                'imei1' => $data->imei1,
                'imei2' => $data->imei2,
                'status' => 'pending',
                'registration_number' => 'ROMEI-' . strtoupper(uniqid()),
            ]);
        });
    }
}