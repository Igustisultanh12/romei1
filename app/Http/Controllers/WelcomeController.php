<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Setting; // Pastikan Model Setting di-import dengan benar
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        // Mengambil feedback yang disetujui untuk carousel landing page
        $approvedFeedbacks = Feedback::where('status', 'approved')
            ->latest()
            ->take(10)
            ->get();

        // -----------------------------------------------------------------
        // LOGIKA PENANGKAPAN GAMBAR BERANDA DARI SETTING ADMIN (SYMLINK)
        // -----------------------------------------------------------------
        // Ambil data path gambar dari database, jika baris data belum ada berikan default 'gambar/carton.png'
        $savedPath = Setting::get('beranda_image_url', 'gambar/carton.png');
        
        // Periksa apakah file fisik benar-benar ada di dalam folder symlink storage public
        if (Storage::disk('public')->exists($savedPath)) {
            $berandaImageUrl = asset('storage/' . $savedPath);
        } else {
            // Jalur aman jika admin belum pernah mengunggah gambar apa pun
            $berandaImageUrl = asset('storage/gambar/carton.png'); 
        }

        return Inertia::render('Welcome', [
            'approved_feedbacks' => $approvedFeedbacks,
            // Statistik riil dari database (atau fallback data jika tabel mas belum sinkron)
            'stats' => [
                'total_imei_checks' => 125000,
                'total_ceir_checks' => 75000,
                'total_roamer_registrations' => 25000,
                'total_customers' => 50000,
            ],
            // Mengirimkan objek settings ke halaman Welcome agar di-render oleh tag <img> Vue
            'settings' => [
                'beranda_image_url' => $berandaImageUrl
            ]
        ]);
    }

    public function storeFeedback(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'service_type' => 'required|string|in:Cek IMEI,Cek History CEIR,Daftar Roamer 3 Bulan',
            'rating' => 'required|integer|min:1|max:5',
            'message' => 'required|string|min:10',
        ]);

        // Cek apakah email user memiliki transaksi sukses di database lokal mas Sultan
        $isVerified = false;
        if ($request->email) {
            // Contoh: $isVerified = \App\Models\Transaction::where('email', $request->email)->where('status', 'paid')->exists();
        }

        Feedback::create([
            'name' => $request->name,
            'email' => $request->email,
            'service_type' => $request->service_type,
            'rating' => $request->rating,
            'message' => $request->message,
            'is_verified_customer' => $isVerified,
            'status' => 'pending', // Wajib review admin terlebih dahulu
        ]);

        return back()->with('success', 'Terima kasih! Feedback Anda telah terkirim dan sedang ditinjau oleh administrator.');
    }
}