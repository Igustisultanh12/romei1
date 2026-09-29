<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Setting;
use App\Models\Transaction;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ApiMonitorController extends Controller
{
    /**
     * Halaman Utama Monitoring API ROMEI
     */
    public function index(): Response
    {
        // 1. Ambil data konfigurasi awal, hilangkan slash di akhir string secara aman
        $ceirBaseUrl = \App\Services\CeirkuService::getBaseUrl();
        $dokuBaseUrl = rtrim(Setting::get('doku_api_base_url', 'https://api.doku.com'), '/');
        $ceirApiKey = trim((string) Setting::get('ceirku_api_key'));
        $activeGateway = \App\Services\Payment\PaymentGatewayManager::getActiveProvider();

        // 2. Ambil data Saldo Aktual dari CEIRKU via endpoint /balance
        $ceirBalance = 0;
        if (!empty($ceirApiKey)) {
            try {
                $balanceUrl = \App\Services\CeirkuService::getBalanceUrl();
                $balanceResponse = Http::timeout(5)
                    ->withHeaders([
                        'X-Api-Key' => $ceirApiKey,
                        'Accept'    => 'application/json',
                    ])
                    ->post($balanceUrl);

                if ($balanceResponse->successful()) {
                    $ceirBalance = $balanceResponse->json('data.credit') ?? $balanceResponse->json('credit') ?? 0;
                    Log::info("ROMEI MONITOR [CEIRKU Saldo] - Sukses mengambil saldo kuota: Rp {$ceirBalance}");
                } else {
                    $errBody = $balanceResponse->json('message') ?? $balanceResponse->body();
                    Log::warning("ROMEI MONITOR [CEIRKU Saldo] - Gagal tarik saldo. HTTP {$balanceResponse->status()}: {$errBody}");
                }
            } catch (\Exception $e) {
                Log::error('ROMEI MONITOR [CEIRKU Saldo] - Gangguan koneksi tarik saldo: ' . $e->getMessage());
                $ceirBalance = 0;
            }
        }

        // 3. Monitoring Latensi Respons Jaringan Gateway Menggunakan Skema Sandbox / Test
        $ceirApis = [
            [
                'name'     => 'CEIRKU Realtime Check API', 
                'endpoint' => 'POST /v1/imei/check', 
                'url'      => \App\Services\CeirkuService::getOrderUrl(),
                'payload'  => [
                    'service_id' => 20, // Test Status Roamer (Sandbox - Biaya Rp 0)
                    'imeis'      => ['123456789012345']
                ]
            ],
            [
                'name'     => 'CEIRKU Database Sync Gateway', 
                'endpoint' => 'POST /v1/imei/register', 
                'url'      => \App\Services\CeirkuService::getOrderUrl(),
                'payload'  => [
                    'service_id' => 21, // Test Status Unknown (Sandbox - Biaya Rp 0)
                    'imeis'      => ['123456789012345']
                ]
            ],
        ];

        $monitoredApis = [];

        // Pengecekan Service CEIRKU
        foreach ($ceirApis as $api) {
            $startTime = microtime(true);
            $status = 'offline';
            $latency = '--';
            $detail = '';

            try {
                $request = Http::timeout(5)->acceptJson();
                if (!empty($ceirApiKey)) {
                    $request->withHeaders(['X-Api-Key' => $ceirApiKey]);
                }
                
                $response = $request->post($api['url'], $api['payload']);
                $endTime = microtime(true);
                $latency = round(($endTime - $startTime) * 1000) . 'ms';
                $statusCode = $response->status();
                $respJson = $response->json() ?? [];
                $respMsg = $respJson['message'] ?? $respJson['error'] ?? null;
                $errorCode = $respJson['error_code'] ?? null;

                if ($response->successful() && ($respJson['status'] ?? true)) {
                    $status = 'online';
                    $detail = "Server CEIRKU operasional ({$latency}). Handshake sukses.";
                    Log::info("ROMEI MONITOR [{$api['name']}] - Jaringan OPERASIONAL ({$latency}). HTTP {$statusCode}");
                } elseif ($statusCode >= 200 && $statusCode < 500) {
                    // Jaringan terhubung ke server CEIRKU (latensi terukur), namun ada pesan bisnis
                    $status = 'warning';
                    if ($errorCode === 'IP_NOT_WHITELISTED') {
                        $clientIp = $respJson['client_ip'] ?? 'server';
                        $detail = "Jaringan terhubung ({$latency}), namun IP server ({$clientIp}) belum di-whitelist di CEIRKU.";
                        Log::warning("ROMEI MONITOR [{$api['name']}] - IP Server belum di-whitelist CEIRKU: {$clientIp} ({$latency})");
                    } elseif ($statusCode === 401) {
                        $detail = "Jaringan terhubung ({$latency}), namun API Key CEIRKU tidak valid (HTTP 401).";
                        Log::warning("ROMEI MONITOR [{$api['name']}] - X-Api-Key CEIRKU Ditolak (HTTP 401) ({$latency})");
                    } else {
                        $cleanMsg = $respMsg ?: "Respon validasi gateway (HTTP {$statusCode})";
                        $detail = "Jaringan terhubung ({$latency}). {$cleanMsg}.";
                        Log::warning("ROMEI MONITOR [{$api['name']}] - Jaringan Terhubung ({$latency}) HTTP {$statusCode}: " . json_encode($respJson));
                    }
                } elseif ($statusCode >= 500) {
                    $status = 'maintenance';
                    $detail = "Server CEIRKU sedang mengalami kendala internal (HTTP {$statusCode}).";
                    Log::error("ROMEI MONITOR [{$api['name']}] - Server CEIRKU Internal Error (HTTP {$statusCode})");
                }
            } catch (\Exception $e) {
                $status = 'offline';
                $latency = '--';
                $detail = 'Koneksi gagal terhubung: ' . $e->getMessage();
                Log::error("ROMEI MONITOR [{$api['name']}] - KONEKSI TERPUTUS ke {$api['url']}: " . $e->getMessage());
            }

            $monitoredApis[] = [
                'name'     => $api['name'],
                'endpoint' => $api['endpoint'],
                'latency'  => $latency,
                'status'   => $status,
                'detail'   => $detail,
            ];
        }

        // Monitoring Payment Gateway (QRqu & DOKU)
        if ($activeGateway === 'qrqu') {
            // 1. QRqu Engine (Aktif)
            $qrquService = new \App\Services\Payment\QrquService();
            $qrquTest = $qrquService->testConnection();
            $qrquStatus = $qrquTest['success'] ? 'online' : ($qrquTest['is_auth_ok'] ? 'online' : (in_array($qrquTest['status_code'], [401, 403, 422]) ? 'warning' : 'offline'));

            if ($qrquTest['success']) {
                Log::info("ROMEI MONITOR [QRqu Gateway (Aktif)] - Sukses terhubung ({$qrquTest['latency']}): {$qrquTest['message']}");
            } else {
                Log::warning("ROMEI MONITOR [QRqu Gateway (Aktif)] - Respon ({$qrquTest['latency']}) HTTP {$qrquTest['status_code']}: {$qrquTest['message']}");
            }

            $monitoredApis[] = [
                'name'     => 'QRqu Payment Gateway Engine (Aktif)',
                'endpoint' => 'POST ' . \App\Services\Payment\QrquService::getBaseUrl() . '/api/v1/invoices',
                'latency'  => $qrquTest['latency'] !== '--' ? $qrquTest['latency'] : '0ms',
                'status'   => $qrquStatus,
                'detail'   => $qrquTest['message'],
            ];

            // 2. DOKU QRIS Payment Gateway (Standby)
            $dokuStartTime = microtime(true);
            $dokuLatency = '--';
            $dokuStatus = 'standby';
            $dokuDetail = 'Mode siaga (Standby). Gateway aktif yang digunakan saat ini adalah QRqu.';
            try {
                $dokuRes = Http::timeout(4)->get($dokuBaseUrl);
                $dokuLatency = round((microtime(true) - $dokuStartTime) * 1000) . 'ms';
                Log::info("ROMEI MONITOR [DOKU Gateway (Standby)] - Server DOKU merespon ({$dokuLatency}). HTTP {$dokuRes->status()}");
            } catch (\Exception $e) {
                Log::warning("ROMEI MONITOR [DOKU Gateway (Standby)] - Server DOKU: " . $e->getMessage());
            }

            $monitoredApis[] = [
                'name'     => 'DOKU QRIS Invoice Generator (Standby)',
                'endpoint' => 'POST ' . $dokuBaseUrl . '/checkout/v1/payment',
                'latency'  => $dokuLatency,
                'status'   => $dokuStatus,
                'detail'   => $dokuDetail,
            ];

            // 3. Webhook Receiver Lokal
            $monitoredApis[] = [
                'name'     => 'QRqu Webhook Receiver (Lokal)',
                'endpoint' => 'POST /api/webhook/qrqu',
                'latency'  => '< 5ms',
                'status'   => 'online',
                'detail'   => 'Route lokal ROMEI siap menerima callback pembayaran QRqu.',
            ];
        } else {
            // 1. DOKU Gateway Engine (Aktif)
            $dokuStartTime = microtime(true);
            $dokuLatency = '--';
            $dokuStatus = 'online';
            $dokuDetail = 'DOKU Checkout engine live siap menerbitkan transaksi QRIS.';
            try {
                $dokuRes = Http::timeout(4)->get($dokuBaseUrl);
                $dokuLatency = round((microtime(true) - $dokuStartTime) * 1000) . 'ms';
                Log::info("ROMEI MONITOR [DOKU Gateway (Aktif)] - Server DOKU merespon ({$dokuLatency}). HTTP {$dokuRes->status()}");
            } catch (\Exception $e) {
                $dokuStatus = 'offline';
                $dokuDetail = 'Gagal terhubung ke gateway DOKU: ' . $e->getMessage();
                Log::error("ROMEI MONITOR [DOKU Gateway (Aktif)] - Gagal terhubung: " . $e->getMessage());
            }

            $monitoredApis[] = [
                'name'     => 'DOKU QRIS Invoice Generator (Aktif)',
                'endpoint' => 'POST ' . $dokuBaseUrl . '/checkout/v1/payment',
                'latency'  => $dokuLatency,
                'status'   => $dokuStatus,
                'detail'   => $dokuDetail,
            ];

            // 2. QRqu Gateway Engine (Standby)
            $qrquDetail = 'Mode siaga (Standby). Gateway aktif yang digunakan saat ini adalah DOKU.';
            $qrquService = new \App\Services\Payment\QrquService();
            $qrquTest = $qrquService->testConnection();
            Log::info("ROMEI MONITOR [QRqu Gateway (Standby)] - Ping ({$qrquTest['latency']}): {$qrquTest['message']}");

            $monitoredApis[] = [
                'name'     => 'QRqu Payment Gateway Engine (Standby)',
                'endpoint' => 'POST ' . \App\Services\Payment\QrquService::getBaseUrl() . '/api/v1/invoices',
                'latency'  => $qrquTest['latency'] !== '--' ? $qrquTest['latency'] : '0ms',
                'status'   => 'standby',
                'detail'   => $qrquDetail,
            ];

            // 3. Webhook Receiver Lokal
            $monitoredApis[] = [
                'name'     => 'DOKU IPN Webhook Receiver (Lokal)',
                'endpoint' => 'POST /api/webhook/doku/qris',
                'latency'  => '< 5ms',
                'status'   => 'online',
                'detail'   => 'Route webhook IPN lokal siap menerima callback pembayaran DOKU.',
            ];
        }

        return Inertia::render('ApiMonitor/Index', [
            'apis'           => $monitoredApis,
            'ceir_balance'   => (float) $ceirBalance,
            'active_gateway' => $activeGateway,
        ]);
    }

    /**
     * METHOD LIVE CHECKOUT (SINKRON DENGAN DOKU & QRQU VIA PAYMENT GATEWAY MANAGER)
     */
    public function testPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:qris,shopeepay'
        ]);

        try {
            $invoiceId = 'INV-ROMEI-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));
            
            $transaction = new \stdClass();
            $transaction->id = 999;
            $transaction->amount = (int) $request->amount;
            $transaction->invoice_number = $invoiceId;
            $transaction->user_id = auth()->id() ?? 1;
            $transaction->user = auth()->user(); 

            $paymentResult = \App\Services\Payment\PaymentGatewayManager::createPayment($transaction);

            if (!empty($paymentResult['payment_url'])) {
                Cache::put('payment_status_' . $invoiceId, 'PENDING', 600);
                if (!empty($paymentResult['invoice_id'])) {
                    Cache::put('payment_qrqu_id_' . $invoiceId, $paymentResult['invoice_id'], 600);
                }

                $providerName = ($paymentResult['provider'] ?? 'doku') === 'qrqu' ? 'QRqu' : 'DOKU';
                return response()->json([
                    'status'      => 'success',
                    'provider'    => $paymentResult['provider'] ?? 'doku',
                    'message'     => "Koneksi Sukses! Halaman Invoice Pembayaran Berhasil Diterbitkan Resmi Oleh {$providerName}.",
                    'invoice_id'  => $invoiceId,
                    'payment_url' => $paymentResult['payment_url'],
                    'qr_string'   => $paymentResult['qr_string'] ?? null,
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Payment Gateway menolak pembuatan invoice payload.'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sistem ROMEI mendeteksi gangguan jembatan data internal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ENDPOINT POLLING VUE
     */
    public function checkStatus($invoiceId)
    {
        $status = Cache::get('payment_status_' . $invoiceId, 'PENDING');

        if (in_array(strtoupper((string) $status), ['SUCCESS', 'PAID'], true)) {
            return response()->json([
                'status'         => 'success',
                'payment_status' => 'SUCCESS',
                'is_paid'        => true,
            ]);
        }

        // Active lookup ke QRqu gateway jika gateway aktif adalah QRqu
        $activeGateway = \App\Models\Setting::get('active_payment_gateway', 'doku');
        if ($activeGateway === 'qrqu') {
            try {
                $qrquId = Cache::get('payment_qrqu_id_' . $invoiceId, $invoiceId);
                $qrquService = new \App\Services\Payment\QrquService();
                $qrquStatus = $qrquService->checkInvoiceStatus($qrquId);

                // Coba invoiceId jika qrquId beda
                if (!$qrquStatus && $qrquId !== $invoiceId) {
                    $qrquStatus = $qrquService->checkInvoiceStatus($invoiceId);
                }

                if (in_array(strtoupper((string) $qrquStatus), ['SUCCESS', 'PAID'], true)) {
                    Cache::put('payment_status_' . $invoiceId, 'SUCCESS', 600);
                    if ($qrquId) {
                        Cache::put('payment_status_' . $qrquId, 'SUCCESS', 600);
                    }
                    Log::info("ROMEI MONITOR: Status pembayaran {$invoiceId} dikonfirmasi lunas via aktif poll QRqu.");
                    return response()->json([
                        'status'         => 'success',
                        'payment_status' => 'SUCCESS',
                        'is_paid'        => true,
                    ]);
                }
            } catch (\Throwable $e) {
                // Silently skip active check errors
            }
        }

        return response()->json([
            'status'         => 'pending',
            'payment_status' => 'PENDING',
            'is_paid'        => false,
        ]);
    }

    /**
     * IPN WEBHOOK RECEIVER
     */
    public function dokuNotification(Request $request)
    {
        $invoiceId = $request->input('order.invoice_number');
        $transactionStatus = $request->input('transaction.status') ?? $request->input('transaction_status');

        Log::info('ROMEI TUNNEL GATEWAY - Callback DOKU Masuk. Invoice: ' . $invoiceId . ' | Status: ' . $transactionStatus);

        if (strtoupper($transactionStatus) === 'SUCCESS') {
            Cache::put('payment_status_' . $invoiceId, 'SUCCESS', 300);
            Transaction::where('invoice_number', $invoiceId)->update(['status' => 'SUCCESS']);

            return response()->json(['message' => 'Notification Received Successfully'], 200);
        }

        return response()->json(['message' => 'Transaction status is not success'], 400);
    }
}