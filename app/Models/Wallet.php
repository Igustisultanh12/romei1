<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property float $balance
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\WalletTransaction[] $transactions
 */
class Wallet extends Model {
    
    /**
     * Atribut yang dapat diisi melalui Mass Assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = ['user_id', 'balance'];

    /**
     * Relasi ke model User (Pemilik Wallet)
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke history transaksi log (WalletTransaction)
     */
    public function transactions(): HasMany {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Menambahkan Saldo ke Wallet (Deposit / Refund / Komisi)
     * Kunci Utama penanganan Webhook QRIS DOKU Lunas
     */
    public function deposit(float $amount, string $type, string $description = null, string $referenceId = null): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal harus lebih besar dari nol.');
        }

        $balanceBefore = $this->balance;
        $this->balance += $amount;
        $this->save();

        // Mengirimkan data mutasi lengkap ke tabel transaksi pendukung
        $this->transactions()->create([
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $this->balance,
            'description'    => $description,
            'reference_id'   => $referenceId
        ]);
    }

    /**
     * Memtotong Saldo Wallet (Pembayaran / Withdrawal)
     */
    public function withdraw(float $amount, string $type, string $description = null, string $referenceId = null): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal harus lebih besar dari nol.');
        }
        if ($this->balance < $amount) {
            throw new \Exception('Saldo tidak mencukupi.');
        }

        $balanceBefore = $this->balance;
        $this->balance -= $amount;
        $this->save();

        // Mengirimkan data mutasi lengkap ke tabel transaksi pendukung
        $this->transactions()->create([
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $this->balance,
            'description'    => $description,
            'reference_id'   => $referenceId
        ]);
    }
}