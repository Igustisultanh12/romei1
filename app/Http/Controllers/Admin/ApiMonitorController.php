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
        $rawCeirUrl = rtrim(Setting::get('ceirku_url', 'https://ceirku.net'), '/');
        $dokuBaseUrl = rtrim(Setting::get('doku_api_base_url', 'https://api.doku.com'), '/');
        $ceirApiKey = trim(Setting::get('ceirku_api_key'));

        // NORMALISASI URL CEIRKU: Memastikan '/api/v1' ada di dalam URL secara tepat dan tidak ganda
        if (str_contains($rawCeirUrl, '/api/v1')) {
            $ceirBaseUrl = $rawCeirUrl;
        } else {
            $ceirBaseUrl = $rawCeirUrl . '/api/v1'; // Menjamin format https://ceirku.id/api/v1
        }

        // 2. Ambil data Saldo Aktual dari CEIRKU via endpoint /balance
        $ceirBalance = 0;
        if (!empty($ceirApiKey)) {
            try {
                // Request menggunakan URL ter-normalisasi resmi menuju endpoint /balance
                $balanceResponse = Http::timeout(5)
                    ->withHeaders([
                        'X-Api-Key' => $ceirApiKey,
                        'Accept' => 'application/json',
                    ])
                    ->post($ceirBaseUrl . '/balance'); // Hasil akhir: https://ceirku.id/api/v1/balance

                if ($balanceResponse->successful()) {
                    // Sesuai Dokumentasi Hal 7-8: Mengambil data.credit
                    $ceirBalance = $balanceResponse->json('data.credit') ?? $balanceResponse->json('credit') ?? 0;
                } else {
                    // Menyuntikkan log informatif ke aaPanel jika API Key ditolak atau IP Server diblokir
                    Log::error('ROMEI MONITOR - Gagal Tarik Saldo CEIRKU. HTTP Status: ' . $balanceResponse->status() . ' | Response: ' . $balanceResponse->body());
                }
            } catch (\Exception $e) {
                Log::error('ROMEI Live - CEIRKU Balance Fetch Exception: ' . $e->getMessage());
                $ceirBalance = 0;
            }
        }

        // 3. Monitoring Latensi Respons Jaringan Gateway Menggunakan Skema Sandbox / Test
        $apiStatuses = [
            [
                'name' => 'CEIRKU Realtime Check API', 
                'endpoint' => 'POST /v1/imei/check', 
                'url' => $ceirBaseUrl . '/order', // Target endpoint utama pembuatan order sandbox
                'type' => 'ceirku',
                'payload' => [
                    'service_id' => 20, // Test Status Roamer (Sandbox - Biaya Rp 0)
                    'imeis' => ['123456789012345']
                ]
            ],
            [
                'name' => 'CEIRKU Database Sync Gateway', 
                'endpoint' => 'POST /v1/imei/register', 
                'url' => $ceirBaseUrl . '/order',
                'type' => 'ceirku',
                'payload' => [
                    'service_id' => 21, // Test Status Unknown (Sandbox - Biaya Rp 0)
                    'imeis' => ['123456789012345']
                ]
            ],
            [
                'name' => 'DOKU QRIS Invoice Generator', 
                'endpoint' => 'POST /checkout/v1/payment', 
                'url' => $dokuBaseUrl . '/checkout/v1/payment',
                'type' => 'doku',
                'payload' => null
            ]
        ];

        $monitoredApis = [];
        foreach ($apiStatuses as $api) {
            $startTime = microtime(true);
            $status = 'offline';
            $latency = '--';

            try {
                $request = Http::timeout(4)->acceptJson();
                
                if ($api['type'] === 'ceirku' && !empty($ceirApiKey)) {
                    $request->withHeaders(['X-Api-Key' => $ceirApiKey]);
                    $response = $request->post($api['url'], $api['payload']);
                } else {
                    $response = $request->get($api['url']);
                }
                
                $endTime = microtime(true);
                $latency = round(($endTime - $startTime) * 1000) . 'ms';

                if ($response->successful() || ($api['type'] === 'ceirku' && $response->json('status') === true)) { 
                    $status = 'online'; 
                } else if ($response->status() >= 500) { 
                    $status = 'maintenance'; 
                } else {
                    if ($response->json('error_code') === 'IP_NOT_WHITELISTED') {
                        Log::warning('ROMEI Monitor - IP Server belum terdaftar di whitelist CEIRKU: ' . $response->json('client_ip'));
                    }
                    $status = 'offline';
                }
            } catch (\Exception $e) {
                $status = 'offline';
                $latency = '--';
            }

            $monitoredApis[] = [
                'name' => $api['name'],
                'endpoint' => $api['endpoint'],
                'latency' => $latency,
                'status' => $status
            ];
        }

        $monitoredApis[] = [
            'name' => 'DOKU IPN Webhook Receiver (Lokal)',
            'endpoint' => 'POST /api/v1/callback/doku',
            'latency' => '0ms',
            'status' => 'online'
        ];

        return Inertia::render('ApiMonitor/Index', [
            'apis' => $monitoredApis,
            'ceir_balance' => (float) $ceirBalance
        ]);
    }

    /**
     * METHOD LIVE CHECKOUT (SINKRON 100% DENGAN MODEL TRANSACTION & DOKU SERVICE)
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

            $dokuService = new \App\Services\Payment\DokuService();
            $paymentUrl = $dokuService->generateQris($transaction);

            if ($paymentUrl) {
                Cache::put('payment_status_' . $invoiceId, 'PENDING', 600);

                return response()->json([
                    'status'      => 'success',
                    'message'     => 'Koneksi Sukses! Halaman Invoice Pembayaran Berhasil Diterbitkan Resmi Oleh DOKU.',
                    'invoice_id'  => $invoiceId,
                    'payment_url' => $paymentUrl
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'DOKU Live Gateway menolak payload mas. Silakan periksa log server aaPanel.'
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

        if ($status === 'SUCCESS') {
            return response()->json([
                'status' => 'success',
                'payment_status' => 'SUCCESS'
            ]);
        }

        return response()->json([
            'status' => 'pending',
            'payment_status' => 'PENDING'
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