<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RoamerRegistrationController extends Controller
{
    /**
     * EKSEKUSI ADD ROAMER DINAMIS (1 BULAN & 3 BULAN)
     * DOKUMENTASI TERBARU: Menggunakan endpoint POST /api/v1/roamer/add.
     * PARAMETER SINKRON: service_id 24 (1 Bulan) atau service_id 36 (3 Bulan)[cite: 2].
     */
    public function register(Request $request)
    {
        $request->validate([
            'imei'       => 'required|digits:15',
            'duration'   => 'required|in:1,3' // Pilihan durasi bulan dari frontend
        ]);

        try {
            $user = auth()->user();
            $wallet = $user->wallet;

            // 1. Tentukan Service ID dan ambil configurasi harga dinamis admin dari database settings
            if ($request->duration == 1) {
                $serviceId = 24;
                $fee = (int) Setting::get('fee_add_roamer_1m', 135000); // Mengambil setting harga paket 1 bulan
            } else {
                $serviceId = 36;
                $fee = (int) Setting::get('fee_add_roamer_3m', 180000); // Mengambil setting harga paket 3 bulan
            }

            if (!$wallet || $wallet->balance < $fee) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Saldo Wallet ROMEI Anda tidak mencukupi untuk mengaktifkan paket Roamer ini. Dibutuhkan ' . $fee
                ], 400);
            }

            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY'); 
            $apiUrl = \App\Services\CeirkuService::getRoamerAddUrl();

            // 2. Eksekusi API Pusat sesuai dokumentasi halaman 15
            $response = Http::withHeaders([
                'X-Api-Key'    => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->timeout(15)->post($apiUrl, [
                'imei'       => (string) $request->imei,
                'service_id' => $serviceId
            ]);

            // Tangani status bentrok antrean (Conflict - HTTP 409)
            if ($response->status() === 409) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Pendaftaran ditolak! IMEI ini sudah dimasukkan ke sistem dan masih berstatus pending/proses.'
                ], 409);
            }

            if ($response->successful() && $response->json('status') === true) { 
                $json = $response->json('data');

                DB::transaction(function () use ($wallet, $fee, $user, $request, $json, $serviceId) {
                    // Potong Saldo Wallet Pengguna secara aman
                    $wallet->update(['balance' => $wallet->balance - $fee]);

                    $invoicePrefix = ($serviceId === 24) ? 'ROAM1M-' : 'ROAM3M-';

                    // Rekam mutasi ledger keuangan internal ROMEI Engine
                    Transaction::create([
                        'invoice_number' => $invoicePrefix . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3)), 
                        'amount'         => $fee,
                        'status'         => 'SUCCESS',
                        'user_id'        => $user->id,
                        'payable_type'   => Wallet::class,
                        'payable_id'     => $wallet->id,
                        'description'    => 'Aktivasi ' . ($serviceId === 24 ? 'Add Roamer 1 Bulan' : 'Add Roamer 3 Bulan') . ' untuk IMEI: ' . $request->imei . ' (Order ID: ' . ($json['order_id'] ?? 'N/A') . ')',
                        'metadata'       => [
                            'ceirku_order_id' => $json['order_id'] ?? 'N/A',
                            'ceirku_status'   => 'PROCESSING',
                            'ceirku_result'   => 'Menunggu proses aktivasi operator pusat.',
                        ]
                    ]);
                });

                return response()->json([
                    'status' => 'success',
                    'message' => $response->json('message') ?? 'Pesanan Roamer berhasil dibuat dan sedang diproses oleh admin.',
                    'data' => [
                        'imei' => $json['imei'] ?? $request->imei,
                        'order_id' => $json['order_id'] ?? 'N/A',
                        'order_status' => $json['order_status'] ?? 'pending'
                    ]
                ], 200);
            }

            $errMessage = $response->json('message') ?? 'Server Jaringan Pusat menolak registrasi roamer.';
            return response()->json(['status' => 'error', 'message' => 'Gagal mengaktifkan paket roamer: ' . $errMessage], 422);

        } catch (\Exception $e) {
            Log::error('ROMEI SERVICE ERROR - Add Roamer Gagal: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Sistem gagal mendaftarkan Roamer ke server pusat.'], 500);
        }
    }
}