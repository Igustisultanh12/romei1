<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal.
     */
    protected $fillable = [
        'user_id',
        'transaction_number',
        'total_amount',
        'status',
        'payment_method',
        // Tambahkan kolom lain sesuai struktur tabel database mas
    ];

    /**
     * RELASI: Menghubungkan transaksi dengan pemilik akun (User).
     * Ini yang dipakai oleh DokuService untuk mengambil $sale->user->name
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Contoh relasi lain jika diperlukan
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}