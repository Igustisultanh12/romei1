<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ImeiRegistration extends Model
{
    protected $fillable = [
        'user_id',
        'registration_number',
        'sim_type',
        'imei1',
        'imei2',
        'package_id',
        'voucher_id',
        'status',
    ];

    /**
     * Relasi ke Pelanggan yang mengajukan registrasi
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Paket durasi aktif jaringan yang dipilih
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Relasi ke Voucher diskon yang digunakan saat checkout
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Relasi Polymorphic ke data transaksi pembayaran/invoice
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'payable');
    }
}