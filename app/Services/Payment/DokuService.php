<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DokuService
{
    /**
     * AMBIL KONFIGURASI DARI PUSAT KOMANDO (PLAIN TEXT)
     */
    private function getConfig()
    {
        $settings = Setting::pluck('value', 'key')->all();
        
        return [
            'client_id'  => isset($settings['doku_client_id']) ? trim($settings['doku_client_id']) : env('DOKU_CLIENT_ID'),
            'secret_key' => isset($settings['doku_secret_key']) ? trim($settings['doku_secret_key']) : env('DOKU_SECRET_KEY'),
            // PENYELARASAN KEY: Gunakan 'doku_api_base_url' sesuai dengan pengaturan harian master admin
            'base_url'   => rtrim($settings['doku_api_base_url'] ?? $settings['doku_base_url'] ?? env('DOKU_BASE_URL', 'https://api.doku.com'), '/'),
        ];
    }

    /**
     * GENERATE QRIS (Metode Direct Checkout Page Live Menggunakan Parameter Model Transaction)
     */
    public function generateQris($transaction)
    {
        $config = $this->getConfig();
        $targetPath = '/checkout/v1/payment'; 

        // 1. DINAMIS CUSTOMER DATA: Mengambil langsung dari relasi user() milik Transaction.php saat itu juga
        $customerName = $transaction->user->name ?? 'Pelanggan ROMEI';
        $customerEmail = $transaction->user->email ?? 'no-reply@romei1.my.id';
        
        // 2. KOREKSI ROUTE ERROR: Memakai fungsi url() murni agar bebas dari error Route Not Defined
        $callbackUrl = url('/dashboard') . '?status=return&transaction_id=' . $transaction->id;

        // KUNCI UTAMA 1: Urutan properti objek 'order' WAJIB diatur alfabetis (amount dulu, baru invoice_number)
        // Hal ini krusial karena DOKU Live memeriksa kecocokan string Digest berdasarkan urutan pengiriman ini.
        $body = [
            'order' => [
                'amount' => (int) $transaction->amount,
                'invoice_number' => $transaction->invoice_number,
                'callback_url' => $callbackUrl,
            ],
            'payment' => [
                'payment_due_date' => 60, // Diperpanjang ke 60 menit agar kasir/pelanggan punya waktu bayar lebih aman
                'payment_method_types' => ['QRIS'], 
            ],
            'customer' => [
                'id' => 'CUST-' . ($transaction->user_id ?? 'GUEST'),
                'name' => $customerName,      // Mengambil dinamis dari data akun pembeli riil saat itu juga
                'email' => $customerEmail,    // Mengambil dinamis dari data akun pembeli riil saat itu juga
            ]
        ];

        $response = $this->executeRequest($targetPath, $body, $config);

        // KUNCI UTAMA 2: Parsing alternatif response object DOKU secara berlapis agar link url aman ditarik
        if (isset($response['response']['payment']['url'])) {
            return $response['response']['payment']['url'];
        }

        if (isset($response['payment']['url'])) {
            return $response['payment']['url'];
        }

        Log::error('ROMEI LIVE - DOKU API Refused Payload: ' . json_encode($response));
        return null;
    }

    /**
     * EKSEKUSI REQUEST (Protokol Sinkronisasi Signature Mas Sultan)
     */
    private function executeRequest($targetPath, $body, $config)
    {
        $requestId = (string) Str::uuid();
        $timestamp = gmdate('Y-m-d\TH:i:s') . 'Z'; // Aturan baku ISO8601 UTC tanpa milidetik

        // KUNCI UTAMA 3: Gunakan flags JSON_UNESCAPED_SLASHES agar json_encode PHP tidak merusak parameter url
        $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Kalkulasi nilai Digest SHA-256 riil
        $digest = base64_encode(hash('sha256', $jsonBody, true));

        // Komponen penyusun wajib lurus rapi tanpa jeda spasi tersembunyi
        $signatureString = "Client-Id:" . $config['client_id'] . "\n" .
                           "Request-Id:" . $requestId . "\n" .
                           "Request-Timestamp:" . $timestamp . "\n" .
                           "Request-Target:" . $targetPath . "\n" .
                           "Digest:" . $digest;

        $signature = base64_encode(hash_hmac('sha256', $signatureString, $config['secret_key'], true));

        try {
            // KUNCI UTAMA 4: Gunakan ->withBody() mengirimkan raw string JSON murni agar identik 100% dengan hash Digest
            $response = Http::withHeaders([
                'Client-Id'         => $config['client_id'],
                'Request-Id'        => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature'         => "HMACSHA256=" . $signature,
                'Content-Type'      => 'application/json'
            ])
            ->timeout(15)
            ->withBody($jsonBody, 'application/json')
            ->post($config['base_url'] . $targetPath);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('ROMEI LIVE - DOKU Connection Exception: ' . $e->getMessage());
            return null;
        }
    }
}