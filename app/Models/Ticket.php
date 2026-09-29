<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /**
     * PERBAIKAN TOTAL: Menyelaraskan seluruh field agar sinkron dengan 
     * TicketController baru dan struktur tabel MySQL phpMyAdmin Anda.
     */
    protected $fillable = [
        'user_id', 
        'ticket_number', // Kolom nomor referensi tiket aduan (Contoh: TCK-20260604-XXXXX)
        'category',
        'subject',       // Cadangan penampung kategori aduan pengguna
        'title',         // Penampung kategori utama aduan pengguna ($request->category)
        'status',        // Status progres tiket (OPEN, IN_PROGRESS, RESOLVED, CLOSED)
        'priority',      // Tingkat urgensi masalah (MEDIUM, HIGH)
        'description',   // Teks gabungan kronologi masalah dan nominal dana kuota
        'admin_reply',   // Teks tanggapan/balasan resmi dari operator CS Admin HQ
        'replied_at'     // Catatan waktu riil respons harian dari operator admin
    ];

    /**
     * Relasi balik ke User (Pemilik / Pencipta Tiket Bantuan)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke data balasan percakapan (Untuk pengembangan fitur riwayat percakapan chat multipel)
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest();
    }
}