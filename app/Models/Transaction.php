<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    /**
     * Atribut yang dapat diisi secara massal (Mass Assignment Safety)
     */
    protected $fillable = [
        'user_id',
        'invoice_number',      // Kolom pengenal kode unik (ROAM3M-, FEESL-, FEEHST-)
        'payable_type',
        'payable_id',
        'amount',
        'status',
        'payment_gateway_ref', // Referensi ID dari DOKU atau pihak ketiga
        'metadata',            // Tempat menyimpan log payload asinkron dari Webhook CEIRKU
        'description',         // Deskripsi transaksi yang memuat nomor IMEI pelacakan
    ];

    /**
     * Mutator Konversi Tipe Data Otomatis (Casting Layer)
     */
    protected $casts = [
        'amount'   => 'decimal:2',
        'metadata' => 'array', // Mengubah JSON database menjadi Array/Objek Vue secara otomatis
    ];

    /**
     * Relasi Balik ke Pemilik Transaksi (User)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mengenali asal usul tagihan secara dinamis via Polimorfisme (ImeiRegistration atau Wallet)
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}