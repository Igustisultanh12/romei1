<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    // Mengizinkan mass-assignment standar bawaan Laravel
    protected $fillable = ['user_id', 'amount', 'status', 'bank_name', 'account_number', 'account_name'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Relasi balik ke pengguna jika suatu saat dipanggil di dashboard
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}