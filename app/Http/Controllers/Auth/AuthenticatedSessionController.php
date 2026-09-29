<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log; // WAJIB DITAMBAHKAN: Untuk mencatat log jejak user
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        Log::channel('single')->info('==================================================');
        Log::channel('single')->info('ROMEI AUTH: Deteksi Percobaan Login Baru');
        Log::channel('single')->info('ROMEI AUTH: Email Input -> ' . $request->email);

        try {
            // 1. Eksekusi Pencocokan Kredensial (Email & Password) ke Database
            $request->authenticate();

            // 2. Jika Lolos, Regenerasi ID Sesi Browser Pengguna
            $request->session()->regenerate();

            $user = Auth::user();
            
            // 3. Catat Data Pengguna yang Berhasil Lolos ke Dalam Log
            Log::channel('single')->info('ROMEI AUTH: Otentikasi BERHASIL Masuk DB');
            Log::channel('single')->info('ROMEI AUTH: User ID -> ' . $user->id);
            Log::channel('single')->info('ROMEI AUTH: User Name -> ' . $user->name);
            Log::channel('single')->info('ROMEI AUTH: User Email -> ' . $user->email);
            Log::channel('single')->info('ROMEI AUTH: User Role -> ' . ($user->role ?? 'NULL (Kosong)'));
            Log::channel('single')->info('ROMEI AUTH: Email Verified At -> ' . ($user->email_verified_at ?? 'NULL (Belum Verifikasi)'));

            // 4. Deteksi Level Hak Akses Pengguna Secara Riil
            if ($user->role === 'admin' || (method_exists($user, 'isAdmin') && $user->isAdmin())) {
                $is2FaEnabled = \App\Models\Setting::get('admin_2fa_enabled', '1') === '1';

                if ($is2FaEnabled) {
                    $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

                    // Sesuai permintaan: OTP dikirimkan lewat WhatsApp dan bisa juga memilih lewat Email
                    $channel = !empty($user->whatsapp_number) ? 'whatsapp' : 'email';

                    $request->session()->put([
                        'admin_2fa_user_id'    => $user->id,
                        'admin_2fa_remember'   => $request->boolean('remember'),
                        'admin_2fa_otp_hash'   => \Illuminate\Support\Facades\Hash::make($otp),
                        'admin_2fa_expires_at' => now()->addMinutes(10)->timestamp,
                        'admin_2fa_attempts'   => 0,
                        'admin_2fa_sent_at'    => now()->timestamp,
                        'admin_2fa_channel'    => $channel,
                    ]);

                    $user->forceFill([
                        'two_factor_otp'        => \Illuminate\Support\Facades\Hash::make($otp),
                        'two_factor_expires_at' => now()->addMinutes(10),
                    ])->save();

                    // Kirim OTP via kanal utama (WhatsApp jika nomor ada, atau Email)
                    if ($channel === 'whatsapp') {
                        try {
                            $msg = "[SECURITY ROMEI HQ]\n\nKode Otentikasi 2FA Anda: *{$otp}*\n\nBerlaku selama 10 menit. Jangan berikan kode ini kepada siapa pun demi keamanan akun Administrator ROMEI.";
                            \App\Services\WhatsappService2::sendMessage($user->whatsapp_number, $msg);
                        } catch (\Throwable $e) {
                            Log::warning("Gagal kirim WhatsApp OTP 2FA ke {$user->whatsapp_number}: " . $e->getMessage());
                        }
                    } else {
                        try {
                            \Illuminate\Support\Facades\Mail::to($user->email)->send(
                                new \App\Mail\OtpNotificationMail(
                                    $user->name,
                                    'ADMIN-HQ',
                                    $otp,
                                    'Login Dashboard Admin (2FA)',
                                    '10 Menit'
                                )
                            );
                        } catch (\Throwable $e) {
                            Log::error("Gagal kirim email OTP 2FA ke {$user->email}: " . $e->getMessage());
                        }
                    }

                    Log::channel('single')->info("ROMEI AUTH: Admin membutuhkan 2FA (Kanal: {$channel}). Mengalihkan ke /admin/2fa");
                    Log::channel('single')->info('==================================================');

                    return redirect()->route('admin.2fa.index');
                }

                Log::channel('single')->info('ROMEI AUTH: Pengguna adalah Admin. Mengalihkan ke /admin/dashboard');
                Log::channel('single')->info('==================================================');
                return redirect()->intended(url('/admin/dashboard'));
            }

            // 5. Alur Pengalihan Untuk Pengguna Biasa / Pelanggan ROMEI
            Log::channel('single')->info('ROMEI AUTH: Pengguna adalah Customer Biasa. Mengalihkan ke /dashboard');
            Log::channel('single')->info('==================================================');
            
            return redirect()->intended(route('dashboard', absolute: false));

        } catch (\Exception $e) {
            // 6. Catat Jika Ada Kegagalan (Password Salah / Akun Belum Ada)
            Log::channel('single')->error('ROMEI AUTH: Kegagalan Proses Autentikasi!');
            Log::channel('single')->error('ROMEI AUTH: Pesan Kegagalan -> ' . $e->getMessage());
            Log::channel('single')->info('==================================================');
            
            throw $e;
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Log::channel('single')->info('ROMEI AUTH: User ' . (Auth::user()->email ?? 'Guest') . ' Melakukan Keluar Aplikasi (Logout).');
        
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}