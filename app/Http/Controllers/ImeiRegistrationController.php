<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImeiRequest;
use App\Actions\RegisterImeiAction;
use App\DTOs\ImeiRegistrationDTO;
use App\Models\ImeiRegistration;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\WhatsappService2; // Diimpor untuk eksekusi otomatisasi kirim notifikasi WA
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
     * TAMPILAN ADMIN: Mengambil semua antrean registrasi IMEI untuk ROMEI HQ
     */
    public function index()
    {
        $registrations = ImeiRegistration::with(['package', 'user'])->latest()->paginate(10);
        return view('admin.imei-registrations', compact('registrations'));
    }

    /**
     * MANIPULASI STATUS ADMIN: Mengubah status antrean langsung dari tabel admin beserta notifikasi WA
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status'        => 'required|in:pending,proses,selesai,ditolak',
            'catatan_tolak' => 'nullable|required_if:status,ditolak|string|max:255',
        ]);

        try {
            $isRoamer = $request->has('is_roamer') && filter_var($request->is_roamer, FILTER_VALIDATE_BOOLEAN);

            // 1. JALUR DATA ROAMER LAMA
            if ($isRoamer) {
                $transaction = Transaction::with('user')->findOrFail($id);
                $customer = $transaction->user;
                
                if ($request->status === 'ditolak' && $transaction->status !== 'FAILED') {
                    DB::transaction(function () use ($transaction, $request) {
                        $wallet = Wallet::where('id', $transaction->payable_id)->lockForUpdate()->first();
                        if ($wallet) {
                            $wallet->update(['balance' => $wallet->balance + $transaction->amount]);
                        }
                        
                        $metadata = is_string($transaction->metadata) ? json_decode($transaction->metadata, true) : ($transaction->metadata ?? []);
                        $metadata['ceirku_status'] = 'FAILED';
                        $metadata['ceirku_result'] = $request->catatan_tolak ?? 'Pendaftaran ditolak oleh admin panel pusat.';

                        $transaction->update([
                            'status' => 'FAILED',
                            'metadata' => $metadata
                        ]);
                    });

                    // NOTIFIKASI WA: Pelanggan Ditolak (Roamer)
                    if ($customer && $customer->whatsapp_number) {
                        $pesanTolak = "[DITOLAK] *Pendaftaran Roamer Ditolak*\n\nHalo {$customer->name},\n\nPermohonan paket Roamer Anda dengan Invoice *{$transaction->invoice_number}* ditolak oleh Admin.\n\n*Alasan:* " . ($request->catatan_tolak ?? '-') . "\n\nSaldo Wallet sebesar *Rp " . number_format($transaction->amount, 0, ',', '.') . "* telah di-refund otomatis kembali ke akun Anda.";
                        WhatsappService2::sendMessage($customer->whatsapp_number, $pesanTolak);
                    }

                    return redirect()->back()->with('success', 'Transaksi roamer ditolak dan saldo berhasil dikembalikan ke user!');
                }
                
                $financialStatus = 'PENDING';
                if ($request->status === 'selesai' || $request->status === 'proses') {
                    $financialStatus = 'SUCCESS';
                }

                $metadata = is_string($transaction->metadata) ? json_decode($transaction->metadata, true) : ($transaction->metadata ?? []);
                $metadata['ceirku_status'] = ($request->status === 'selesai') ? 'SUCCESS' : 'PROCESSING';
                $metadata['ceirku_result'] = ($request->status === 'selesai') ? 'Jaringan aktif resmi.' : 'Menunggu proses aktivasi operator pusat.';

                $transaction->update([
                    'status' => $financialStatus,
                    'metadata' => $metadata
                ]);

                // NOTIFIKASI WA: Pelanggan Update Status (Roamer)
                if ($customer && $customer->whatsapp_number) {
                    $statusLabel = $request->status === 'selesai' ? '[SELESAI]' : '[PROSES]';
                    $pesanUpdate = "{$statusLabel} *Update Status Roamer*\n\nHalo {$customer->name},\n\nStatus permohonan Roamer Anda dengan Invoice *{$transaction->invoice_number}* telah diperbarui menjadi: *[" . strtoupper($request->status) . "]*.\n\n*Keterangan:* " . $metadata['ceirku_result'];
                    WhatsappService2::sendMessage($customer->whatsapp_number, $pesanUpdate);
                }

                return redirect()->back()->with('success', 'Status transaksi roamer lama berhasil diperbarui!');
            }

            // 2. JALUR STANDAR REGULER IMEI
            $registration = ImeiRegistration::with('user')->findOrFail($id);
            $customer = $registration->user;
            
            if ($request->status === 'ditolak' && $registration->status !== 'ditolak') {
                $relatedTransaction = Transaction::where('user_id', $registration->user_id)
                    ->where('status', 'SUCCESS')
                    ->where(function($query) use ($registration) {
                        $query->where('description', 'like', '%' . $registration->imei1 . '%')
                              ->orWhere('description', 'like', '%' . ($registration->registration_number ?? 'NOTFOUND') . '%')
                              ->orWhere('invoice_number', 'like', 'ROMEI%');
                    })
                    ->latest()
                    ->first();

                $userId = $registration->user_id;
                $imeiPerangkat = $registration->imei1;
                $hasCatatanKolom = \Schema::hasColumn('imei_registrations', 'catatan_tolak');

                DB::transaction(function () use ($registration, $request, $relatedTransaction, $userId, $imeiPerangkat, $hasCatatanKolom) {
                    $registration->status = 'ditolak';
                    if ($hasCatatanKolom) {
                        $registration->catatan_tolak = $request->catatan_tolak;
                    }
                    $registration->save();

                    if ($relatedTransaction) {
                        $wallet = Wallet::where('id', $relatedTransaction->payable_id)->lockForUpdate()->first();
                        if ($wallet) {
                            $wallet->update(['balance' => $wallet->balance + $relatedTransaction->amount]);
                        }

                        Transaction::create([
                            'invoice_number' => 'REFUND-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3)), 
                            'amount'         => $relatedTransaction->amount,
                            'status'         => 'SUCCESS',
                            'type'           => 'refund',
                            'user_id'        => $userId,
                            'payable_type'   => Wallet::class,
                            'payable_id'     => $relatedTransaction->payable_id,
                            'description'    => 'Refund otomatis penolakan IMEI: ' . $imeiPerangkat . '. Alasan: ' . $request->catatan_tolak,
                        ]);
                    }
                });

                // NOTIFIKASI WA: Pelanggan Ditolak & Refund (Reguler)
                if ($customer && $customer->whatsapp_number) {
                    $pesanTolakReg = "[DITOLAK] *Pengajuan IMEI Ditolak & Refund*\n\nHalo {$customer->name},\n\nRegistrasi IMEI dengan No. Registrasi *{$registration->registration_number}* ditolak oleh ROMEI HQ.\n\n*IMEI 1:* {$registration->imei1}\n*Alasan:* " . ($request->catatan_tolak ?? '-') . "\n\nDana Anda telah dikembalikan penuh ke Wallet digital.";
                    WhatsappService2::sendMessage($customer->whatsapp_number, $pesanTolakReg);
                }

                return redirect()->back()->with('success', 'Registrasi IMEI berhasil ditolak dan saldo otomatis di-refund penuh!');
            }

            // Update status non-ditolak (pending, proses, selesai)
            $registration->status = $request->status;
            $registration->save();

            // NOTIFIKASI WA: Pelanggan Update Status Berhasil/Proses (Reguler)
            if ($customer && $customer->whatsapp_number) {
                $statusLabelReg = $request->status === 'selesai' ? '[SELESAI]' : '[PROSES]';
                $pesanUpdateReg = "{$statusLabelReg} *Perubahan Status Antrean IMEI*\n\nHalo {$customer->name},\n\nPengajuan IMEI Anda dengan nomor *{$registration->registration_number}* telah diperbarui menjadi: *[" . strtoupper($request->status) . "]*.\n\n*IMEI 1:* {$registration->imei1}\nTerima kasih telah memercayai layanan ROMEI platform.";
                WhatsappService2::sendMessage($customer->whatsapp_number, $pesanUpdateReg);
            }

            return redirect()->back()->with('success', 'Status antrean registrasi IMEI berhasil diubah menjadi ' . strtoupper($request->status));
        } catch (\Exception $e) {
            Log::error('ROMEI ADMIN ERROR - Gagal update status ID ' . $id . ': ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal memperbarui status: ' . $e->getMessage());
        }
    }

    /**
     * LANGSUNG JALUR UTAMA: Menyimpan data registrasi gawai via Action Class & DTO 
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
            
            $singlePrice = 0; 
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

            // PIPELINE IMEI 1
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

            // PIPELINE IMEI 2
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

            // DB Execution
            $registration = DB::transaction(function () use ($request, $action, $wallet, $totalRequiredFee, $user, $orderId1, $orderId2) {
                $wallet->update(['balance' => $wallet->balance - $totalRequiredFee]);

                $dto = new ImeiRegistrationDTO(
                    $request->imei1,
                    $request->imei2,
                    $request->sim_type,
                    $request->package_id,
                    $user->id,
                    $request->voucher_code
                );
                
                $regData = $action->execute($dto);

                Transaction::create([
                    'invoice_number' => 'ROMEI-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3)), 
                    'amount'         => $totalRequiredFee,
                    'status'         => 'SUCCESS',
                    'user_id'        => $user->id,
                    'payable_type'   => Wallet::class,
                    'payable_id'     => $wallet->id,
                    'description'    => 'Aktivasi Paket Jaringan Instan via Wallet Berantai. IMEI: ' . $request->imei1 . ($request->imei2 ? ' / ' . $request->imei2 : '') . ' | ID-1: ' . $orderId1 . ($orderId2 ? ' | ID-2: ' . $orderId2 : ''),
                ]);

                return $regData;
            });

            // NOTIFIKASI 1: Otomatis ke Pelanggan (Status: Pending)
            if ($user->whatsapp_number) {
                $pesanPelanggan = "[STATUS] *Pesanan IMEI Diterima*\n\nHalo *{$user->name}*,\n\nTerima kasih, permohonan sinkronisasi IMEI perangkat Anda telah masuk ke dalam antrean sistem ROMEI HQ.\n\n*No. Registrasi:* " . ($registration->registration_number ?? '-') . "\n*IMEI 1:* {$request->imei1}\n" . ($request->imei2 ? "*IMEI 2:* {$request->imei2}\n" : "") . "*Tipe SIM:* " . strtoupper($request->sim_type) . "\n*Status:* [PENDING]\n\nMohon ditunggu, Admin ROMEI akan segera meninjau dan melakukan aktivasi jaringan Anda.";
                WhatsappService2::sendMessage($user->whatsapp_number, $pesanPelanggan);
            }

            // NOTIFIKASI 2: Otomatis Tembak ke Admin WhatsApp HQ
            $adminPhone = Setting::get('admin_whatsapp_notification', '62816500104');
            if ($adminPhone) {
                $pesanAdmin = "[NOTIFIKASI] *Pemberitahuan Antrean IMEI Baru ROMEI_HQ*\n\nAda permohonan registrasi IMEI baru masuk yang perlu segera dikonfirmasi:\n\n*Nama Pelanggan:* {$user->name}\n*No. Registrasi:* " . ($registration->registration_number ?? '-') . "\n*IMEI 1:* {$request->imei1}\n*Tipe SIM:* " . strtoupper($request->sim_type) . "\n\nSilakan buka Dashboard Admin ROMEI untuk memproses permohonan ini.";
                WhatsappService2::sendMessage($adminPhone, $pesanAdmin);
            }

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
     * UTILITAS API: Mengecek Status Layanan SIM LOCK / Roamer Status
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
            $fee = (int) Setting::get('fee_check_sim_lock', 5000);

            if (!$wallet || $wallet->balance < $fee) {
                return back()->withErrors(['message' => 'Saldo Wallet ROMEI Anda tidak mencukupi. Silakan lakukan Top Up terlebih dahulu.']);
            }

            $mode   = Setting::get('ceirku_mode', 'sandbox'); 
            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY'); 
            $apiUrl = \App\Services\CeirkuService::getOrderUrl(); 

            $serviceId = ($mode === 'live') ? 30 : 20; 

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

                $statusKey2 = "N/A";

                if ($request->filled('imei2')) {
                    if ($wallet->fresh()->balance < $fee) {
                        if ($user->whatsapp_number) {
                            $pesanWaSim1 = "[LAPORAN] *Hasil Cek SIM Lock Perangkat*\n\nHalo {$user->name},\nBerikut hasil tracing instan jaringan Anda:\n\n*IMEI 1:* {$request->imei}\n*Status SIM 1:* {$statusKey}\n\n_Catatan: Pengecekan IMEI 2 terhenti karena saldo kurang._";
                            WhatsappService2::sendMessage($user->whatsapp_number, $pesanWaSim1);
                        }

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
                    }
                }

                // -----------------------------------------------------------------
                // FORMAT TABEL VERTIKAL MONOSPACE: HASIL LAPORAN SIM LOCK WA
                // -----------------------------------------------------------------
                if ($user->whatsapp_number) {
                    $imei1Clean = str_pad(substr($request->imei, 0, 15), 15, " ");
                    $imei2Clean = $request->filled('imei2') ? str_pad(substr($request->imei2, 0, 15), 15, " ") : str_pad("N/A", 15, " ");
                    $status1Clean = str_pad(substr($statusKey, 0, 12), 12, " ");
                    $status2Clean = str_pad(substr($statusKey2, 0, 12), 12, " ");

                    $tableSimLock = "[LAPORAN] *PENGECEKAN SIM LOCK ROMEI*\n\n" .
                                    "Halo {$user->name}, berikut rincian status deteksi jaringan gawai Anda:\n\n" .
                                    "```" .
                                    "┌─────────────────┬──────────────┐\n" .
                                    "│ IMEI PERANGKAT  │ STATUS LOCK  │\n" .
                                    "├─────────────────┼──────────────┤\n" .
                                    "│ {$imei1Clean} │ {$status1Clean} │\n";
                    if ($request->filled('imei2')) {
                        $tableSimLock .= "│ {$imei2Clean} │ {$status2Clean} │\n";
                    }
                    $tableSimLock .= "└─────────────────┴──────────────┘" .
                                    "```\n" .
                                    "*Waktu Analisis:* " . now()->format('d/m/Y H:i') . " WIB\n" .
                                    "_Data sinkronisasi realtime via Central Gate ROMEI HQ._";

                    WhatsappService2::sendMessage($user->whatsapp_number, $tableSimLock);
                }

                return back()->with('flash', [
                    'success_trigger' => true,
                    'sim_lock_details' => [
                        'imei'     => $request->imei . ($request->filled('imei2') ? ' / ' . $request->imei2 : ''),
                        'status'   => "SIM 1: {$statusKey}" . ($request->filled('imei2') ? " | SIM 2: {$statusKey2}" : ""), 
                        'message'  => 'Mekanisme dual-channel sinkronisasi berantai sukses diproses.' 
                    ]
                ]);
            }

            $errMessage = $response1->json('message') ?? 'Server CEIRKU Pusat menolak memproses payload.'; 
            return back()->withErrors(['message' => 'Layanan sedang maintenance sementara. Detail: ' . $errMessage]);

        } catch (\Exception $e) {
            Log::error('ROMEI SERVICE ERROR - Cek SIM Lock Gagal: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Gagal terhubung ke server CEIRKU pusat.']);
        }
    }

    /**
     * UTILITAS API: Mengecek Log Riwayat Sinkronisasi database CEIR Pusat
     */
    public function checkCeirHistory(Request $request)
    {
        $request->validate([
            'imei' => 'required|digits:15'
        ]);

        try {
            $user = auth()->user();
            $wallet = $user->wallet;
            $fee = (int) Setting::get('fee_check_ceir_history', 7500);

            if (!$wallet || $wallet->balance < $fee) {
                return back()->withErrors(['message' => 'Saldo Wallet ROMEI Anda tidak mencukupi untuk melakukan pengecekan history.']);
            }

            $mode   = Setting::get('ceirku_mode', 'sandbox'); 
            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY'); 
            $apiUrl = \App\Services\CeirkuService::getOrderUrl(); 

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

                // -----------------------------------------------------------------
                // FORMAT TABEL VERTIKAL MONOSPACE: HASIL HISTORI CEIR KEMENPERIN
                // -----------------------------------------------------------------
                if ($user->whatsapp_number) {
                    $tableHistory = "[LAPORAN] *TRACING HISTORI DATABASE CEIR PUSAT*\n\n" .
                                    "Berikut rincian riwayat log status sinkronisasi IMEI *{$request->imei}*:\n\n" .
                                    "```" .
                                    "┌────┬──────────────┬──────────────────┐\n" .
                                    "│ NO │ STATUS REGS  │ WAKTU SINKRON    │\n" .
                                    "├────┼──────────────┼──────────────────┤\n";

                    if (!empty($historyLogs) && is_array($historyLogs)) {
                        foreach ($historyLogs as $index => $log) {
                            $noClean = str_pad($index + 1, 2, " ");
                            $statusClean = str_pad(substr($log['status'] ?? '-', 0, 12), 12, " ");
                            $timeClean = str_pad(substr($log['time'] ?? '-', 0, 16), 16, " ");
                            
                            $tableHistory .= "│ {$noClean} │ {$statusClean} │ {$timeClean} │\n";
                        }
                    } else {
                        $tableHistory .= "│ -- │ NO LOG TRACE │ DATA KOSONG      │\n";
                    }

                    $tableHistory .= "└────┴──────────────┴──────────────────┘" .
                                     "```\n" .
                                     "*Waktu Tracing:* " . now()->format('d/m/Y H:i') . " WIB\n" .
                                     "_Laporan resmi diterbitkan oleh ROMEI Operating System._";

                    WhatsappService2::sendMessage($user->whatsapp_number, $tableHistory);
                }

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
     * UTILITAS API KOMERSIAL: Eksekusi Pendaftaran Add Roamer Resmi 3 Bulan
     */
    public function registerRoamer3Months(Request $request)
    {
        $request->validate([
            'imei' => 'required|digits:15'
        ]);

        try {
            $user = auth()->user();
            $wallet = $user->wallet;
            $fee = (int) Setting::get('fee_add_roamer_3m', 0); 

            if (!$wallet || $wallet->balance < $fee) {
                return back()->withErrors(['message' => 'Saldo Wallet ROMEI Anda tidak mencukupi untuk mengaktifkan paket Roamer 3 Bulan.']);
            }

            $apiKey = Setting::get('ceirku_api_key', 'YOUR_API_KEY'); 
            $apiUrl = \App\Services\CeirkuService::getRoamerAddUrl(); 

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