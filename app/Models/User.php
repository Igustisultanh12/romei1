<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'whatsapp_number',
        'password',
        'role', // Mengidentifikasi 'admin' atau 'user'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * KUNCI PROTEKSI: Otomatis menginjeksikan role 'user' saat pendaftaran baru dibuat
     * Ini mencegah error SQL "Field 'role' doesn't have a default value" jika skema DB diset NOT NULL
     */
    protected static function booted(): void
    {
        static::creating(function ($user) {
            if (empty($user->role)) {
                $user->role = 'user';
            }
        });
    }

    /**
     * Helper pengecekan hak akses admin di area HQ routes/web.php
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Relasi ke sistem Dompet Digital ROMEI
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Riwayat pendaftaran IMEI gawai pelanggan
     */
    public function imeiRegistrations(): HasMany
    {
        return $this->hasMany(ImeiRegistration::class);
    }

    /**
     * Log pencatatan invoice finansial diagnosis utilitas
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Hubungan ke tabel pengaduan gangguan jaringan & tarik saldo
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}