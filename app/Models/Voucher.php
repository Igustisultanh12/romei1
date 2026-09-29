<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'max_uses',
        'uses_count',
        'expires_at',
        'is_active'
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function imeiRegistrations(): HasMany
    {
        return $this->hasMany(ImeiRegistration::class);
    }

    /**
     * Helper pengecekan masa aktif kupon voucher harian
     */
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->max_uses && $this->uses_count >= $this->max_uses) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        
        return true;
    }
}