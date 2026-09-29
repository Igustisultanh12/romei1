<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreImeiRequest;
use App\Actions\RegisterImeiAction;
use App\DTOs\ImeiRegistrationDTO;
use App\Models\ImeiRegistration;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;

class ImeiRegistrationController extends Controller
{
    /**
     * LANGSUNG JALUR UTAMA: Menyimpan data registrasi gawai via Action Class & DTO 
     * MANIFESTASI BARU: Potong Saldo Wallet & Eksekusi API CEIRKU sekuensial sejalan loading SweetAlert2
     * LOGIKA BISNIS: Single SIM = Rp 200.000 | Dual SIM = Rp 350.000 (200.000 * 2 - 50.000)
     */
    public function store(Request $request, RegisterImeiAction $action)
    {
        $request->validate([
            'sim_type'   => 'required|in:single,dual',
            'imei1'      => 'required|digits:15',
            'imei2'      => 'nullable|required_if:sim_type,dual|digits:15',
            'package_id' => 'required|exists:packages,id',
        ]);

        try {
            $user = auth()->user();
            $wallet = $user->wallet;
            
            // Mengunci harga basis dasar dinamis atau fallback Rp 200.000
            $singlePrice = 0;
            
            // KALKULASI BERANTAI DISKON: Single SIM = 200k, Dual SIM = 200k * 2 - 50k = 350k
            $totalRequiredFee = ($request->sim_type === 'dual') ? (($singlePrice * 2) - 50000) : $singlePrice;

            if (!$wallet || $wallet->balance < $totalRequiredFee) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Saldo internal wallet ROMEI Anda tidak mencukupi untuk mengaktifkan paket ini. Silakan top up terlebih dahulu.'
                ], 400);
            }

            $mode   = Setting::get('ceirku_mode', 'sandbox'); 
            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY'); 
            $apiUrl = 'https://ceirku.net/api/v1/order'; 
            $serviceId = ($mode === 'live') ? 30 : 20; 

            // -----------------------------------------------------------------
            // PIPELINE SEKUENSIAL STEP 1: Daftarkan Slot IMEI 1 Utama ke Pusat
            // -----------------------------------------------------------------
            $response1 = Http::withHeaders([
                'X-Api-Key'    => $apiKey, 
                'Content-Type' => 'application/json', 
                'Accept'       => 'application/json',
            ])->timeout(15)->post($apiUrl, [
                'service_id' => $serviceId, 
                'imeis'      => [ (string) $request->imei1 ] 
            ]);

            if (!$response1->successful() || $response1->json('status') !== true) {
                $msg1 = $response1->json('message') ?? 'API Server Pusat mendeteksi kegagalan data pada slot IMEI 1.';
                return response()->json(['status' => 'error', 'message' => 'Gagal verifikasi IMEI 1: ' . $msg1], 422);
            }

            $json1 = $response1->json('data');
            $orderId1 = $json1['order_id'] ?? 'N/A';

            // -----------------------------------------------------------------
            // PIPELINE SEKUENSIAL STEP 2: Daftarkan Slot IMEI 2 Sekunder (Jika Dual SIM)
            // -----------------------------------------------------------------
            $orderId2 = null;
            if ($request->sim_type === 'dual' && $request->filled('imei2')) {
                $response2 = Http::withHeaders([
                    'X-Api-Key'    => $apiKey, 
                    'Content-Type' => 'application/json', 
                ])->timeout(15)->post($apiUrl, [
                    'service_id' => $serviceId, 
                    'imeis'      => [ (string) $request->imei2 ] 
                ]);

                if (!$response2->successful() || $response2->json('status') !== true) {
                    $msg2 = $response2->json('message') ?? 'API Server Pusat mendeteksi kegagalan data pada slot IMEI 2.';
                    return response()->json([
                        'status'  => 'error', 
                        'message' => 'IMEI 1 Berhasil Lolos (ID: '.$orderId1.'), namun pendaftaran IMEI 2 ditolak. Detail: ' . $msg2
                    ], 422);
                }

                $json2 = $response2->json('data');
                $orderId2 = $json2['order_id'] ?? 'N/A';
            }

            // -----------------------------------------------------------------
            // JURNALISASI DATA INTERNAL: Eksekusi DB Transaction & Pemotongan Saldo
            // -----------------------------------------------------------------
            $registration = DB::transaction(function () use ($request, $action, $wallet, $totalRequiredFee, $user, $orderId1, $orderId2) {
                // Potong Saldo Akun Pengguna secara Aman
                $wallet->update(['balance' => $wallet->balance - $totalRequiredFee]);

                // Panggil Action Class & DTO asli bawaan Mas Sultan untuk menyimpan manifest data
                $dto = new ImeiRegistrationDTO(
                    $request->imei1,
                    $request->imei2,
                    $request->sim_type,
                    $request->package_id,
                    $user->id,
                    $request->voucher_code
                );
                
                $regData = $action->execute($dto);

                // Catat mutasi invoice transaksi komersial internal ROMEI
                Transaction::create([
                    'invoice_number' => 'ROMEI-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3)), 
                    'amount'         => $totalRequiredFee,
                    'status'         => 'SUCCESS',
                    'user_id'        => $user->id,
                    'payable_type'   => Wallet::class,
                    'payable_id'     => $wallet->id,
                    'description'    => 'Aktivasi Paket Jaringan Instan via Wallet Berantai. ID-1: ' . $orderId1 . ($orderId2 ? ' | ID-2: ' . $orderId2 : ''),
                ]);

                return $regData;
            });

            Log::info('ROMEI UNLOCK - Pendaftaran berantai sekuensial via Wallet lunas komersial.', [
                'user_id' => $user->id,
                'imei1'   => $request->imei1
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Paket IMEI perangkat gawai Anda berhasil diaktivasi berantai secara instan via Wallet!'
            ], 200);

        } catch (\Exception $e) {
            Log::error('ROMEI UNLOCK STORE ERROR - Gagal mendaftarkan IMEI Berantai: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Sistem gagal mengeksekusi pendaftaran berantai: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Step Akhir: Menampilkan halaman detail tagihan & jenerator QRIS DOKU.
     */
    public function paymentPage($id)
    {
        $registration = ImeiRegistration::with(['package', 'transactions'])->findOrFail($id);
        
        if ($registration->user_id !== auth()->id()) {
            abort(403, 'Akses Ditolak: Anda tidak berhak melihat tagihan ini.');
        }

        $transaction = $registration->transactions()->where('status', 'PENDING')->latest()->first();

        if (!$transaction) {
            return redirect()->route('dashboard')->with('error', 'Transaksi tidak ditemukan atau sudah kedaluwarsa.');
        }

        return Inertia::render('IMEI/Payment', [
            'registration' => [
                'id' => $registration->id,
                'registration_number' => $registration->registration_number,
                'sim_type' => $registration->sim_type,
                'imei1' => $registration->imei1,
                'imei2' => $registration->imei2,
                'package_name' => $registration->package->name,
            ],
            'transaction' => [
                'id' => $transaction->id,
                'invoice_number' => $transaction->invoice_number, 
                'total_amount' => (float) $transaction->amount,
                'doku_client_id' => Setting::get('doku_client_id'), 
            ]
        ]);
    }

    /**
     * UTILITAS API: Mengecek Status Layanan SIM LOCK / Roamer Status (Service ID 20 / 30)
     * MEKANISME DUAL SIM BERANTAI: Kirim IMEI 1 dulu, jika sukses/aman baru daftarkan IMEI 2.
     * HARGA DINAMIS: Mengambil nilai biaya pengecekan dari database (key: fee_check_sim_lock)
     */
    public function checkSimLockStatus(Request $request)
    {
        $request->validate([
            'imei' => 'required|digits:15',
            'imei2' => 'nullable|digits:15'
        ]);

        try {
            $user = auth()->user();
            $wallet = $user->wallet;
            
            // Membaca pengaturan harga dinamis yang diinput Admin pada panel pengaturan ROMEI HQ
            $fee = (int) Setting::get('fee_check_sim_lock', 5000);

            if (!$wallet || $wallet->balance < $fee) {
                return back()->withErrors(['message' => 'Saldo Wallet ROMEI Anda tidak mencukupi. Silakan lakukan Top Up terlebih dahulu.']);
            }

            $mode   = Setting::get('ceirku_mode', 'sandbox'); 
            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY'); 
            $apiUrl = 'https://ceirku.net/api/v1/order'; 

            $serviceId = ($mode === 'live') ? 30 : 20; 

            // 1. Eksekusi pengiriman IMEI Pertama
            $response1 = Http::withHeaders([
                'X-Api-Key'    => $apiKey, 
                'Content-Type' => 'application/json', 
                'Accept'       => 'application/json',
            ])->timeout(12)->post($apiUrl, [
                'service_id' => $serviceId, 
                'imeis'      => [ (string) $request->imei ] 
            ]);

            if ($response1->successful() && $response1->json('status') === true) { 
                $json1 = $response1->json('data'); 
                $result1 = $json1['result'] ?? []; 
                $statusKey = key($result1) ?? 'UNKNOWN'; 

                // 2. Jurnalisasi Keuangan untuk IMEI Pertama
                DB::transaction(function () use ($wallet, $fee, $user, $request, $json1, $statusKey) {
                    $wallet->update(['balance' => $wallet->balance - $fee]);
                    Transaction::create([
                        'invoice_number' => 'FEESL-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3)), 
                        'amount'         => $fee,
                        'status'         => 'SUCCESS',
                        'user_id'        => $user->id,
                        'payable_type'   => Wallet::class,
                        'payable_id'     => $wallet->id,
                        'description'    => 'Pengecekan Jaringan SIM Lock IMEI 1: ' . $request->imei . ' (Order ID: ' . ($json1['order_id'] ?? 'N/A') . ')',
                        'metadata'       => [
                            'ceirku_order_id' => $json1['order_id'] ?? 'N/A',
                            'ceirku_result'   => $statusKey,
                        ]
                    ]);
                });

                // 3. LOGIKA PIPELINE ANTREAN DUAL SIM: Jika IMEI 2 diinput, langsung picu request kedua
                if ($request->filled('imei2')) {
                    if ($wallet->fresh()->balance < $fee) {
                        return back()->with('flash', [
                            'success_trigger' => true,
                            'sim_lock_details' => [
                                'imei' => $request->imei,
                                'status' => $statusKey,
                                'message' => 'IMEI 1 sukses, namun saldo tidak cukup untuk memicu otomatisasi IMEI 2.'
                            ]
                        ]);
                    }

                    $response2 = Http::withHeaders([
                        'X-Api-Key'    => $apiKey, 
                        'Content-Type' => 'application/json', 
                    ])->timeout(12)->post($apiUrl, [
                        'service_id' => $serviceId, 
                        'imeis'      => [ (string) $request->imei2 ] 
                    ]);

                    if ($response2->successful() && $response2->json('status') === true) {
                        $json2 = $response2->json('data');
                        $statusKey2 = key($json2['result'] ?? []) ?? 'UNKNOWN';

                        DB::transaction(function () use ($wallet, $fee, $user, $request, $json2, $statusKey2) {
                            $wallet->update(['balance' => $wallet->balance - $fee]);
                            Transaction::create([
                                'invoice_number' => 'FEESL-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3)), 
                                'amount'         => $fee,
                                'status'         => 'SUCCESS',
                                'user_id'        => $user->id,
                                'payable_type'   => Wallet::class,
                                'payable_id'     => $wallet->id,
                                'description'    => 'Pengecekan Otomatis SIM Lock IMEI 2 Sekunder: ' . $request->imei2,
                                'metadata'       => [
                                    'ceirku_order_id' => $json2['order_id'] ?? 'N/A',
                                    'ceirku_result'   => $statusKey2,
                                ]
                            ]);
                        });

                        return back()->with('flash', [
                            'success_trigger' => true,
                            'sim_lock_details' => [
                                'imei'     => $request->imei . ' / ' . $request->imei2,
                                'status'   => "SIM1: {$statusKey} | SIM2: {$statusKey2}", 
                                'message'  => 'Mekanisme dual-channel sinkronisasi berantai sukses diproses.' 
                            ]
                        ]);
                    }
                }

                return back()->with('flash', [
                    'success_trigger' => true,
                    'sim_lock_details' => [
                        'imei'     => $request->imei,
                        'order_id' => $json1['order_id'] ?? 'N/A', 
                        'status'   => $statusKey, 
                        'message'  => $response1->json('message') ?? 'Pengecekan selesai.' 
                    ]
                ]);
            }

            $errMessage = $response1->json('message') ?? 'Server CEIRKU Pusat menolak memproses payload.'; 
            return back()->withEdges(['message' => 'Layanan sedang maintenance sementara. Detail: ' . $errMessage]);

        } catch (\Exception $e) {
            Log::error('ROMEI SERVICE ERROR - Cek SIM Lock Gagal: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Gagal terhubung ke server CEIRKU pusat.']);
        }
    }

    /**
     * UTILITAS API: Mengecek Log Riwayat Sinkronisasi database CEIR Pusat (Service ID 23 / 31)
     * HARGA DINAMIS: Mengambil nilai biaya penelusuran histori dari database (key: fee_check_ceir_history)
     */
    public function checkCeirHistory(Request $request)
    {
        $request->validate([
            'imei' => 'required|digits:15'
        ]);

        try {
            $user = auth()->user();
            $wallet = $user->wallet;
            
            // Membaca pengaturan harga dinamis tracing history dari panel admin
            $fee = (int) Setting::get('fee_check_ceir_history', 7500);

            if (!$wallet || $wallet->balance < $fee) {
                return back()->withErrors(['message' => 'Saldo Wallet ROMEI Anda tidak mencukupi untuk melakukan tracing history.']);
            }

            $mode   = Setting::get('ceirku_mode', 'sandbox'); 
            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY'); 
            $apiUrl = 'https://ceirku.net/api/v1/order'; 

            $serviceId = ($mode === 'live') ? 31 : 23; 

            $response = Http::withHeaders([
                'X-Api-Key'    => $apiKey, 
                'Content-Type' => 'application/json', 
                'Accept'       => 'application/json',
            ])->timeout(12)->post($apiUrl, [
                'service_id' => $serviceId, 
                'imeis'      => [ $request->imei ] 
            ]);

            if ($response->successful() && $response->json('status') === true) { 
                $json = $response->json('data'); 
                $resultData = $json['result'][0] ?? []; 
                $historyLogs = $resultData['history'] ?? []; 

                DB::transaction(function () use ($wallet, $fee, $user, $request, $json, $historyLogs) {
                    $wallet->update(['balance' => $wallet->balance - $fee]);
                    
                    Transaction::create([
                        'invoice_number' => 'FEEHST-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3)), 
                        'amount'         => $fee,
                        'status'         => 'SUCCESS',
                        'user_id'        => $user->id,
                        'payable_type'   => Wallet::class,
                        'payable_id'     => $wallet->id,
                        'description'    => 'Pengecekan mandiri riwayat sinkronisasi database CEIR IMEI: ' . $request->imei,
                        'metadata'       => [
                            'ceirku_order_id'    => $json['order_id'] ?? 'N/A',
                            'ceirku_status'      => 'SUCCESS',
                            'ceirku_result'      => $historyLogs,
                            'instant_check'      => true,
                            'webhook_updated_at' => now()->toDateTimeString()
                        ]
                    ]);
                });

                return back()->with('flash', [
                    'success_trigger' => true,
                    'ceir_history_details' => [
                        'imei'     => $request->imei,
                        'order_id' => $json['order_id'] ?? 'N/A', 
                        'logs'     => $historyLogs, 
                        'message'  => $response->json('message') ?? 'Log sinkronisasi berhasil ditarik.' 
                    ]
                ]);
            }

            $errMessage = $response->json('message') ?? 'Server CEIRKU Pusat menolak memproses tracking.'; 
            return back()->withErrors(['message' => 'Layanan sedang maintenance sementara. Detail: ' . $errMessage]);

        } catch (\Exception $e) {
            Log::error('ROMEI SERVICE ERROR - Cek Histori CEIR Gagal: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Gagal memanggil basis data log CEIRKU pusat.']);
        }
    }

    /**
     * UTILITAS API KOMERSIAL: Eksekusi Pendaftaran Add Roamer Resmi 3 Bulan (Service ID 24)
     * HARGA DINAMIS: Mengambil nilai harga yang diatur Admin secara dinamis dari tabel settings database
     */
    public function registerRoamer3Months(Request $request)
    {
        $request->validate([
            'imei' => 'required|digits:15'
        ]);

        try {
            $user = auth()->user();
            $wallet = $user->wallet;

            // HARGA DINAMIS MUTLAK: Mengambil nilai kolom value dari tabel settings berdasarkan key 'fee_add_roamer_3m'
            $fee = (int) Setting::get('fee_add_roamer_3m', 0); 

            if (!$wallet || $wallet->balance < $fee) {
                return back()->withErrors(['message' => 'Saldo Wallet ROMEI Anda tidak mencukupi untuk mengaktifkan paket Roamer 3 Bulan.']);
            }

            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY'); 
            $apiUrl = 'https://ceirku.net/api/v1/roamer/add'; 

            $response = Http::withHeaders([
                'X-Api-Key'    => $apiKey, 
                'Content-Type' => 'application/json', 
                'Accept'       => 'application/json',
            ])->timeout(15)->post($apiUrl, [
                'imei'   => (string) $request->imei, 
                'months' => 3 
            ]);

            if ($response->successful() && $response->json('status') === true) { 
                $json = $response->json('data'); 

                DB::transaction(function () use ($wallet, $fee, $user, $request, $json) {
                    $wallet->update(['balance' => $wallet->balance - $fee]);

                    Transaction::create([
                        'invoice_number' => 'ROAM3M-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3)), 
                        'amount'         => $fee,
                        'status'         => 'SUCCESS',
                        'user_id'        => $user->id,
                        'payable_type'   => Wallet::class,
                        'payable_id'     => $wallet->id,
                        'description'    => 'Aktivasi Paket Jaringan Add Roamer 3 Bulan untuk IMEI: ' . $request->imei . ' (Order ID: ' . ($json['order_id'] ?? 'N/A') . ')',
                        'metadata'       => [
                            'ceirku_order_id' => $json['order_id'] ?? 'N/A',
                            'ceirku_status'   => 'PROCESSING',
                            'ceirku_result'   => 'Menunggu proses aktivasi operator pusat.',
                        ]
                    ]);
                });

                return back()->with('flash', [
                    'success_trigger' => true,
                    'roamer_details' => [
                        'imei'          => $json['imei'] ?? $request->imei, 
                        'order_id'      => $json['order_id'] ?? 'N/A', 
                        'cost_pusat'    => $json['cost'] ?? 75000, 
                        'order_status'  => $json['order_status'] ?? 'Pending', 
                        'message'       => $response->json('message') ?? 'Pesanan Roamer berhasil dibuat.' 
                    ]
                ]);
            }

            $errMessage = $response->json('message') ?? 'Server Jaringan Pusat menolak registrasi roamer.'; 
            return back()->withErrors(['message' => 'Gagal mengaktifkan paket roamer: ' . $errMessage]);

        } catch (\Exception $e) {
            Log::error('ROMEI SERVICE ERROR - Add Roamer 3M Gagal: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Sistem gagal mendaftarkan Roamer ke server pusat.']);
        }
    }

    /**
     * Fitur Keuangan: Menangani eksekusi pembuatan invoice top-up saldo E-Wallet
     */
    public function storeDeposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1', 
        ]);

        try {
            $user = auth()->user();
            $wallet = $user->wallet;
            if (!$wallet) {
                $wallet = $user->wallet()->create(['balance' => 0]);
            }

            $invoiceId = 'INV-ROMEI-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(4));

            $transaction = Transaction::create([
                'invoice_number' => $invoiceId, 
                'amount'         => (int) $request->amount,
                'status'         => 'PENDING', 
                'user_id'        => $user->id,
                'payable_type'   => Wallet::class, 
                'payable_id'     => $wallet->id,
            ]);

            $dokuService = new \App\Services\Payment\DokuService();
            
            $mockTx = new \stdClass();
            $mockTx->id = $transaction->id;
            $mockTx->amount = (int) $transaction->amount;
            $mockTx->invoice_number = $invoiceId; 
            $mockTx->user = $user;

            $paymentUrl = $dokuService->generateQris($mockTx);

            if ($paymentUrl) {
                Cache::put('payment_status_' . $invoiceId, 'PENDING', 600);
                return response()->json([
                    'status'             => 'success',
                    'invoice_number'     => $invoiceId,
                    'transaction_number' => $invoiceId,
                    'payment_url'        => $paymentUrl 
                ]);
            }

            throw new \Exception('DOKU Live Payment Gateway menolak pembuatan invoice payload.');

        } catch (\Exception $e) {
            Log::error('ROMEI WALLET ERROR - Gagal memproses data top-up: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Sistem gagal memproses data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Polling Realtime Status Pembayaran E-Wallet ROMEI & Jurnaling Kredit Saldo
     */
    public function checkStatus($invoiceId)
    {
        $cacheStatus = Cache::get('payment_status_' . $invoiceId, 'PENDING');

        if (strtoupper($cacheStatus) === 'SUCCESS') {
            try {
                $transaction = Transaction::where('invoice_number', $invoiceId)->first();

                if ($transaction && $transaction->status !== 'SUCCESS' && $transaction->payable_type === Wallet::class) {
                    DB::transaction(function () use ($transaction) {
                        $wallet = Wallet::where('id', $transaction->payable_id)->lockForUpdate()->first();
                        if ($wallet) {
                            $wallet->update(['balance' => $wallet->balance + $transaction->amount]);
                            $transaction->update(['status' => 'SUCCESS']);
                        }
                    });
                }
            } catch (\Exception $e) {
                Log::error("ROMEI FINANSIAL ERROR - Gagal mengkreditkan saldo otomatis: " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => 'Gagal jurnaling saldo'], 500);
            }

            return response()->json(['status' => 'success', 'payment_status' => 'SUCCESS']);
        }

        return response()->json(['status' => 'pending', 'payment_status' => 'PENDING']);
    }

    /**
     * Fitur Cetak Bukti Registrasi Resmi PDF.
     */
    public function downloadPdf($id)
    {
        $registration = ImeiRegistration::with(['package', 'transactions' => function ($query) {
            $query->where('status', 'SUCCESS');
        }])->findOrFail($id);

        if ($registration->user_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh dokumen ini.');
        }

        if ($registration->transactions->isEmpty()) {
            return back()->withErrors(['message' => 'Dokumen sertifikat belum tersedia. Silakan selesaikan pembayaran QRIS Anda terlebih dahulu.']);
        }

        $transaction = $registration->transactions->first();
        $user = auth()->user();

        $pdfData = [
            'registration_number' => $registration->registration_number,
            'invoice_number'      => $transaction->invoice_number, 
            'created_at'          => $registration->created_at->format('d F Y H:i'),
            'name'                => $user->name,
            'email'               => $user->email,
            'whatsapp_number'     => $user->whatsapp_number,
            'imei1'               => $registration->imei1,
            'user_id'             => $user->id,
            'imei2'               => $registration->imei2,
            'sim_type'            => $registration->sim_type === 'dual' ? 'Dual SIM' : 'Single SIM',
            'package_name'        => $registration->package->name,
            'duration_days'       => $registration->package->duration_days,
            'amount'              => $transaction->amount,
        ];

        $pdf = Pdf::loadView('pdf.registration_receipt', $pdfData)->setPaper('a4', 'portrait')->setWarnings(false);
        return $pdf->download('ROMEI-Sertifikat-' . $registration->registration_number . '.pdf');
    }
}