<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QrquService
{
    /**
     * Normalisasi Base URL API Gateway QRqu
     * Menghapus trailing slash dan sub-path bawaan seperti /api, /v1, /api/v1, /invoices, dll.
     */
    public static function normalizeBaseUrl(?string $url): string
    {
        $url = trim((string) $url);
        if (empty($url)) {
            return '';
        }

        // Tambahkan skema HTTP/HTTPS bila pengguna lupa mencantumkannya
        if (!preg_match('#^https?://#i', $url)) {
            $isLocal = str_contains($url, 'localhost') || str_contains($url, '127.0.0.1');
            $url = ($isLocal ? 'http://' : 'https://') . $url;
        }

        $url = rtrim($url, '/');

        // Bersihkan path endpoint umum jika pengguna menempelkan full API URL
        $redundantPaths = [
            '#/api/v1/invoices/?$#i',
            '#/api/v1/account/?$#i',
            '#/api/v1/?$#i',
            '#/v1/invoices/?$#i',
            '#/v1/account/?$#i',
            '#/v1/?$#i',
            '#/api/?$#i',
        ];

        foreach ($redundantPaths as $pattern) {
            $url = preg_replace($pattern, '', $url);
        }

        return rtrim($url, '/');
    }

    /**
     * Dapatkan Base URL API Gateway QRqu yang sudah dinormalisasi
     */
    public static function getBaseUrl(): string
    {
        $rawUrl = Setting::get('qrqu_api_url', 'http://localhost:8000');
        return self::normalizeBaseUrl($rawUrl);
    }

    /**
     * Dapatkan API Key Merchant QRqu
     */
    public static function getApiKey(): string
    {
        return trim((string) Setting::get('qrqu_api_key', ''));
    }

    /**
     * Dapatkan API Secret Merchant QRqu
     */
    public static function getApiSecret(): string
    {
        return trim((string) Setting::get('qrqu_api_secret', ''));
    }

    /**
     * Dapatkan Webhook Secret HMAC QRqu
     */
    public static function getWebhookSecret(): string
    {
        $secret = trim((string) Setting::get('qrqu_webhook_secret', ''));
        return !empty($secret) ? $secret : self::getApiSecret();
    }

    /**
     * Generate Invoice & QRIS ke platform QRqu
     *
     * @param object $transaction
     * @return array [ 'status' => 'success'|'error', 'payment_url' => string, 'qr_string' => ?string, 'invoice_id' => ?string, 'message' => ?string ]
     */
    public function generateInvoice(object $transaction): array
    {
        try {
            $baseUrl = self::getBaseUrl();
            $apiKey = self::getApiKey();
            $apiSecret = self::getApiSecret();

            if (empty($apiKey) || empty($apiSecret)) {
                throw new \Exception('Kredensial API QRqu (API Key / Secret) belum diatur di menu Pengaturan Admin.');
            }

            $externalId = (string) ($transaction->invoice_number ?? ('INV-ROMEI-' . time()));
            $amount = (int) ($transaction->amount ?? 0);

            $user = $transaction->user ?? null;
            $customerName = $user->name ?? 'Pelanggan ROMEI';
            $customerEmail = $user->email ?? 'pelanggan@romei.my.id';
            $customerPhone = $user->whatsapp_number ?? ($user->phone ?? '081234567890');

            $payload = [
                'external_id'    => $externalId,
                'amount'         => $amount,
                'description'    => "Pembayaran ROMEI #{$externalId}",
                'customer'       => [
                    'name'  => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                ],
                'callback_url'   => url('/wallet'),
                'webhook_url'    => url('/api/webhook/qrqu'),
                'expired_at'     => now()->addMinutes(60)->toIso8601String(),
            ];

            $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $timestamp = (string) time();
            $nonce = Str::random(16);
            $signature = hash_hmac('sha256', $apiKey . $timestamp . $nonce . $rawBody, $apiSecret);

            // Daftar kandidat rute endpoint invoice (mendukung prefix /api/v1 maupun /v1)
            $candidateEndpoints = [
                $baseUrl . '/api/v1/invoices',
                $baseUrl . '/v1/invoices',
            ];

            $response = null;
            foreach ($candidateEndpoints as $endpoint) {
                $response = Http::withHeaders([
                    'Content-Type'      => 'application/json',
                    'Accept'            => 'application/json',
                    'X-QRQU-KEY'        => $apiKey,
                    'X-QRQU-TIMESTAMP'  => $timestamp,
                    'X-QRQU-NONCE'      => $nonce,
                    'X-QRQU-SIGNATURE'  => $signature,
                    'Idempotency-Key'   => $externalId,
                ])
                    ->timeout(15)
                    ->withBody($rawBody, 'application/json')
                    ->post($endpoint);

                if ($response->status() !== 404) {
                    break;
                }
            }

            if ($response && $response->successful()) {
                $json = $response->json();
                $data = $json['data'] ?? [];

                $invoiceId = $data['invoice_id'] ?? null;
                $checkoutUrl = $data['qr_url'] ?? ($invoiceId ? "{$baseUrl}/checkout/{$invoiceId}" : null);
                $qrString = $data['qr_string'] ?? null;

                Log::info("ROMEI QRQU SUCCESS - Invoice berhasil dibuat via QRqu: {$externalId}", [
                    'invoice_id'   => $invoiceId,
                    'checkout_url' => $checkoutUrl,
                ]);

                return [
                    'status'             => 'success',
                    'provider'           => 'qrqu',
                    'payment_url'        => $checkoutUrl,
                    'qr_string'          => $qrString,
                    'invoice_id'         => $invoiceId,
                    'transaction_number' => $externalId,
                    'invoice_number'     => $externalId,
                ];
            }

            $statusCode = $response ? $response->status() : 500;
            $errorMsg = $response?->json('error.message') ?? $response?->json('message');

            if ($statusCode === 404) {
                $errorMsg = "Endpoint pembuatan invoice QRqu tidak ditemukan (HTTP 404). Periksa konfigurasi Base URL.";
            } elseif (!$errorMsg) {
                $errorMsg = "Ditolak oleh gateway server QRqu (HTTP {$statusCode})";
            }

            Log::error('ROMEI QRQU REFUSED - QRqu API menolak payload invoice', [
                'external_id' => $externalId,
                'status'      => $statusCode,
                'response'    => $response?->json(),
            ]);

            return [
                'status'  => 'error',
                'message' => $errorMsg,
            ];

        } catch (\Throwable $e) {
            Log::error('ROMEI QRQU EXCEPTION - Gagal menghubungi QRqu Gateway: ' . $e->getMessage());
            return [
                'status'  => 'error',
                'message' => 'Gangguan koneksi QRqu Gateway: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Wrapper kesesuaian interface generateQris
     *
     * @param object $transaction
     * @return string|null Payment Checkout URL
     */
    public function generateQris(object $transaction): ?string
    {
        $res = $this->generateInvoice($transaction);
        if (($res['status'] ?? '') === 'success' && !empty($res['payment_url'])) {
            return $res['payment_url'];
        }
        return null;
    }

    /**
     * Uji koneksi jembatan API QRqu secara realtime (Handshake Test)
     */
    public function testConnection(?string $apiUrl = null, ?string $apiKey = null, ?string $apiSecret = null): array
    {
        $rawUrl = self::normalizeBaseUrl($apiUrl ?: self::getBaseUrl());
        $key = trim((string) ($apiKey ?: self::getApiKey()));
        $secret = trim((string) ($apiSecret ?: self::getApiSecret()));

        if (empty($rawUrl)) {
            return [
                'success'     => false,
                'latency'     => '--',
                'status_code' => 400,
                'is_auth_ok'  => false,
                'message'     => 'URL Gateway QRqu tidak boleh kosong.',
            ];
        }

        $startTime = microtime(true);

        try {
            // 1. Periksa ketersediaan server endpoint (probe health)
            $healthEndpoints = [
                $rawUrl . '/api/health',
                $rawUrl . '/health',
                $rawUrl . '/up',
                $rawUrl,
            ];

            $serverReachable = false;
            $healthStatus = null;
            $latency = '--';

            foreach ($healthEndpoints as $hUrl) {
                try {
                    $probeRes = Http::timeout(4)->get($hUrl);
                    if ($probeRes->successful() || in_array($probeRes->status(), [401, 403, 301, 302])) {
                        $serverReachable = true;
                        $healthStatus = $probeRes->status();
                        $latency = round((microtime(true) - $startTime) * 1000) . 'ms';
                        break;
                    }
                } catch (\Throwable) {
                    // Lanjutkan uji kandidat endpoint berikutnya
                }
            }

            // 2. Jika kredensial diberikan, uji otentikasi merchant profil
            if (!empty($key) && !empty($secret)) {
                $timestamp = (string) time();
                $nonce = Str::random(16);
                $signature = hash_hmac('sha256', $key . $timestamp . $nonce . '', $secret);

                $authStartTime = microtime(true);
                $accountEndpoints = [
                    $rawUrl . '/api/v1/account',
                    $rawUrl . '/v1/account',
                    $rawUrl . '/account',
                ];

                $lastResponse = null;
                $authOk = false;
                $activeAccountData = null;

                foreach ($accountEndpoints as $accUrl) {
                    try {
                        $res = Http::withHeaders([
                            'Accept'           => 'application/json',
                            'X-QRQU-KEY'       => $key,
                            'X-QRQU-TIMESTAMP' => $timestamp,
                            'X-QRQU-NONCE'     => $nonce,
                            'X-QRQU-SIGNATURE' => $signature,
                        ])
                            ->timeout(7)
                            ->get($accUrl);

                        $lastResponse = $res;

                        if ($res->successful()) {
                            $authOk = true;
                            $activeAccountData = $res->json('data', []);
                            break;
                        }

                        // Jika status bukan 404 (misal 401 Unauthorized, 403 Forbidden, 429 Too Many Requests),
                        // berarti endpoint DITEMUKAN namun kredensial atau otentikasi bermasalah!
                        if ($res->status() !== 404) {
                            break;
                        }
                    } catch (\Throwable $e) {
                        // Coba endpoint kandidat berikutnya
                    }
                }

                $authLatency = round((microtime(true) - $authStartTime) * 1000) . 'ms';

                if ($authOk && $lastResponse) {
                    $merchantName = $activeAccountData['name'] ?? $activeAccountData['company_name'] ?? 'Merchant';
                    $planName = $activeAccountData['subscription']['plan'] ?? 'Aktif';

                    return [
                        'success'     => true,
                        'latency'     => $authLatency,
                        'status_code' => $lastResponse->status(),
                        'is_auth_ok'  => true,
                        'message'     => "Gateway QRqu aktif & otentikasi valid ({$authLatency})! Akun: {$merchantName} ({$planName}).",
                    ];
                }

                if ($lastResponse) {
                    $statusCode = $lastResponse->status();
                    $errorJson = $lastResponse->json();
                    $errorCode = $errorJson['error']['code'] ?? null;
                    $errorMsg = $errorJson['error']['message'] ?? $errorJson['message'] ?? null;

                    // Diagnostik spesifik berdasarkan HTTP Status Code
                    if ($statusCode === 404) {
                        return [
                            'success'     => false,
                            'latency'     => $authLatency,
                            'status_code' => 404,
                            'is_auth_ok'  => false,
                            'message'     => "Server merespon ({$authLatency}), namun endpoint /api/v1/account tidak ditemukan (HTTP 404). Pastikan Base URL tidak memiliki akhiran sub-path dan server QRqu telah memuat route API v1.",
                        ];
                    }

                    if ($statusCode === 401) {
                        $detail = match ($errorCode) {
                            'INVALID_API_KEY'            => 'API Key tidak terdaftar atau non-aktif di portal QRqu.',
                            'INVALID_SIGNATURE'          => 'Signature HMAC-SHA256 ditolak. Pastikan API Secret sesuai dengan API Key.',
                            'TIMESTAMP_EXPIRED'          => 'Timestamp server ROMEI dan QRqu tidak sinkron (selisih > 300 detik).',
                            'REPLAY_ATTACK_DETECTED'     => 'Nonce sudah digunakan, coba beberapa detik lagi.',
                            'IP_NOT_WHITELISTED'         => 'Alamat IP server ROMEI belum di-whitelist di portal QRqu.',
                            'SUBSCRIPTION_EXPIRED'       => 'Akun merchant QRqu belum memiliki paket langganan aktif.',
                            'ACCOUNT_SUSPENDED'          => 'Akun merchant QRqu dalam status dibekukan / ditangguhkan.',
                            default                      => $errorMsg ?: 'Kredensial ditolak oleh QRqu.',
                        };

                        return [
                            'success'     => false,
                            'latency'     => $authLatency,
                            'status_code' => 401,
                            'is_auth_ok'  => false,
                            'message'     => "Otentikasi ditolak (HTTP 401): {$detail}",
                        ];
                    }

                    if ($statusCode === 403) {
                        return [
                            'success'     => false,
                            'latency'     => $authLatency,
                            'status_code' => 403,
                            'is_auth_ok'  => false,
                            'message'     => "Akses ditolak (HTTP 403): " . ($errorMsg ?: 'Periksa hak akses API Key QRqu Anda.'),
                        ];
                    }

                    return [
                        'success'     => false,
                        'latency'     => $authLatency,
                        'status_code' => $statusCode,
                        'is_auth_ok'  => false,
                        'message'     => "Server QRqu merespon ({$authLatency}) dengan status HTTP {$statusCode}: " . ($errorMsg ?: 'Respon tidak terduga.'),
                    ];
                }
            }

            // Jika hanya cek URL tanpa key atau key belum diisi
            if ($serverReachable) {
                return [
                    'success'     => true,
                    'latency'     => $latency,
                    'status_code' => $healthStatus ?: 200,
                    'is_auth_ok'  => false,
                    'message'     => "Server endpoint QRqu aktif ({$latency})! Silakan masukkan API Key & Secret untuk verifikasi akun.",
                ];
            }

            return [
                'success'     => false,
                'latency'     => '--',
                'status_code' => 503,
                'is_auth_ok'  => false,
                'message'     => "Tidak dapat menjangkau server QRqu di {$rawUrl}. Pastikan domain/IP dan port aktif.",
            ];

        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'latency'     => '--',
                'status_code' => 500,
                'is_auth_ok'  => false,
                'message'     => "Gagal terhubung ke {$rawUrl}: " . $e->getMessage(),
            ];
        }
    }
}
