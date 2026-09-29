<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ImeiRegistration;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Jobs\SubmitIMEIRegistrationJob;
use App\Actions\ProcessReferralCommissionAction;
use App\Notifications\PaymentReceivedNotification;
use App\Services\Payment\QrquService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QrquWebhookController extends Controller
{
    /**
     * Menangani callback webhook asinkron dari QRqu Payment Gateway
     */
    public function handleCallback(Request $request)
    {
        $rawBody = $request->getContent();
        $data = $request->all();

        Log::info('QRqu Webhook Masuk (ROMEI Platform Engine):', [
            'payload' => $data,
            'headers' => [
                'signature' => $request->header('X-QRQU-Signature') ?? $request->header('X-QRQU-SIGNATURE'),
                'event'     => $request->header('X-QRQU-Event') ?? $request->header('X-QRQU-EVENT'),
            ]
        ]);

        // 1. Verifikasi Keaslian Kriptografis Webhook Signature (HMAC-SHA256)
        $signature = $request->header('X-QRQU-Signature') ?? $request->header('X-QRQU-SIGNATURE');
        $webhookSecret = QrquService::getWebhookSecret();

        if (!empty($webhookSecret)) {
            $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);
            if (!hash_equals($expectedSignature, (string) $signature)) {
                Log::warning('QRqu Webhook Ditolak: Tanda tangan HMAC-SHA256 tidak valid.');
                return response()->json(['message' => 'Invalid Webhook Signature'], 401);
            }
        }

        $externalId = $data['external_id'] ?? null;
        $qrquInvoiceId = $data['invoice_id'] ?? null;
        $qrquTransactionId = $data['transaction_id'] ?? $qrquInvoiceId ?? 'N/A';
        $event = $data['event'] ?? $request->header('X-QRQU-Event') ?? '';
        $status = strtoupper($data['status'] ?? '');

        if (!$externalId && !$qrquInvoiceId) {
            Log::error('QRqu Webhook Gagal: Tidak ada identifikasi invoice atau external_id.');
            return response()->json(['message' => 'Missing invoice identification in payload'], 400);
        }

        // 2. Temukan Transaksi di Database ROMEI
        $transaction = null;
        if ($externalId) {
            $transaction = Transaction::where('invoice_number', $externalId)->first();
        }
        if (!$transaction && $qrquInvoiceId) {
            $transaction = Transaction::where('payment_gateway_ref', $qrquInvoiceId)
                ->orWhere('invoice_number', $qrquInvoiceId)
                ->first();
        }

        // Fallback pencocokan prefix jika terdapat postfix token
        if (!$transaction && $externalId) {
            $parts = explode('-', $externalId);
            if (count($parts) > 1) {
                $transaction = Transaction::where('invoice_number', 'like', $parts[0] . '-' . $parts[1] . '%')->first();
            }
        }

        if (!$transaction) {
            Log::error("QRqu Webhook Error: Transaksi dengan referensi '{$externalId}' / '{$qrquInvoiceId}' tidak ditemukan di ROMEI.");
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $invoiceNumber = $transaction->invoice_number;

        // 3. IDEMPOTENCY CHECK: Jika transaksi telah lunas sebelumnya, kembalikan 200 OK langsung
        if (in_array(strtoupper($transaction->status), ['SUCCESS', 'PAID', 'REFUNDED'], true)) {
            Log::info("QRqu Webhook Info: Transaksi {$invoiceNumber} sudah berstatus lunas sebelumnya (Idempotent Bypass).");
            return response()->json(['message' => 'Transaction already processed or finalized'], 200);
        }

        // 4. PENANGANAN STATUS PEMBAYARAN SUKSES / PAID
        if ($event === 'payment.paid' || in_array($status, ['PAID', 'SUCCESS'], true)) {
            try {
                DB::transaction(function () use ($transaction, $qrquTransactionId, $invoiceNumber, $data) {
                    $transaction->lockForUpdate();

                    $transaction->update([
                        'status'              => 'SUCCESS',
                        'payment_gateway_ref' => $qrquTransactionId,
                        'paid_at'             => now(),
                    ]);

                    $payable = $transaction->payable;

                    if ($payable) {
                        // KONDISI A: Pendaftaran Resmi IMEI Jalur Utama
                        if ($transaction->payable_type === ImeiRegistration::class) {
                            $payable->update(['status' => 'processing']);

                            if (!empty($payable->voucher_id)) {
                                DB::table('vouchers')
                                    ->where('id', $payable->voucher_id)
                                    ->lockForUpdate()
                                    ->increment('used_count');
                            }

                            app(ProcessReferralCommissionAction::class)->execute($transaction);
                            SubmitIMEIRegistrationJob::dispatch($payable);
                        }
                        // KONDISI B: Top Up Saldo Dompet Digital (Wallet Deposit)
                        elseif ($transaction->payable_type === Wallet::class && str_starts_with($invoiceNumber, 'INV-ROMEI-')) {
                            $payable->deposit(
                                amount: $transaction->amount,
                                type: 'topup',
                                description: "Top up saldo aman via QRIS QRqu (#{$transaction->invoice_number})",
                                referenceId: $transaction->id
                            );
                            Log::info("QRqu Webhook Wallet: Saldo Rp {$transaction->amount} berhasil dikreditkan untuk invoice {$invoiceNumber}");
                        }
                        // KONDISI C: Direct Service Check (SIM Lock / History)
                        elseif ($transaction->payable_type === Wallet::class) {
                            Log::info("QRqu Webhook Direct Service: Invoice Layanan {$invoiceNumber} dinyatakan Sah & Lunas.");
                        }
                    }

                    AuditLog::create([
                        'user_id'     => $transaction->user_id,
                        'activity'    => 'PAYMENT_SUCCESS',
                        'description' => "Pembayaran sukses terverifikasi untuk Invoice: {$transaction->invoice_number} melalui QRIS QRqu.",
                        'ip_address'  => request()->ip(),
                        'user_agent'  => request()->userAgent(),
                    ]);
                });

                // Update status cache polling Vue
                Cache::put('payment_status_' . $invoiceNumber, 'SUCCESS', 300);
                if ($externalId && $externalId !== $invoiceNumber) {
                    Cache::put('payment_status_' . $externalId, 'SUCCESS', 300);
                }

                // Kirim notifikasi user jika class notifikasi tersedia
                if ($transaction->user && class_exists('App\Notifications\PaymentReceivedNotification')) {
                    $transaction->user->notify(new \App\Notifications\PaymentReceivedNotification($transaction));
                }

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Notification received and transaction completed successfully'
                ], 200);

            } catch (\Throwable $e) {
                Log::critical("QRqu Webhook Processing Exception: " . $e->getMessage(), [
                    'invoice' => $invoiceNumber,
                    'trace'   => $e->getTraceAsString(),
                ]);
                return response()->json(['message' => 'Internal Server Error during processing'], 500);
            }
        }

        // 5. PENANGANAN STATUS PEMBAYARAN GAGAL / EXPIRED
        if (in_array($status, ['FAILED', 'EXPIRED', 'CANCEL', 'VOID'], true)) {
            DB::transaction(function () use ($transaction) {
                $transaction->update(['status' => 'FAILED']);
                if ($transaction->payable_type === ImeiRegistration::class) {
                    $transaction->payable()->update(['status' => 'rejected']);
                }
            });

            Cache::put('payment_status_' . $invoiceNumber, 'FAILED', 300);
            Log::warning("QRqu Webhook: Transaksi {$invoiceNumber} dinyatakan gagal/expired dengan status {$status}.");
            return response()->json(['message' => 'Payment marked as failed'], 200);
        }

        return response()->json(['message' => 'Unhandled payment status state'], 200);
    }
}
