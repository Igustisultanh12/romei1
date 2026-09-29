<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Mail\OtpNotificationMail;
use App\Services\WhatsappService2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AdminTwoFactorController extends Controller
{
    /**
     * Tampilkan halaman verifikasi OTP 2FA untuk Admin
     */
    public function show(Request $request)
    {
        $userId = session('admin_2fa_user_id') ?? Auth::id();

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            session()->forget(['admin_2fa_user_id', 'admin_2fa_remember', 'admin_2fa_otp_hash', 'admin_2fa_expires_at', 'admin_2fa_attempts']);
            return redirect()->route('login');
        }

        // Jika sudah terverifikasi 2FA, arahkan langsung ke dashboard admin
        if (session('admin_2fa_verified') === true && Auth::check() && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // Mask email: misal 'admin@domain.com' -> 'a***n@domain.com'
        $maskedEmail = $this->maskEmail($user->email);

        $expiresAt = session('admin_2fa_expires_at', now()->addMinutes(10)->timestamp);
        $lastSentAt = session('admin_2fa_sent_at', now()->timestamp);
        $cooldown = max(0, 60 - (now()->timestamp - $lastSentAt));

        return Inertia::render('Admin/Auth/TwoFactorChallenge', [
            'maskedEmail' => $maskedEmail,
            'expiresIn'   => max(0, $expiresAt - now()->timestamp),
            'cooldown'    => $cooldown,
        ]);
    }

    /**
     * Verifikasi kode OTP 6-Digit yang diinputkan Admin
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ], [
            'code.required' => 'Kode OTP 6-digit wajib diisi.',
            'code.size'     => 'Kode OTP harus berjumlah tepat 6 digit angka.',
        ]);

        $userId = session('admin_2fa_user_id') ?? Auth::id();

        if (!$userId) {
            return redirect()->route('login')->withErrors(['email' => 'Sesi autentikasi telah habis. Silakan masuk kembali.']);
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        // Periksa batasan jumlah percobaan
        $attempts = session('admin_2fa_attempts', 0);
        if ($attempts >= 5) {
            session()->forget(['admin_2fa_user_id', 'admin_2fa_remember', 'admin_2fa_otp_hash', 'admin_2fa_expires_at', 'admin_2fa_attempts', 'admin_2fa_sent_at']);
            if (Auth::check()) {
                Auth::logout();
            }
            return redirect()->route('login')->withErrors(['email' => 'Batas percobaan OTP terlampaui. Silakan masuk kembali untuk kode baru.']);
        }

        // Periksa batas waktu kadaluarsa OTP
        $expiresAt = session('admin_2fa_expires_at', 0);
        if (now()->timestamp > $expiresAt) {
            return back()->withErrors(['code' => 'Kode OTP telah kedaluwarsa. Silakan klik tombol "Kirim Ulang Kode OTP".']);
        }

        // Verifikasi kecocokan OTP
        $hash = session('admin_2fa_otp_hash');
        $isValid = false;

        if ($hash && Hash::check($request->code, $hash)) {
            $isValid = true;
        } elseif ($user->verifyTwoFactorOtp($request->code)) {
            $isValid = true;
        }

        if (!$isValid) {
            session(['admin_2fa_attempts' => $attempts + 1]);

            AuditLog::create([
                'user_id'     => $user->id,
                'activity'    => 'ADMIN_2FA_FAILED',
                'description' => 'Percobaan verifikasi OTP 2FA admin gagal (Kode Salah)',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            $remaining = 5 - ($attempts + 1);
            return back()->withErrors(['code' => "Kode OTP tidak sesuai. Sisa percobaan: {$remaining} kali."]);
        }

        // Bersihkan state OTP
        $remember = session('admin_2fa_remember', false);
        session()->forget(['admin_2fa_user_id', 'admin_2fa_remember', 'admin_2fa_otp_hash', 'admin_2fa_expires_at', 'admin_2fa_attempts', 'admin_2fa_sent_at']);
        $user->clearTwoFactorOtp();

        // Login pengguna jika belum terautentikasi
        if (!Auth::check() || Auth::id() !== $user->id) {
            Auth::login($user, $remember);
        }

        $request->session()->regenerate();
        session([
            'admin_2fa_verified'    => true,
            'admin_2fa_verified_at' => now()->timestamp,
        ]);

        AuditLog::create([
            'user_id'     => $user->id,
            'activity'    => 'ADMIN_2FA_SUCCESS',
            'description' => 'Verifikasi Otentikasi Dua Faktor (2FA) Admin berhasil lolos',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        Log::info("ROMEI SECURITY: Admin {$user->email} berhasil menyelesaikan 2FA via Mail Gateway.");

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Kirim ulang kode OTP 2FA ke email & WhatsApp
     */
    public function resend(Request $request)
    {
        $userId = session('admin_2fa_user_id') ?? Auth::id();

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        // Cek cooldown kirim ulang (60 detik)
        $lastSentAt = session('admin_2fa_sent_at', 0);
        if (now()->timestamp - $lastSentAt < 60) {
            $wait = 60 - (now()->timestamp - $lastSentAt);
            return back()->withErrors(['code' => "Mohon tunggu {$wait} detik sebelum meminta kode OTP baru."]);
        }

        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        session([
            'admin_2fa_otp_hash'   => Hash::make($otp),
            'admin_2fa_expires_at' => now()->addMinutes(10)->timestamp,
            'admin_2fa_attempts'   => 0,
            'admin_2fa_sent_at'    => now()->timestamp,
        ]);

        $user->forceFill([
            'two_factor_otp'        => Hash::make($otp),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        // 1. Kirim via Mail Gateway
        try {
            Mail::to($user->email)->send(
                new OtpNotificationMail(
                    $user->name,
                    'ADMIN-HQ',
                    $otp,
                    'Login Dashboard Admin (2FA)',
                    '10 Menit'
                )
            );
        } catch (\Throwable $e) {
            Log::error("Gagal mengirim email 2FA ke {$user->email}: " . $e->getMessage());
        }

        // 2. Kirim via WhatsApp jika nomor tersedia
        if (!empty($user->whatsapp_number)) {
            try {
                $msg = "[SECURITY ROMEI HQ]\n\nKode Verifikasi 2FA Anda: *{$otp}*\n\nBerlaku selama 10 menit. Jangan berikan kode ini kepada siapa pun.";
                WhatsappService2::sendMessage($user->whatsapp_number, $msg);
            } catch (\Throwable $e) {
                Log::warning("Gagal mengirim WA 2FA ke {$user->whatsapp_number}: " . $e->getMessage());
            }
        }

        AuditLog::create([
            'user_id'     => $user->id,
            'activity'    => 'ADMIN_2FA_RESEND',
            'description' => "Permintaan kirim ulang kode OTP 2FA Admin ke {$user->email}",
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return back()->with('success', 'Kode verifikasi OTP baru telah berhasil dikirimkan ke email Anda.');
    }

    /**
     * Batalkan proses 2FA dan kembali ke form login
     */
    public function cancel(Request $request)
    {
        session()->forget(['admin_2fa_user_id', 'admin_2fa_remember', 'admin_2fa_otp_hash', 'admin_2fa_expires_at', 'admin_2fa_attempts', 'admin_2fa_sent_at', 'admin_2fa_verified']);
        
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }

    /**
     * Helper untuk masking email
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        $length = strlen($name);
        if ($length <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', max(3, $length - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }
}
