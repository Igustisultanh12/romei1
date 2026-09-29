<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log; // WAJIB IMPORT UNTUK PROSES LOGGING TRACKER

class TicketController extends Controller
{
    // =========================================================================
    // SISI PELANGGAN / USER REGULER
    // =========================================================================

    /**
     * Halaman List & Form Tiket untuk Pelanggan (Murni Sisi User)
     */
    public function index()
    {
        // Menyelaraskan query sesuai relasi milik user yang sedang aktif login
        $tickets = Ticket::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        // Merender halaman khusus riwayat & form pembuatan aduan user
        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets
        ]);
    }

    /**
     * Proses Submit Tiket Baru oleh Pelanggan + SISTEM LOG TRACKER
     */
    public function store(Request $request)
    {
        Log::channel('single')->info('==================================================');
        Log::channel('single')->info('ROMEI TICKET: Deteksi Pengajuan Tiket Baru Pengguna');
        Log::channel('single')->info('ROMEI TICKET: User ID Pengirim -> ' . auth()->id());
        Log::channel('single')->info('ROMEI TICKET: Form Input Category -> ' . ($request->category ?? 'KOSONG'));
        Log::channel('single')->info('ROMEI TICKET: Form Input Description -> ' . ($request->description ?? 'KOSONG'));
        Log::channel('single')->info('ROMEI TICKET: Form Input Withdraw Amount -> ' . ($request->withdraw_amount ?? 'KOSONG'));

        // 1. Eksekusi Validasi Input Form Dinamis dari Vue Frontend
        try {
            $request->validate([
                'category' => 'required|string',
                'description' => 'required|string|min:10',
                'withdraw_amount' => 'required_if:category,Permohonan Tarik Saldo|nullable|numeric|min:10000',
            ]);
            Log::channel('single')->info('ROMEI TICKET: Tahap 1 - Validasi Skrip Form Lolos Sempurna.');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            Log::channel('single')->error('ROMEI TICKET: GAGAL pada Tahap 1 (Validasi Form Ditolak!).');
            Log::channel('single')->error('ROMEI TICKET: Rincian Eror Validasi -> ', $ve->errors());
            Log::channel('single')->info('==================================================');
            throw $ve;
        }

        // 2. Pembuatan Nomor Tiket Acak Unik untuk Kolom 'ticket_number' sesuai phpMyAdmin Anda
        $generatedTicketId = 'TCK-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
        Log::channel('single')->info('ROMEI TICKET: Tahap 2 - Nomor Referensi Terbaca -> ' . $generatedTicketId);

        // 3. Modifikasi Teks Deskripsi: Menyisipkan data Kategori & Nominal Dana secara rapi ke dalam teks deskripsi
        $finalDescription = "Kategori Masalah: " . $request->category . "\n";
        
        if ($request->category === 'Permohonan Tarik Saldo' && !empty($request->withdraw_amount)) {
            $finalDescription .= "Jumlah Penarikan Dana: Rp " . number_format($request->withdraw_amount, 0, ',', '.') . "\n";
        }
        
        $finalDescription .= "----------------------------------------\n";
        $finalDescription .= "Detail Keluhan:\n" . $request->description;

        // 4. Masukkan data ke Database mengikuti struktur kolom phpMyAdmin asli Anda + Try Catch Tangkap Eror DB
        try {
            Log::channel('single')->info('ROMEI TICKET: Tahap 3 - Memulai Perintah Eloquent Insert Database.');
            
            // PERBAIKAN FINAL: Memetakan request->category ke kolom 'category', 'title', & 'subject' secara serentak
            $newTicket = Ticket::create([
                'user_id'       => auth()->id(),
                'ticket_number' => $generatedTicketId, 
                'category'      => $request->category,  // <-- Mengisi field 'category' bawaan DB lama Anda agar tidak null
                'subject'       => $request->category,  // <-- Mengisi field 'subject' sebagai cadangan integritas data
                'title'         => $request->category,  // <-- Mengisi field 'title' hasil migrasi tabel baru
                'priority'      => $request->category === 'Permohonan Tarik Saldo' ? 'MEDIUM' : 'HIGH', 
                'status'        => 'OPEN',
                'description'   => $finalDescription, 
            ]);

            Log::channel('single')->info('ROMEI TICKET: Tahap 4 - SUKSES! Data Berhasil Disimpan Ke Tabel MySql. ID Record: ' . $newTicket->id);
            Log::channel('single')->info('==================================================');

            return redirect()->back()->with('success', 'Tiket bantuan berhasil dibuat dengan nomor referensi: ' . $generatedTicketId);

        } catch (\Exception $dbError) {
            // MENANGKAP EROR JIKA TABEL MYSQL MENOLAK DATA
            Log::channel('single')->error('ROMEI TICKET: CRITICAL ERROR! Database Menolak Perintah Menyimpan.');
            Log::channel('single')->error('ROMEI TICKET: Pesan Kesalahan Mesin DB -> ' . $dbError->getMessage());
            Log::channel('single')->info('==================================================');
            
            // Memberikan lemparan status error untuk ditangkap reaktif oleh Vue SweetAlert
            return redirect()->back()->withErrors(['database' => 'Gagal memproses penyimpanan. ' . $dbError->getMessage()]);
        }
    }

    /**
     * LENGKAPAN: Melihat detail jalannya percakapan tiket dari sisi user
     */
    public function show($id)
    {
        $ticket = Ticket::where('user_id', auth()->id())->findOrFail($id);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket
        ]);
    }

    /**
     * LENGKAPAN: Mengirim pesan tanggapan balik dari user
     */
    public function reply(Request $request, $id)
    {
        $request->validate([
            'description' => 'required|string|min:5'
        ]);

        $ticket = Ticket::where('user_id', auth()->id())->findOrFail($id);
        
        // Membuka kembali status tiket menjadi OPEN jika user mengirim balasan lanjutan
        $ticket->update([
            'description' => $ticket->description . "\n\n[User Reply - " . now()->format('Y-m-d H:i:s') . "]:\n" . $request->description,
            'status' => 'OPEN'
        ]);

        return redirect()->back()->with('success', 'Tanggapan Anda berhasil dikirim ke antrean.');
    }

    /**
     * LENGKAPAN: Menutup tiket secara mandiri oleh user jika kendala dirasa sudah beres
     */
    public function close($id)
    {
        $ticket = Ticket::where('user_id', auth()->id())->findOrFail($id);
        $ticket->update(['status' => 'CLOSED']);

        return redirect()->back()->with('success', 'Tiket bantuan Anda telah resmi ditutup.');
    }


    // =========================================================================
    // SISI ADMINISTRATIVE / ADMIN PANEL ROMEI HQ
    // =========================================================================

    /**
     * Halaman Management Tiket untuk Panel Admin ROMEI (Murni Sisi Admin)
     */
    public function adminIndex()
    {
        $tickets = Ticket::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        // Mengarahkan objek render menuju folder Admin terisolasi agar layout tidak pecah/bocor
        return Inertia::render('Admin/Tickets/Index', [
            'tickets' => $tickets
        ]);
    }

    /**
     * Proses Balas dan Update Status Tiket oleh Admin ROMEI
     */
    public function adminReply(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:OPEN,IN_PROGRESS,RESOLVED,CLOSED',
            'admin_reply' => 'required|string|min:5'
        ]);

        $ticket = Ticket::findOrFail($id);
        
        // Memperbarui record status keluhan sekaligus mencantumkan waktu tanggapan admin harian
        $ticket->update([
            'status' => $request->status,
            'admin_reply' => $request->admin_reply,
            'replied_at' => now()
        ]);

        return redirect()->back()->with('success', 'Status dan balasan tiket resmi ' . $ticket->ticket_number . ' berhasil diterbitkan.');
    }
}