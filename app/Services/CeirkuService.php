<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CeirkuService
{
    /**
     * Daftarkan nomor IMEI ke API Server Pusat CEIRKU.
     */
    public function registerImei(string $imei, int $serviceId)
    {
        try {
            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY');
            $apiUrl = 'https://ceirku.net/api/v1/order';

            $response = Http::withHeaders([
                'X-Api-Key'    => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->timeout(20)->post($apiUrl, [
                'service_id' => $serviceId,
                'imeis'      => [ (string) $imei ]
            ]);

            if ($response->successful() && $response->json('status') === true) {
                Log::info('CEIRKU SERVICE SUCCESS - IMEI Berhasil Terdaftar via Webhook Sync.', [
                    'imei' => $imei,
                    'order_id' => $response->json('data.order_id')
                ]);
                return $response->json('data');
            }

            Log::error('CEIRKU SERVICE API ERROR - Server Pusat Menolak Payload.', [
                'imei' => $imei,
                'response' => $response->json()
            ]);
            
            return false;

        } catch (\Exception $e) {
            Log::error('CEIRKU SERVICE CRITICAL EXCEPTION - Gagal Sinkronisasi: ' . $e->getMessage());
            return false;
        }
    }
}