<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService2
{
    /**
     * Mengirim pesan WA via Server Node.js Baileys Gateway secara dinamis (Internal Proxy)
     */
    public static function sendMessage($target, $message)
    {
        // 1. Bersihkan nomor (hilangkan spasi, strip, dll)
        $phone = preg_replace('/[^0-9]/', '', $target);

        // 2. Ambil URL Gateway dinamis dari setting database (Key: whatsapp_gateway_url)
        // Disinkronkan menggunakan port default 3100 sesuai visual dashboard ROMEI_HQ
        $baseUrl = rtrim(Setting::get('whatsapp_gateway_url', 'http://localhost:3100'), '/');
        $endpoint = $baseUrl . '/send';

        // 3. Kirim perintah dengan query parameter terstruktur ke Node.js Baileys engine
        try {
            $response = Http::timeout(15)->get($endpoint, [
                'number' => $phone,
                'msg'    => $message
            ]);

            if ($response->successful()) {
                Log::info("WA Gateway 2 Terkirim via ($endpoint) ke: $phone");
            } else {
                Log::error("Server WA ($endpoint) merespon gagal: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Koneksi ke Server WA ($endpoint) Gagal: " . $e->getMessage());
        }
    }
}