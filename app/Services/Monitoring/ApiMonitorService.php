<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Http;
use DB;

class ApiMonitorService {
    /**
     * Menguji dan mencatat kesehatan koneksi API eksternal.
     */
    public function pingCheck(string $service, string $url, array $headers = []): array {
        $startTime = microtime(true);
        $isSuccess = false;
        $errorMessage = null;
        $statusCode = 500;

        try {
            // Lakukan pemanggilan HEAD / GET ringan untuk test ping
            $response = Http::timeout(5)->withHeaders($headers)->get($url);
            $statusCode = $response->status();
            $isSuccess = $response->successful();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        $endTime = microtime(true);
        $latencyMs = round(($endTime - $startTime) * 1000, 2);

        // Simpan ke database api_logs
        DB::table('api_logs')->insert([
            'service_name' => $service,
            'endpoint' => parse_url($url, PHP_URL_PATH) ?? '/',
            'method' => 'GET',
            'http_status' => $statusCode,
            'latency_ms' => $latencyMs,
            'is_success' => $isSuccess,
            'error_message' => $errorMessage,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'status' => $isSuccess ? 'ONLINE' : 'OFFLINE',
            'latency' => $latencyMs,
            'http_status' => $statusCode,
            'error' => $errorMessage
        ];
    }
}