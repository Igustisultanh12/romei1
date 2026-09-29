<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string|null $invoice_number
 * @property string $type               // 'topup' (Deposit), 'pembayaran' (Bayar IMEI), dll
 * @property float $amount
 * @property float $balance_before
 * @property float $balance_after
 * @property string|null $description
 * @property string|null $reference_id   // Menyimpan ID Referensi unik dari DOKU (original_request_id / approval_code)
 * @property string|null $status         // 'PENDING', 'SUCCESS', 'FAILED'
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * * @property-read \App\Models\Wallet $wallet
 */
class WalletTransaction extends Model
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal (Mass Assignment).
     * FIXED: Membuka gerbang izin masuk untuk kolom audit trail saldo keuangan (balance_before & balance_after)
     */
    protected $fillable = [
        'wallet_id',
        'invoice_number',
        'type',
        'amount',
        'balance_before', // Ditambahkan agar lolos Mass Assignment Laravel
        'balance_after',  // Ditambahkan agar lolos Mass Assignment Laravel
        'description',
        'reference_id',   // Ditambahkan untuk menyimpan data callback unik dari DOKU
        'status',
    ];

    /**
     * Konversi tipe data otomatis (Casting).
     * FIXED: Menambahkan casting berpresisi decimal untuk pelaporan saldo sebelum dan sesudah transaksi
     */
    protected $casts = [
        'amount'         => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    /**
     * Relasi balik ke model Wallet (Satu transaksi dimiliki oleh satu Dompet).
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Scope Helper: Mempermudah filter data mutasi yang sukses saja di Dashboard.
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'SUCCESS');
    }

    /**
     * Scope Helper: Mempermudah filter data bertipe masuk (Top Up / Deposit).
     * FIXED: Menyelaraskan dengan string 'topup' yang dikirim oleh method deposit() model Wallet
     */
    public function scopeDeposits($query)
    {
        return $query->where('type', 'topup');
    }

    /**
     * Scope Helper: Mempermudah filter data bertipe keluar (Withdrawal / Pembayaran).
     */
    public function scopeWithdrawals($query)
    {
        return $query->where('type', 'withdraw');
    }
}