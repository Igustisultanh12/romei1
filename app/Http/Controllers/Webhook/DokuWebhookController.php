<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Jobs\SubmitIMEIRegistrationJob;
use App\Actions\ProcessReferralCommissionAction;
use App\Models\AuditLog;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DokuWebhookController extends Controller {
    
    /**
     * Menangani callback QRIS dari DOKU Payment Gateway
     * MENGIKUTI METODE TAKTIS SIKANDA: Fokus pada Integrasi Data & Idempotency Aman
     */
    public function handleQrisCallback(Request $request) {
        $data = $request->all();
        
        // 1. Audit Trail: Rekam seluruh payload masuk demi transparansi pembukuan
        Log::info('DOKU Webhook Masuk (ROMEI Engine):', $data);

        $invoiceNumber = $data['order']['invoice_number'] ?? null;
        
        // Mengikuti metode SIKANDA: DOKU terkadang memakai 'status', terkadang 'state'
        $transactionStatus = strtoupper($data['transaction']['status'] ?? $data['transaction']['state'] ?? ''); 
        
        // Mengambil ID referensi unik dari DOKU (original_request_id atau approval_code)
        $dokuReference = $data['transaction']['original_request_id'] ?? $data['emoney_payment']['approval_code'] ?? $data['transaction']['id'] ?? 'N/A';

        if (!$invoiceNumber) {
            Log::error('DOKU Webhook Gagal: Invoice tidak ditemukan dalam payload.');
            return response()->json(['message' => 'Invalid Data Request'], 400);
        }

        // 2. CARI TRANSAKSI DI DATABASE ROMEI
        $transaction = Transaction::where('invoice_number', $invoiceNumber)->first();

        // Cadangan taktis: Jika invoice tidak ketemu, coba pecah format string pencocokan
        if (!$transaction) {
            $parts = explode('-', $invoiceNumber);
            if (count($parts) > 1) {
                // Mencari record jika invoice membawa postfix random token generator
                $transaction = Transaction::where('invoice_number', 'like', $parts[0] . '-' . $parts[1] . '%')->first();
            }
        }

        if (!$transaction) {
            // Cek apakah invoice pengujian / direct monitoring
            if ($transactionStatus === 'SUCCESS') {
                Cache::put('payment_status_' . $invoiceNumber, 'SUCCESS', 600);
                Log::info("DOKU Webhook Info: Status invoice {$invoiceNumber} berhasil dicache sebagai SUCCESS (Direct/Mock).");
                return response()->json(['message' => 'Notification Received and Status Cached Successfully'], 200);
            }

            Log::error("DOKU Webhook Error: Invoice {$invoiceNumber} tidak terdaftar di ROMEI.");
            return response()->json(['message' => 'Transaction/Invoice Not Found'], 404);
        }

        // 3. IDEMPOTENCY CHECK: Jika status di database sudah sukses, langsung kembalikan OK untuk cegah double processing
        if (in_array(strtoupper($transaction->status), ['SUCCESS', 'PAID', 'REFUNDED'])) {
            Log::info("DOKU Webhook Info: Invoice {$invoiceNumber} sudah lunas sebelumnya (Bypass Success).");
            return response()->json(['message' => 'Transaction already processed or finalized'], 200);
        }

        // 4. OPERASI UPDATE STATUS JURNALIS FINANSIAL PLATFORM
        if ($transactionStatus === 'SUCCESS') {
            try {
                DB::transaction(function () use ($transaction, $dokuReference, $invoiceNumber, $data) {
                    // Kunci baris data transaksi (Pessimistic Locking) demi aspek keamanan data
                    $transaction->lockForUpdate();

                    // A. Update status transaksi utama menjadi lunas ('SUCCESS')
                    $transaction->update([
                        'status' => 'SUCCESS',
                        'payment_gateway_ref' => $dokuReference,
                        'paid_at' => now()
                    ]);
                    
                    // B. Ambil & Jalankan otomatisasi model pendukung (Polymorphic: ImeiRegistration atau Wallet)
                    $payable = $transaction->payable; 
                    
                    if ($payable) {
                        // KONDISI A: Jika ini merupakan transaksi pendaftaran IMEI resmi jalur utama
                        if ($transaction->payable_type === \App\Models\ImeiRegistration::class) {
                            $payable->update(['status' => 'processing']);
                            
                            // Integrasi sistem kode kupon kuota voucher
                            if (!empty($payable->voucher_id)) {
                                DB::table('vouchers')
                                    ->where('id', $payable->voucher_id)
                                    ->lockForUpdate()
                                    ->increment('used_count');
                            }
                            
                            // Eksekusi pembagian komisi kemitraan Afiliasi
                            app(ProcessReferralCommissionAction::class)->execute($transaction);

                            // Dorong pendaftaran perangkat asing ke antrean antrean background job Redis
                            SubmitIMEIRegistrationJob::dispatch($payable);
                        } 
                        // KONDISI B: Skenario jika jenis transaksinya adalah Deposit / Top Up Saldo Dompet Digital (INV-ROMEI-)
                        elseif ($transaction->payable_type === \App\Models\Wallet::class && str_starts_with($invoiceNumber, 'INV-ROMEI-')) {
                            $payable->deposit(
                                amount: $transaction->amount,
                                type: 'topup',
                                description: "Top up saldo aman via QRIS DOKU (#{$transaction->invoice_number})",
                                referenceId: $transaction->id
                            );
                            Log::info("DOKU Webhook Wallet Topup: Berhasil kredit saldo untuk invoice {$invoiceNumber}");
                        }
                        // KONDISI C: Skenario jika ini Pembayaran QRIS Langsung Diagnosis Instan (INV-SLOCK- atau INV-FEEHST-)
                        elseif ($transaction->payable_type === \App\Models\Wallet::class && (str_starts_with($invoiceNumber, 'INV-SLOCK-') || str_starts_with($invoiceNumber, 'INV-FEEHST-'))) {
                            Log::info("DOKU Webhook Direct Service Check: Invoice Layanan Diagnosis Instan {$invoiceNumber} dinyatakan Sah & Lunas.");
                        }
                    }

                    // C. Catat Log ke Sistem Global Audit Log ROMEI
                    AuditLog::create([
                        'user_id' => $transaction->user_id,
                        'activity' => 'PAYMENT_SUCCESS',
                        'description' => "Pembayaran sukses terverifikasi untuk Invoice: {$transaction->invoice_number} melalui QRIS DOKU.",
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent()
                    ]);
                });

                // F. Set Cache Status Polling Real-Time agar modal QRIS di frontend Vue pembeli langsung otomatis tertutup sukses
                Cache::put('payment_status_' . $invoiceNumber, 'SUCCESS', 300);

                // G. Kirim Notifikasi Pembayaran Berhasil ke Akun User
                if ($transaction->user) {
                    $transaction->user->notify(new PaymentReceivedNotification($transaction));
                }

                return response()->json(['message' => 'OK'], 200);

            } catch (\Exception $e) {
                Log::critical("DOKU Webhook Runtime Exception ROMEI: " . $e->getMessage(), [
                    'invoice' => $invoiceNumber,
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['message' => 'Internal Server Error during processing'], 500);
            }
        }

        // 5. Penanganan jika Pembayaran Dinyatakan Gagal/Expired dari sistem DOKU
        if (in_array($transactionStatus, ['FAILED', 'EXPIRED', 'CANCEL', 'VOID'])) {
            DB::transaction(function () use ($transaction, $transactionStatus, $invoiceNumber) {
                $transaction->update(['status' => 'FAILED']);
                
                if ($transaction->payable_type === \App\Models\ImeiRegistration::class) {
                    $transaction->payable()->update(['status' => 'rejected']);
                }
            });

            Cache::put('payment_status_' . $invoiceNumber, 'FAILED', 300);
            Log::warning("DOKU Webhook Laporan Gagal: Transaksi {$invoiceNumber} status {$transactionStatus}.");
            return response()->json(['message' => "Payment marked as failed"], 200);
        }

        return response()->json(['message' => 'Unhandled payment status state'], 400);
    }
}