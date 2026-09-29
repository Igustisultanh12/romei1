<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Setting;
use App\Mail\OtpNotificationMail;
use App\Services\WhatsappService2;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin2FaVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Hanya terapkan untuk user dengan role Admin
        if ($user && ($user->isAdmin() || $user->role === 'admin')) {
            $is2FaEnabled = Setting::get('admin_2fa_enabled', '1') === '1';

            if ($is2FaEnabled) {
                // Periksa apakah sesi 2FA sudah terverifikasi
                if ($request->session()->get('admin_2fa_verified') !== true) {
                    
                    // Jika belum ada OTP aktif di sesi, buat dan kirimkan secara otomatis
                    if (!$request->session()->has('admin_2fa_otp_hash') || now()->timestamp > $request->session()->get('admin_2fa_expires_at', 0)) {
                        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                        $channel = !empty($user->whatsapp_number) ? 'whatsapp' : 'email';

                        $request->session()->put([
                            'admin_2fa_user_id'    => $user->id,
                            'admin_2fa_otp_hash'   => Hash::make($otp),
                            'admin_2fa_expires_at' => now()->addMinutes(10)->timestamp,
                            'admin_2fa_attempts'   => 0,
                            'admin_2fa_sent_at'    => now()->timestamp,
                            'admin_2fa_channel'    => $channel,
                        ]);

                        $user->forceFill([
                            'two_factor_otp'        => Hash::make($otp),
                            'two_factor_expires_at' => now()->addMinutes(10),
                        ])->save();

                        if ($channel === 'whatsapp') {
                            try {
                                $msg = "[SECURITY ROMEI HQ]\n\nKode Otentikasi 2FA Anda: *{$otp}*\n\nBerlaku selama 10 menit. Jangan berikan kode ini kepada siapa pun demi keamanan akun Administrator ROMEI.";
                                WhatsappService2::sendMessage($user->whatsapp_number, $msg);
                            } catch (\Throwable $e) {
                                Log::warning("Gagal mengirim WhatsApp 2FA ke {$user->whatsapp_number}: " . $e->getMessage());
                            }
                        } else {
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
                        }
                    }

                    return redirect()->route('admin.2fa.index');
                }
            }
        }

        return $next($request);
    }
}
