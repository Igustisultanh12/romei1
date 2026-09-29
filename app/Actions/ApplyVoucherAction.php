<?php

namespace App\Actions;

use App\Models\Voucher;
use Carbon\Carbon;

class ApplyVoucherAction {
    /**
     * Memvalidasi voucher dan menghitung nominal potongan harga.
     */
    public function execute(string $code, float $originalPrice): array {
        $voucher = Voucher::where('code', strtoupper($code))
            ->where('is_active', true)
            ->first();

        // 1. Validasi Keberadaan & Masa Aktif
        if (!$voucher) {
            throw new \Exception('Kode voucher tidak valid atau sudah tidak aktif.');
        }

        $now = Carbon::now();
        if (($voucher->starts_at && $now->lessThan($voucher->starts_at)) || 
            ($voucher->expires_at && $now->greaterThan($voucher->expires_at))) {
            throw new \Exception('Masa berlaku kode voucher ini telah habis.');
        }

        // 2. Validasi Kuota Kupon
        if ($voucher->used_count >= $voucher->usage_limit) {
            throw new \Exception('Kuota penggunaan kode voucher ini telah habis.');
        }

        // 3. Kalkulasi Nominal Potongan
        $discountAmount = 0;

        if ($voucher->type === 'fixed') {
            $discountAmount = $voucher->reward_value;
        } elseif ($voucher->type === 'percentage') {
            $discountAmount = ($originalPrice * $voucher->reward_value) / 100;
            
            // Batasi diskon jika ada rule max_discount_limit
            if ($voucher->max_discount_limit && $discountAmount > $voucher->max_discount_limit) {
                $discountAmount = $voucher->max_discount_limit;
            }
        }

        // Pastikan nilai diskon tidak melebihi harga produk utama
        if ($discountAmount > $originalPrice) {
            $discountAmount = $originalPrice;
        }

        $finalPrice = $originalPrice - $discountAmount;

        return [
            'voucher_id' => $voucher->id,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice
        ];
    }
}