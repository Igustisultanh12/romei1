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
        'two_factor_enabled',
        'two_factor_otp',
        'two_factor_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_otp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_enabled' => 'boolean',
        'two_factor_expires_at' => 'datetime',
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

    /**
     * Generate 6-digit OTP untuk 2FA
     */
    public function generateTwoFactorOtp(int $validMinutes = 10): string
    {
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $this->forceFill([
            'two_factor_otp' => \Illuminate\Support\Facades\Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes($validMinutes),
        ])->save();

        return $code;
    }

    /**
     * Verifikasi kode OTP 2FA
     */
    public function verifyTwoFactorOtp(string $code): bool
    {
        if (empty($this->two_factor_otp) || empty($this->two_factor_expires_at)) {
            return false;
        }

        if (now()->isAfter($this->two_factor_expires_at)) {
            return false;
        }

        return \Illuminate\Support\Facades\Hash::check($code, $this->two_factor_otp);
    }

    /**
     * Hapus OTP 2FA setelah berhasil diverifikasi
     */
    public function clearTwoFactorOtp(): void
    {
        $this->forceFill([
            'two_factor_otp' => null,
            'two_factor_expires_at' => null,
        ])->save();
    }
}