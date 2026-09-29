<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terikat dengan model di database.
     *
     * @var string
     */
    protected $table = 'feedbacks';

    /**
     * Atribut yang dapat diisi secara massal (Mass Assignment Protection).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'service_type',
        'rating',
        'message',
        'admin_reply',
        'is_verified_customer',
        'status', // pending, approved, rejected
    ];

    /**
     * Atribut casting tipe data otomatis saat diakses oleh Eloquent / Inertia JSON.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'integer',
        'is_verified_customer' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * SCOPE: Mempermudah query mengambil feedback yang siap ditampilkan di Landing Page (Approved)
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * SCOPE: Mengambil feedback yang masih memerlukan tindakan verifikasi admin harian
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * HELPER: Mengecek secara instan apakah feedback ini memiliki balasan resmi dari tim admin ROMEI
     *
     * @return bool
     */
    public function hasReply(): bool
    {
        return !empty($this->admin_reply);
    }
}