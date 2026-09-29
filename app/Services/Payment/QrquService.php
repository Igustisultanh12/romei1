<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QrquService
{
    /**
     * Dapatkan Base URL API Gateway QRqu
     */
    public static function getBaseUrl(): string
    {
        $rawUrl = Setting::get('qrqu_api_url', 'http://localhost:8000');
        return rtrim(trim($rawUrl), '/');
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

            $endpoint = $baseUrl . '/api/v1/invoices';

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

            if ($response->successful()) {
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

            $errorMsg = $response->json('error.message') ?? $response->json('message') ?? 'Ditolak oleh gateway server QRqu (HTTP ' . $response->status() . ')';
            Log::error('ROMEI QRQU REFUSED - QRqu API menolak payload invoice', [
                'external_id' => $externalId,
                'status'      => $response->status(),
                'response'    => $response->json(),
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
        $rawUrl = rtrim(trim($apiUrl ?: self::getBaseUrl()), '/');
        $key = trim((string) ($apiKey ?: self::getApiKey()));
        $secret = trim((string) ($apiSecret ?: self::getApiSecret()));

        $startTime = microtime(true);

        try {
            // 1. Cek kesehatan endpoint dasar
            $healthResponse = Http::timeout(5)->get($rawUrl . '/api/health');
            $latency = round((microtime(true) - $startTime) * 1000) . 'ms';

            // 2. Jika kredensial diberikan, uji otentikasi merchant profil
            if (!empty($key) && !empty($secret)) {
                $timestamp = (string) time();
                $nonce = Str::random(16);
                $signature = hash_hmac('sha256', $key . $timestamp . $nonce . '', $secret);

                $authStartTime = microtime(true);
                $accountResponse = Http::withHeaders([
                    'Accept'           => 'application/json',
                    'X-QRQU-KEY'       => $key,
                    'X-QRQU-TIMESTAMP' => $timestamp,
                    'X-QRQU-NONCE'     => $nonce,
                    'X-QRQU-SIGNATURE' => $signature,
                ])
                    ->timeout(6)
                    ->get($rawUrl . '/api/v1/account');

                $authLatency = round((microtime(true) - $authStartTime) * 1000) . 'ms';

                if ($accountResponse->successful()) {
                    $accountData = $accountResponse->json('data', []);
                    $merchantName = $accountData['name'] ?? $accountData['company_name'] ?? 'Merchant';
                    $planName = $accountData['subscription']['plan'] ?? 'Aktif';

                    return [
                        'success'     => true,
                        'latency'     => $authLatency,
                        'status_code' => $accountResponse->status(),
                        'is_auth_ok'  => true,
                        'message'     => "Gateway QRqu aktif & otentikasi valid ({$authLatency})! Akun: {$merchantName} ({$planName}).",
                    ];
                }

                $authError = $accountResponse->json('error.message') ?? "HTTP {$accountResponse->status()}";
                return [
                    'success'     => false,
                    'latency'     => $authLatency,
                    'status_code' => $accountResponse->status(),
                    'is_auth_ok'  => false,
                    'message'     => "Server QRqu merespon ({$authLatency}), namun otentikasi ditolak: {$authError}.",
                ];
            }

            // Jika hanya cek URL tanpa key
            if ($healthResponse->successful()) {
                return [
                    'success'     => true,
                    'latency'     => $latency,
                    'status_code' => $healthResponse->status(),
                    'is_auth_ok'  => false,
                    'message'     => "Server endpoint QRqu aktif ({$latency})! Silakan isi API Key & Secret untuk verifikasi akun.",
                ];
            }

            return [
                'success'     => false,
                'latency'     => $latency,
                'status_code' => $healthResponse->status(),
                'is_auth_ok'  => false,
                'message'     => "Server QRqu merespon HTTP {$healthResponse->status()}.",
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
