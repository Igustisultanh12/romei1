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
                'ceirku_api_url'         => Setting::get('ceirku_api_url', Setting::get('ceirku_url', 'https://ceirku.net/api/v1')),
                'ceirku_api_key'         => Setting::get('ceirku_api_key', ''),
                
                // TARIF JASA DIAGNOSIS BERBAYAR (POTONG SALDO WALLET KONSUMEN ROMEI)
                'fee_check_sim_lock'     => (int) Setting::get('fee_check_sim_lock', 5000),
                'fee_check_ceir_history' => (int) Setting::get('fee_check_ceir_history', 7500),
                'fee_add_roamer_1m'      => (int) Setting::get('fee_add_roamer_1m', 135000),
                'fee_add_roamer_3m'      => (int) Setting::get('fee_add_roamer_3m', 180000),
                
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
            'ceirku_api_url'         => 'required|url',
            'ceirku_api_key'         => 'nullable|string',
            
            // Validasi Skema Tarif Penjurnalan Finansial Wallet Konsumen
            'fee_check_sim_lock'     => 'required|integer|min:0',
            'fee_check_ceir_history' => 'required|integer|min:0',
            'fee_add_roamer_1m'      => 'required|integer|min:0',
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

        if (isset($data['ceirku_api_url'])) {
            Setting::updateOrCreate(
                ['key' => 'ceirku_url'],
                ['value' => trim($data['ceirku_api_url']), 'group' => 'ceirku']
            );
        }

        \App\Models\AuditLog::create([
            'user_id'     => auth()->id() ?? 1,
            'activity'    => 'UPDATE_SETTINGS',
            'description' => 'Memperbarui konfigurasi sistem, API CEIRKU Gateway, dan tarif layanan',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return back()->with('flash', ['message' => 'Konfigurasi Mode API CEIRKU, Skema Tarif Finansial, dan Aset Gambar Beranda berhasil diperbarui!']);
    }

    /**
     * Uji koneksi jembatan API Gateway CEIRKU secara realtime
     */
    public function testCeirku(Request $request)
    {
        $request->validate([
            'ceirku_api_url' => 'required|url',
            'ceirku_api_key' => 'nullable|string',
        ]);

        $rawUrl = rtrim(trim($request->ceirku_api_url), '/');
        if (!str_contains($rawUrl, '/api/')) {
            $rawUrl .= '/api/v1';
        }

        $balanceUrl = $rawUrl . '/balance';
        $startTime = microtime(true);

        try {
            $apiKey = $request->ceirku_api_key ?: Setting::get('ceirku_api_key', '');
            $response = \Illuminate\Support\Facades\Http::timeout(6)
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                    'Accept'    => 'application/json',
                ])
                ->post($balanceUrl);

            $latency = round((microtime(true) - $startTime) * 1000) . 'ms';
            $statusCode = $response->status();

            if ($response->successful() || in_array($statusCode, [200, 401, 403])) {
                $isAuthOk = $response->successful();
                return response()->json([
                    'success'     => true,
                    'latency'     => $latency,
                    'status_code' => $statusCode,
                    'is_auth_ok'  => $isAuthOk,
                    'message'     => $isAuthOk 
                        ? "Gateway CEIRKU aktif & responsif ({$latency})! Kredensial valid." 
                        : "Gateway CEIRKU terhubung ({$latency}, HTTP {$statusCode}). Pastikan API Key benar.",
                ]);
            }

            return response()->json([
                'success'     => false,
                'latency'     => $latency,
                'status_code' => $statusCode,
                'message'     => "Server CEIRKU merespon status HTTP {$statusCode}.",
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'success'     => false,
                'latency'     => '--',
                'status_code' => 500,
                'message'     => "Gagal terhubung ke {$rawUrl}: " . $e->getMessage(),
            ], 422);
        }
    }
}