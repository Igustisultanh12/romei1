<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingController extends Controller
{
    /**
     * Menampilkan halaman Konfigurasi Sistem ROMEI
     */
    public function index()
    {
        // 1. Ambil path gambar dari database. 
        // Jika baris belum ada di phpMyAdmin, berikan fallback string 'gambar/carton.png'
        $savedPath = Setting::get('beranda_image_url', 'gambar/carton.png');
        
        // 2. Cek apakah file fisik tersebut benar-benar ada di dalam folder symlink storage public
        if (Storage::disk('public')->exists($savedPath)) {
            $berandaImageUrl = asset('storage/' . $savedPath);
        } else {
            // Jika file fisik belum ada (belum pernah upload), arahkan ke default fallback asset
            $berandaImageUrl = asset('storage/gambar/carton.png'); 
        }

        return Inertia::render('Settings/AdminIndex', [
            'settings' => [
                // PENGATURAN INTEGRASI GATEWAY CEIRKU (SESUAI DOKUMEN RESMI)
                'ceirku_mode'            => Setting::get('ceirku_mode', 'sandbox'), // sandbox atau live
                'ceirku_api_key'         => Setting::get('ceirku_api_key', ''),
                
                // TARIF JASA DIAGNOSIS BERBAYAR (POTONG SALDO WALLET KONSUMEN ROMEI)
                'fee_check_sim_lock'     => (int) Setting::get('fee_check_sim_lock', 5000),
                'fee_check_ceir_history' => (int) Setting::get('fee_check_ceir_history', 7500),
                'fee_add_roamer_3m'      => (int) Setting::get('fee_add_roamer_3m', 125000),
                
                // PENGATURAN PEMBAYARAN DOKU MERCHANT GATWAY
                'doku_client_id'         => Setting::get('doku_client_id', ''),
                'doku_secret_key'        => Setting::get('doku_secret_key', ''),
                
                // PENGATURAN UMUM APLIKASI
                'maintenance_mode'       => Setting::get('maintenance_mode', 'false') === 'true',
                'beranda_image_url'      => $berandaImageUrl, // Dikirim ke frontend untuk preview img src
            ]
        ]);
    }

    /**
     * Menyimpan konfigurasi teks dan file gambar menggunakan Symlink Storage
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            // Validasi Kredensial CEIRKU Pusat
            'ceirku_mode'            => 'required|in:sandbox,live',
            'ceirku_api_key'         => 'nullable|string',
            
            // Validasi Skema Tarif Penjurnalan Finansial Wallet Konsumen
            'fee_check_sim_lock'     => 'required|integer|min:0',
            'fee_check_ceir_history' => 'required|integer|min:0',
            'fee_add_roamer_3m'      => 'required|integer|min:0',
            
            // Validasi Finansial Payment Gateway DOKU
            'doku_client_id'         => 'nullable|string',
            'doku_secret_key'        => 'nullable|string',
            
            // Validasi Umum
            'maintenance_mode'       => 'required|boolean',
            'beranda_image'          => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20048', // Aturan validasi file gambar
        ]);

        // Proses Pemindahan File Gambar Beranda Utama Melalui Jalur Symlink
        if ($request->hasFile('beranda_image')) {
            $file = $request->file('beranda_image');
            
            // Membuat nama file unik menggunakan micro-timestamp agar tidak terkena cache browser pelanggan
            $fileName = 'carton_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Menyimpan file fisik ke direktori storage/app/public/gambar/$fileName
            // Secara otomatis file ini dapat diakses publik via /storage/gambar/$fileName berkat symlink
            Storage::disk('public')->putFileAs('gambar', $file, $fileName);

            // AUTO-INSERT & UPDATE: Jika key 'beranda_image_url' belum ada di tabel phpMyAdmin, 
            // fungsi updateOrCreate di bawah ini akan otomatis membuatkan baris barunya secara instan!
            Setting::updateOrCreate(
                ['key' => 'beranda_image_url'],
                [
                    'value' => 'gambar/' . $fileName, // Menyimpan path relatif di kolom value database
                    'group' => 'general'
                ]
            );
        }

        // Hapus indeks berkas gambar dari array agar tidak merusak perulangan input teks database
        unset($data['beranda_image']);

        // Update otomatis untuk semua konfigurasi teks kredensial lainnya
        foreach ($data as $key => $value) {
            
            // Tentukan pengelompokan grup data setting agar database tetap rapi terstruktur
            if (str_contains($key, 'ceirku') || str_contains($key, 'fee_')) {
                $groupName = 'ceirku';
            } elseif (str_contains($key, 'doku')) {
                $groupName = 'doku';
            } else {
                $groupName = 'general';
            }

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    // Memotong spasi tak sengaja di ujung teks saat proses copy-paste token API
                    'value' => is_bool($value) ? ($value ? 'true' : 'false') : trim($value),
                    'group' => $groupName
                ]
            );
        }

        return back()->with('flash', ['message' => 'Konfigurasi Mode API CEIRKU, Skema Tarif Finansial, dan Aset Gambar Beranda berhasil diperbarui!']);
    }
}