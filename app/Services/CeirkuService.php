<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CeirkuService
{
    /**
     * Dapatkan Base URL API Gateway CEIRKU secara dinamis dari database Setting
     */
    public static function getBaseUrl(): string
    {
        $rawUrl = Setting::get('ceirku_api_url', Setting::get('ceirku_url', 'https://ceirku.net/api/v1'));
        $rawUrl = rtrim(trim($rawUrl), '/');

        // Jika user hanya menginput 'https://ceirku.net', otomatis tambahkan '/api/v1'
        if (!str_contains($rawUrl, '/api/')) {
            $rawUrl .= '/api/v1';
        }

        return $rawUrl;
    }

    /**
     * Endpoint Order / Registrasi IMEI
     */
    public static function getOrderUrl(): string
    {
        $baseUrl = self::getBaseUrl();
        if (str_ends_with($baseUrl, '/order')) {
            return $baseUrl;
        }
        return $baseUrl . '/order';
    }

    /**
     * Endpoint Tambah Roamer
     */
    public static function getRoamerAddUrl(): string
    {
        $baseUrl = self::getBaseUrl();
        if (str_ends_with($baseUrl, '/order')) {
            $baseUrl = preg_replace('/\/order$/', '', $baseUrl);
        }
        return $baseUrl . '/roamer/add';
    }

    /**
     * Endpoint Pengecekan Saldo Reseller CEIRKU
     */
    public static function getBalanceUrl(): string
    {
        $baseUrl = self::getBaseUrl();
        if (str_ends_with($baseUrl, '/order')) {
            $baseUrl = preg_replace('/\/order$/', '', $baseUrl);
        }
        return $baseUrl . '/balance';
    }

    /**
     * Daftarkan nomor IMEI ke API Server Pusat CEIRKU.
     */
    public function registerImei(string $imei, int $serviceId)
    {
        try {
            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY');
            $apiUrl = self::getOrderUrl();

            $response = Http::withHeaders([
                'X-Api-Key'    => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->timeout(20)->post($apiUrl, [
                'service_id' => $serviceId,
                'imeis'      => [ (string) $imei ]
            ]);

            if ($response->successful() && $response->json('status') === true) {
                Log::info('CEIRKU SERVICE SUCCESS - IMEI Berhasil Terdaftar via Gateway Sync.', [
                    'imei'     => $imei,
                    'order_id' => $response->json('data.order_id')
                ]);
                return $response->json('data');
            }

            Log::error('CEIRKU SERVICE API ERROR - Server Pusat Menolak Payload.', [
                'imei'     => $imei,
                'url'      => $apiUrl,
                'response' => $response->json()
            ]);
            
            return false;

        } catch (\Exception $e) {
            Log::error('CEIRKU SERVICE CRITICAL EXCEPTION - Gagal Sinkronisasi: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifikasi status perangkat hardware ke CEIRKU
     */
    public function checkDevice(string $imei): object
    {
        try {
            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY');
            $apiUrl = self::getOrderUrl();

            $response = Http::withHeaders([
                'X-Api-Key'    => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->timeout(12)->post($apiUrl, [
                'service_id' => 20, // Sandbox check
                'imeis'      => [ (string) $imei ]
            ]);

            $isBlocked = false;
            $isSimLocked = false;

            if ($response->successful() && $response->json('status') === true) {
                $data = $response->json('data.result', []);
                $status = key($data) ?? 'UNKNOWN';
                if ($status === 'BLOCKED') {
                    $isBlocked = true;
                }
            }

            return (object) [
                'isBlocked'   => $isBlocked,
                'isSimLocked' => $isSimLocked,
                'status'      => $response->json('status', false)
            ];
        } catch (\Exception $e) {
            Log::warning('CEIRKU CheckDevice Exception: ' . $e->getMessage());
            return (object) [
                'isBlocked'   => false,
                'isSimLocked' => false,
                'status'      => false
            ];
        }
    }

    /**
     * Kirim pendaftaran antrean IMEI Job ke CEIRKU
     */
    public function submitRegistration(array $payload): array
    {
        try {
            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY');
            $apiUrl = self::getOrderUrl();

            $serviceId = (Setting::get('ceirku_mode', 'sandbox') === 'live') ? 30 : 21;

            $imeis = array_filter([$payload['imei_1'] ?? null, $payload['imei_2'] ?? null]);

            $response = Http::withHeaders([
                'X-Api-Key'    => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->timeout(20)->post($apiUrl, [
                'service_id' => $serviceId,
                'imeis'      => array_values($imeis)
            ]);

            if ($response->successful() && $response->json('status') === true) {
                return [
                    'status'  => 'SUCCESS',
                    'data'    => $response->json('data'),
                    'message' => 'Registrasi IMEI berhasil disubmit ke pusat.'
                ];
            }

            return [
                'status'  => 'FAILED',
                'message' => $response->json('message') ?? 'Ditolak oleh gateway server CEIRKU.'
            ];
        } catch (\Exception $e) {
            Log::error('CEIRKU submitRegistration Exception: ' . $e->getMessage());
            return [
                'status'  => 'FAILED',
                'message' => 'Gangguan koneksi gateway: ' . $e->getMessage()
            ];
        }
    }
}