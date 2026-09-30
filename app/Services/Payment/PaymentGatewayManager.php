<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class PaymentGatewayManager
{
    /**
     * Dapatkan nama provider payment gateway yang sedang aktif ('doku' atau 'qrqu')
     */
    public static function getActiveProvider(): string
    {
        $val = Setting::get('payment_gateway_provider');
        if (empty($val)) {
            $val = Setting::get('active_payment_gateway');
        }
        $provider = strtolower(trim((string) ($val ?: 'doku')));
        return in_array($provider, ['doku', 'qrqu'], true) ? $provider : 'doku';
    }

    /**
     * Cek apakah DOKU yang sedang aktif
     */
    public static function isDoku(): bool
    {
        return self::getActiveProvider() === 'doku';
    }

    /**
     * Cek apakah QRqu yang sedang aktif
     */
    public static function isQrqu(): bool
    {
        return self::getActiveProvider() === 'qrqu';
    }

    /**
     * Dapatkan label human-readable dari provider aktif
     */
    public static function getProviderLabel(): string
    {
        return self::isQrqu() ? 'QRqu Payment Gateway' : 'DOKU Payment Gateway';
    }

    /**
     * Eksekusi pembuatan transaksi/invoice melalui gateway yang sedang aktif
     *
     * @param object $transaction Model / Mock object dengan id, amount, invoice_number, user
     * @return array
     */
    public static function createPayment(object $transaction): array
    {
        $provider = self::getActiveProvider();

        if ($provider === 'qrqu') {
            $qrquService = new QrquService();
            $result = $qrquService->generateInvoice($transaction);

            if (($result['status'] ?? '') === 'success') {
                return $result;
            }

            throw new \Exception($result['message'] ?? 'Gateway QRqu menolak pembuatan invoice payload.');
        }

        // Default: DOKU
        $dokuService = new DokuService();
        $paymentUrl = $dokuService->generateQris($transaction);

        if ($paymentUrl) {
            $invoiceNumber = (string) ($transaction->invoice_number ?? '');
            return [
                'status'             => 'success',
                'provider'           => 'doku',
                'payment_url'        => $paymentUrl,
                'qr_string'          => null,
                'invoice_id'         => $invoiceNumber,
                'invoice_number'     => $invoiceNumber,
                'transaction_number' => $invoiceNumber,
            ];
        }

        throw new \Exception('DOKU Live Payment Gateway menolak pembuatan invoice payload.');
    }
}
