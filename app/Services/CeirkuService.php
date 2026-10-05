<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CeirkuService
{
    /**
     * Dapatkan Base URL API Gateway CEIRKU secara dinamis dari database Setting
     * Default standar terbaru sesuai pedoman resmi: https://ceirku.org/api
     */
    public static function getBaseUrl(): string
    {
        $rawUrl = Setting::get('ceirku_api_url', Setting::get('ceirku_url', 'https://ceirku.org/api'));
        $rawUrl = rtrim(trim($rawUrl), '/');

        // Jika user hanya menginput https://ceirku.org, otomatis tambahkan /api
        if ($rawUrl === 'https://ceirku.org' || $rawUrl === 'http://ceirku.org') {
            $rawUrl .= '/api';
        }

        return $rawUrl;
    }

    /**
     * Dapatkan Username Reseller CEIRKU dari database Setting
     */
    public static function getUsername(): string
    {
        return trim((string) Setting::get('ceirku_username', 'Igshax12'));
    }

    /**
     * Dapatkan API Access Key CEIRKU dari database Setting
     */
    public static function getApiKey(): string
    {
        return trim((string) Setting::get('ceirku_api_key', ''));
    }

    /**
     * Endpoint Order / Registrasi IMEI (kompatibilitas arsitektur)
     */
    public static function getOrderUrl(): string
    {
        $baseUrl = self::getBaseUrl();
        if (str_contains($baseUrl, 'ceirku.net') || str_contains($baseUrl, '/api/v1')) {
            return str_ends_with($baseUrl, '/order') ? $baseUrl : $baseUrl . '/order';
        }
        return $baseUrl;
    }

    /**
     * Endpoint Tambah Roamer (kompatibilitas arsitektur)
     */
    public static function getRoamerAddUrl(): string
    {
        $baseUrl = self::getBaseUrl();
        if (str_contains($baseUrl, 'ceirku.net') || str_contains($baseUrl, '/api/v1')) {
            $base = preg_replace('/\/order$/', '', $baseUrl);
            return $base . '/roamer/add';
        }
        return $baseUrl;
    }

    /**
     * Endpoint Pengecekan Saldo Reseller CEIRKU (kompatibilitas arsitektur)
     */
    public static function getBalanceUrl(): string
    {
        $baseUrl = self::getBaseUrl();
        if (str_ends_with($baseUrl, '/balance')) {
            return $baseUrl;
        }
        return rtrim($baseUrl, '/') . '/balance';
    }

    /**
     * Eksekusi request API standar CEIRKU (Dhru Fusion Standard)
     *
     * @param string $action 'accountinfo', 'imeiservicelist', 'placeimeiorder', 'getimeiorder'
     * @param array|null $parameters Data parameters (misal: ['ID' => 30, 'IMEI' => '...'])
     * @param string|null $apiUrl
     * @param string|null $apiKey
     * @param string|null $username
     * @return array
     */
    public static function execute(string $action, ?array $parameters = null, ?string $apiUrl = null, ?string $apiKey = null, ?string $username = null): array
    {
        $url  = $apiUrl ? rtrim(trim($apiUrl), '/') : self::getBaseUrl();
        $key  = $apiKey !== null ? trim($apiKey) : self::getApiKey();
        $user = $username !== null ? trim($username) : self::getUsername();

        $payload = [
            'username'     => $user,
            'apiaccesskey' => $key,
            'action'       => $action,
        ];

        if ($parameters !== null) {
            $payload['parameters'] = $parameters;
        }

        try {
            $startTime = microtime(true);
            $response = Http::withoutVerifying()->withHeaders([
                'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 ROMEI-Gateway/2.0',
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($url, $payload);

            $latency = round((microtime(true) - $startTime) * 1000) . 'ms';
            $statusCode = $response->status();
            $body = $response->json();

            // Parsing format respon JSON
            if (is_array($body)) {
                // 1. Respon ERROR standar Dhru Fusion: {"ERROR": [{"MESSAGE": "..."}], "apiversion": "..."}
                if (isset($body['ERROR']) && !empty($body['ERROR'])) {
                    $errObj = is_array($body['ERROR']) ? ($body['ERROR'][0] ?? []) : [];
                    $errMsg = is_array($errObj)
                        ? ($errObj['MESSAGE'] ?? $errObj['message'] ?? json_encode($errObj))
                        : (string) $body['ERROR'];

                    return [
                        'success'     => false,
                        'is_auth_ok'  => !str_contains(strtolower($errMsg), 'auth'),
                        'latency'     => $latency,
                        'status_code' => $statusCode,
                        'message'     => $errMsg,
                        'error'       => $errMsg,
                        'data'        => null,
                        'raw'         => $body,
                    ];
                }

                // 2. Respon SUCCESS standar Dhru Fusion: {"SUCCESS": [...], "apiversion": "..."}
                if (isset($body['SUCCESS'])) {
                    $successData = is_array($body['SUCCESS']) ? ($body['SUCCESS'][0] ?? $body['SUCCESS']) : $body['SUCCESS'];
                    return [
                        'success'     => true,
                        'is_auth_ok'  => true,
                        'latency'     => $latency,
                        'status_code' => $statusCode,
                        'message'     => is_array($successData) ? ($successData['MESSAGE'] ?? $successData['message'] ?? 'OK') : 'OK',
                        'data'        => $successData,
                        'raw'         => $body,
                    ];
                }

                // 3. Respon format legacy CEIRKU: {"status": true, "data": ...}
                if (isset($body['status']) && $body['status'] === true) {
                    return [
                        'success'     => true,
                        'is_auth_ok'  => true,
                        'latency'     => $latency,
                        'status_code' => $statusCode,
                        'message'     => $body['message'] ?? 'OK',
                        'data'        => $body['data'] ?? $body,
                        'raw'         => $body,
                    ];
                }

                if (isset($body['status']) && $body['status'] === false) {
                    return [
                        'success'     => false,
                        'is_auth_ok'  => $statusCode !== 401 && $statusCode !== 403,
                        'latency'     => $latency,
                        'status_code' => $statusCode,
                        'message'     => $body['message'] ?? 'Ditolak oleh gateway server CEIRKU.',
                        'error'       => $body['message'] ?? 'Error',
                        'data'        => $body['data'] ?? null,
                        'raw'         => $body,
                    ];
                }
            }

            if (!$response->successful()) {
                return [
                    'success'     => false,
                    'is_auth_ok'  => false,
                    'latency'     => $latency,
                    'status_code' => $statusCode,
                    'message'     => "Server CEIRKU merespon status HTTP {$statusCode}.",
                    'error'       => $response->body(),
                    'data'        => null,
                    'raw'         => $body,
                ];
            }

            return [
                'success'     => true,
                'is_auth_ok'  => true,
                'latency'     => $latency,
                'status_code' => $statusCode,
                'message'     => 'Berhasil terhubung ke CEIRKU.',
                'data'        => $body,
                'raw'         => $body,
            ];

        } catch (\Throwable $e) {
            Log::error('CEIRKU SERVICE EXCEPTION: ' . $e->getMessage(), [
                'url'    => $url,
                'action' => $action,
            ]);

            return [
                'success'     => false,
                'is_auth_ok'  => false,
                'latency'     => '--',
                'status_code' => 500,
                'message'     => 'Gagal menghubungi server CEIRKU: ' . $e->getMessage(),
                'error'       => $e->getMessage(),
                'data'        => null,
                'raw'         => null,
            ];
        }
    }

    /**
     * 1. Cek Akun & Saldo (action: accountinfo)
     */
    public static function getAccountInfo(?string $apiUrl = null, ?string $apiKey = null, ?string $username = null): array
    {
        $url  = $apiUrl ? rtrim(trim($apiUrl), '/') : self::getBaseUrl();
        $key  = $apiKey !== null ? trim($apiKey) : self::getApiKey();
        $user = $username !== null ? trim($username) : self::getUsername();

        // Jika URL secara spesifik mengarah ke endpoint legacy /balance atau /api/v1
        if (str_ends_with($url, '/balance') || str_contains($url, '/api/v1') || str_contains($url, 'ceirku.net')) {
            $balanceUrl = str_ends_with($url, '/balance') ? $url : $url . '/balance';
            try {
                $startTime = microtime(true);
                $resp = Http::withoutVerifying()->withHeaders([
                    'X-Api-Key'  => $key,
                    'Accept'     => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 ROMEI-Gateway/2.0',
                ])->timeout(8)->post($balanceUrl);

                $latency = round((microtime(true) - $startTime) * 1000) . 'ms';
                if ($resp->successful()) {
                    $json = $resp->json();
                    $credit = $json['data']['credit'] ?? $json['credit'] ?? 0;
                    return [
                        'success'     => true,
                        'is_auth_ok'  => true,
                        'latency'     => $latency,
                        'status_code' => $resp->status(),
                        'credit'      => (float) $credit,
                        'currency'    => 'IDR',
                        'email'       => '-',
                        'message'     => "Gateway CEIRKU aktif & responsif ({$latency})! Saldo: Rp " . number_format((float) $credit, 0, ',', '.'),
                        'raw'         => $json,
                    ];
                }
            } catch (\Throwable $e) {
                // fall through ke standar baru
            }
        }

        // Jalur standar baru CEIRKU: action = accountinfo
        $res = self::execute('accountinfo', null, $url, $key, $user);

        // Jika endpoint 404 (misal testing legacy fake /balance), coba fallback ke /balance
        if (!$res['success'] && $res['status_code'] === 404 && !str_ends_with($url, '/balance')) {
            $fallbackUrl = $url . '/balance';
            try {
                $startTime = microtime(true);
                $resp = Http::withoutVerifying()->withHeaders([
                    'X-Api-Key'  => $key,
                    'Accept'     => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 ROMEI-Gateway/2.0',
                ])->timeout(8)->post($fallbackUrl);

                if ($resp->successful()) {
                    $latency = round((microtime(true) - $startTime) * 1000) . 'ms';
                    $json = $resp->json();
                    $credit = $json['data']['credit'] ?? $json['credit'] ?? 0;
                    return [
                        'success'     => true,
                        'is_auth_ok'  => true,
                        'latency'     => $latency,
                        'status_code' => $resp->status(),
                        'credit'      => (float) $credit,
                        'currency'    => 'IDR',
                        'email'       => '-',
                        'message'     => "Gateway CEIRKU aktif & responsif ({$latency})! Saldo: Rp " . number_format((float) $credit, 0, ',', '.'),
                        'raw'         => $json,
                    ];
                }
            } catch (\Throwable $e) {
                // pertahankan hasil asli
            }
        }

        if ($res['success']) {
            $data     = $res['data'] ?? [];
            $credit   = $data['credit'] ?? $data['Credit'] ?? $data['balance'] ?? 0;
            $currency = $data['currency'] ?? $data['Currency'] ?? 'IDR';
            $email    = $data['mail'] ?? $data['email'] ?? $data['Email'] ?? '-';

            return [
                'success'     => true,
                'is_auth_ok'  => true,
                'latency'     => $res['latency'],
                'status_code' => $res['status_code'],
                'credit'      => (float) $credit,
                'currency'    => (string) $currency,
                'email'       => (string) $email,
                'message'     => "Gateway CEIRKU aktif & responsif ({$res['latency']})! Kredensial valid (Saldo: Rp " . number_format((float) $credit, 0, ',', '.') . " {$currency}).",
                'raw'         => $res['raw'],
            ];
        }

        $errMsg = $res['message'];

        return [
            'success'     => in_array($res['status_code'], [200, 401, 403]),
            'is_auth_ok'  => false,
            'latency'     => $res['latency'],
            'status_code' => $res['status_code'],
            'credit'      => 0,
            'currency'    => 'IDR',
            'email'       => '-',
            'message'     => "Gateway CEIRKU terhubung ({$res['latency']}), namun kredensial ditolak: {$errMsg}",
            'raw'         => $res['raw'],
        ];
    }

    /**
     * Dapatkan nominal saldo reseller secara realtime
     */
    public static function getBalance(): float
    {
        $info = self::getAccountInfo();
        if ($info['is_auth_ok'] ?? false) {
            return (float) ($info['credit'] ?? 0);
        }
        return 0;
    }

    /**
     * 2. Sync Daftar Layanan (action: imeiservicelist)
     */
    public static function getServiceList(?string $apiUrl = null, ?string $apiKey = null, ?string $username = null): array
    {
        $res = self::execute('imeiservicelist', null, $apiUrl, $apiKey, $username);
        if (!$res['success']) {
            return [
                'success'  => false,
                'message'  => $res['message'],
                'services' => [],
            ];
        }

        $services = [];
        $data = $res['data'] ?? [];

        // Parsing format Dhru Fusion: SUCCESS[0]['LIST'][<kategori>]['SERVICES']
        if (isset($data['LIST']) && is_array($data['LIST'])) {
            foreach ($data['LIST'] as $groupName => $groupData) {
                $groupServices = $groupData['SERVICES'] ?? $groupData['services'] ?? [];
                if (is_array($groupServices)) {
                    foreach ($groupServices as $svc) {
                        $services[] = [
                            'id'       => $svc['SERVICEID'] ?? $svc['service_id'] ?? $svc['id'] ?? null,
                            'name'     => $svc['SERVICENAME'] ?? $svc['service_name'] ?? $svc['name'] ?? '',
                            'credit'   => (float) ($svc['CREDIT'] ?? $svc['credit'] ?? $svc['price'] ?? 0),
                            'time'     => $svc['TIME'] ?? $svc['time'] ?? $svc['delivery_time'] ?? '',
                            'group'    => (string) $groupName,
                        ];
                    }
                }
            }
        } elseif (isset($data['services']) && is_array($data['services'])) {
            $services = $data['services'];
        }

        return [
            'success'  => true,
            'message'  => 'Daftar layanan berhasil diambil.',
            'services' => $services,
            'raw'      => $res['raw'],
        ];
    }

    /**
     * 3. Place Order IMEI (action: placeimeiorder)
     */
    public static function placeImeiOrder(string $imei, int|string $serviceId, array $extraParameters = []): array
    {
        $parameters = array_merge([
            'ID'   => (int) $serviceId,
            'IMEI' => (string) $imei,
        ], $extraParameters);

        // Standar baru: action = placeimeiorder
        $res = self::execute('placeimeiorder', $parameters);

        if ($res['success']) {
            $data = $res['data'] ?? [];
            $referenceId = $data['REFERENCEID']
                ?? $data['reference_id']
                ?? $data['order_id']
                ?? $data['ID']
                ?? null;

            return [
                'success'      => true,
                'reference_id' => $referenceId,
                'order_id'     => $referenceId,
                'message'      => $res['message'] ?? 'Order IMEI berhasil dibuat.',
                'data'         => $data,
                'raw'          => $res['raw'],
            ];
        }

        // Fallback untuk backward compatibility jika server pusat lama / legacy endpoint
        $baseUrl = self::getBaseUrl();
        if (str_contains($baseUrl, '/api/v1') || str_contains($baseUrl, 'ceirku.net') || $res['status_code'] === 404) {
            $legacyUrl = str_ends_with($baseUrl, '/order') ? $baseUrl : $baseUrl . '/order';
            try {
                $apiKey = self::getApiKey();
                $resp = Http::withoutVerifying()->withHeaders([
                    'X-Api-Key'    => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])->timeout(20)->post($legacyUrl, [
                    'service_id' => (int) $serviceId,
                    'imeis'      => [ (string) $imei ],
                ]);

                if ($resp->successful() && $resp->json('status') === true) {
                    $json = $resp->json('data') ?? [];
                    return [
                        'success'      => true,
                        'reference_id' => $json['order_id'] ?? null,
                        'order_id'     => $json['order_id'] ?? null,
                        'message'      => 'Order IMEI berhasil dibuat via gateway.',
                        'data'         => $json,
                        'raw'          => $resp->json(),
                    ];
                }
            } catch (\Throwable $e) {
                Log::error('CEIRKU legacy order fallback failed: ' . $e->getMessage());
            }
        }

        return [
            'success'      => false,
            'reference_id' => null,
            'order_id'     => null,
            'message'      => $res['message'] ?? 'Gagal membuat order IMEI ke CEIRKU.',
            'error'        => $res['error'] ?? $res['message'] ?? 'Failed',
            'data'         => null,
            'raw'          => $res['raw'],
        ];
    }

    /**
     * 4. Cek Status / Hasil Order IMEI (action: getimeiorder)
     */
    public static function getImeiOrder(string|int $referenceId): array
    {
        $parameters = [
            'ID' => $referenceId,
        ];

        $res = self::execute('getimeiorder', $parameters);

        if ($res['success']) {
            $data = $res['data'] ?? [];
            $rawStatus = $data['STATUS'] ?? $data['status'] ?? null;
            $code = $data['CODE'] ?? $data['code'] ?? $data['result'] ?? '';

            // Mapping STATUS Dhru:
            // 0 = Pending / In Process
            // 1 = Rejected / Canceled / Failed
            // 2 = Success / Completed / Done
            // 3 = In Process / Processing
            $statusLabel = 'PROCESSING';
            if ($rawStatus === 2 || $rawStatus === '2' || strtoupper((string) $rawStatus) === 'SUCCESS' || strtoupper((string) $rawStatus) === 'COMPLETED') {
                $statusLabel = 'SUCCESS';
            } elseif ($rawStatus === 1 || $rawStatus === '1' || strtoupper((string) $rawStatus) === 'REJECTED' || strtoupper((string) $rawStatus) === 'FAILED' || strtoupper((string) $rawStatus) === 'CANCELED') {
                $statusLabel = 'FAILED';
            } elseif ($rawStatus === 0 || $rawStatus === 3 || $rawStatus === '0' || $rawStatus === '3' || strtoupper((string) $rawStatus) === 'PENDING' || strtoupper((string) $rawStatus) === 'PROCESSING') {
                $statusLabel = 'PROCESSING';
            }

            return [
                'success'      => true,
                'status'       => $statusLabel,
                'raw_status'   => $rawStatus,
                'code'         => (string) $code,
                'message'      => $res['message'] ?? 'Order status retrieved',
                'data'         => $data,
                'raw'          => $res['raw'],
            ];
        }

        return [
            'success'      => false,
            'status'       => 'UNKNOWN',
            'raw_status'   => null,
            'code'         => '',
            'message'      => $res['message'] ?? 'Gagal memeriksa status order.',
            'data'         => null,
            'raw'          => $res['raw'],
        ];
    }

    /**
     * Daftarkan nomor IMEI ke API Server Pusat CEIRKU (backward-compatible)
     */
    public function registerImei(string $imei, int $serviceId)
    {
        $res = self::placeImeiOrder($imei, $serviceId);
        if ($res['success']) {
            Log::info('CEIRKU SERVICE SUCCESS - IMEI Berhasil Terdaftar via Gateway Sync.', [
                'imei'     => $imei,
                'order_id' => $res['reference_id']
            ]);
            return $res['data'] ?? ['order_id' => $res['reference_id']];
        }

        Log::error('CEIRKU SERVICE API ERROR - Server Pusat Menolak Payload.', [
            'imei'     => $imei,
            'response' => $res
        ]);

        return false;
    }

    /**
     * Verifikasi status perangkat hardware ke CEIRKU (backward-compatible)
     */
    public function checkDevice(string $imei): object
    {
        try {
            $mode = Setting::get('ceirku_mode', 'sandbox');
            $serviceId = ($mode === 'live') ? 30 : 20;

            $res = self::placeImeiOrder($imei, $serviceId);

            $isBlocked = false;
            $isSimLocked = false;

            if ($res['success']) {
                $code = strtoupper((string) ($res['data']['CODE'] ?? $res['data']['result'] ?? ''));
                if (str_contains($code, 'BLOCK') || str_contains($code, 'LOCKED')) {
                    $isBlocked = true;
                }
            }

            return (object) [
                'isBlocked'   => $isBlocked,
                'isSimLocked' => $isSimLocked,
                'status'      => $res['success'],
            ];
        } catch (\Throwable $e) {
            Log::warning('CEIRKU CheckDevice Exception: ' . $e->getMessage());
            return (object) [
                'isBlocked'   => false,
                'isSimLocked' => false,
                'status'      => false,
            ];
        }
    }

    /**
     * Kirim pendaftaran antrean IMEI Job ke CEIRKU (backward-compatible)
     */
    public function submitRegistration(array $payload): array
    {
        try {
            $mode = Setting::get('ceirku_mode', 'sandbox');
            $serviceId = ($mode === 'live') ? 30 : 21;
            $imei = $payload['imei_1'] ?? $payload['imei'] ?? null;

            if (empty($imei)) {
                return [
                    'status'  => 'FAILED',
                    'message' => 'Nomor IMEI 1 tidak boleh kosong.'
                ];
            }

            $res = self::placeImeiOrder((string) $imei, $serviceId);

            if ($res['success']) {
                return [
                    'status'  => 'SUCCESS',
                    'data'    => $res['data'] ?? ['order_id' => $res['reference_id']],
                    'message' => 'Registrasi IMEI berhasil disubmit ke pusat.'
                ];
            }

            return [
                'status'  => 'FAILED',
                'message' => $res['message'] ?? 'Ditolak oleh gateway server CEIRKU.'
            ];
        } catch (\Throwable $e) {
            Log::error('CEIRKU submitRegistration Exception: ' . $e->getMessage());
            return [
                'status'  => 'FAILED',
                'message' => 'Gangguan koneksi gateway: ' . $e->getMessage()
            ];
        }
    }
}