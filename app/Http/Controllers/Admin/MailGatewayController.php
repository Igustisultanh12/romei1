<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\AuditLog;
use App\Mail\OtpNotificationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class MailGatewayController extends Controller
{
    /**
     * Tampilkan halaman utama Mail Gateway
     */
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        // Default values jika belum tersimpan di DB
        $mailConfig = [
            'mail_mailer'       => $settings['mail_mailer'] ?? config('mail.default', 'smtp'),
            'mail_host'         => $settings['mail_host'] ?? config('mail.mailers.smtp.host', 'smtp.gmail.com'),
            'mail_port'         => $settings['mail_port'] ?? (string) config('mail.mailers.smtp.port', '587'),
            'mail_username'     => $settings['mail_username'] ?? config('mail.mailers.smtp.username', ''),
            'mail_password'     => $settings['mail_password'] ?? config('mail.mailers.smtp.password', ''),
            'mail_encryption'   => $settings['mail_encryption'] ?? config('mail.mailers.smtp.encryption', 'tls'),
            'mail_from_address' => $settings['mail_from_address'] ?? config('mail.from.address', 'no-reply@romei.my.id'),
            'mail_from_name'    => $settings['mail_from_name'] ?? config('mail.from.name', 'ROMEI Platform'),
            'enable_email_otp'  => $settings['enable_email_otp'] ?? '1',
            'admin_2fa_enabled' => $settings['admin_2fa_enabled'] ?? '1',
        ];

        return Inertia::render('Admin/MailGateway/Index', [
            'mailConfig' => $mailConfig,
        ]);
    }

    /**
     * Simpan pembaruan konfigurasi Mail Gateway & 2FA
     */
    public function update(Request $request)
    {
        $request->validate([
            'mail_mailer'       => 'required|string|in:smtp,log',
            'mail_host'         => 'required|string',
            'mail_port'         => 'required|numeric',
            'mail_username'     => 'nullable|string',
            'mail_password'     => 'nullable|string',
            'mail_encryption'   => 'nullable|string|in:tls,ssl,none',
            'mail_from_address' => 'required|email',
            'mail_from_name'    => 'required|string',
            'enable_email_otp'  => 'required|in:0,1',
            'admin_2fa_enabled' => 'required|in:0,1',
        ], [
            'mail_mailer.required'       => 'Driver Mailer wajib dipilih.',
            'mail_host.required'         => 'SMTP Host wajib diisi.',
            'mail_port.required'         => 'SMTP Port wajib diisi.',
            'mail_from_address.required' => 'Email pengirim (From Address) wajib diisi.',
            'mail_from_address.email'    => 'Format email pengirim tidak valid.',
            'mail_from_name.required'    => 'Nama pengirim (From Name) wajib diisi.',
        ]);

        $keys = [
            'mail_mailer',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_from_address',
            'mail_from_name',
            'enable_email_otp',
            'admin_2fa_enabled',
        ];

        foreach ($keys as $key) {
            $val = $request->input($key, '');
            if ($key === 'mail_encryption' && $val === 'none') {
                $val = null;
            }
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $val, 'group' => 'mail']
            );
        }

        // Terapkan ke runtime config secara langsung
        Config::set('mail.default', $request->mail_mailer);
        Config::set('mail.mailers.smtp.host', $request->mail_host);
        Config::set('mail.mailers.smtp.port', $request->mail_port);
        Config::set('mail.mailers.smtp.username', $request->mail_username);
        Config::set('mail.mailers.smtp.password', $request->mail_password);
        Config::set('mail.mailers.smtp.encryption', $request->mail_encryption === 'none' ? null : $request->mail_encryption);
        Config::set('mail.from.address', $request->mail_from_address);
        Config::set('mail.from.name', $request->mail_from_name);

        // Terapkan ke file .env jika file tersebut ada dan writable
        $this->updateEnvFile([
            'MAIL_MAILER'       => $request->mail_mailer,
            'MAIL_HOST'         => $request->mail_host,
            'MAIL_PORT'         => $request->mail_port,
            'MAIL_USERNAME'     => '"' . $request->mail_username . '"',
            'MAIL_PASSWORD'     => '"' . $request->mail_password . '"',
            'MAIL_ENCRYPTION'   => $request->mail_encryption === 'none' ? 'null' : $request->mail_encryption,
            'MAIL_FROM_ADDRESS' => '"' . $request->mail_from_address . '"',
            'MAIL_FROM_NAME'    => '"' . $request->mail_from_name . '"',
        ]);

        AuditLog::create([
            'user_id'     => Auth::id() ?? 1,
            'activity'    => 'UPDATE_MAIL_GATEWAY',
            'description' => 'Memperbarui konfigurasi SMTP Mail Gateway dan parameter keamanan 2FA',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return redirect()->route('admin.mail.index')->with('success', 'Konfigurasi Mail Gateway & Keamanan 2FA berhasil disimpan.');
    }

    /**
     * Melakukan pengujian pengiriman email uji coba langsung
     */
    public function testSend(Request $request)
    {
        $request->validate([
            'recipient_email' => 'required|email',
        ], [
            'recipient_email.required' => 'Alamat email penerima uji coba wajib diisi.',
            'recipient_email.email'    => 'Format alamat email penerima tidak valid.',
        ]);

        $recipient = $request->recipient_email;

        // Ambil konfigurasi tersimpan
        $settings = Setting::pluck('value', 'key')->toArray();
        $mailer   = $settings['mail_mailer'] ?? config('mail.default', 'smtp');
        $host     = $settings['mail_host'] ?? config('mail.mailers.smtp.host');
        $port     = $settings['mail_port'] ?? config('mail.mailers.smtp.port');
        $username = $settings['mail_username'] ?? config('mail.mailers.smtp.username');
        $password = $settings['mail_password'] ?? config('mail.mailers.smtp.password');
        $encrypt  = $settings['mail_encryption'] ?? config('mail.mailers.smtp.encryption');

        if ($encrypt === 'none') {
            $encrypt = null;
        }

        Config::set('mail.default', $mailer);
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', $username);
        Config::set('mail.mailers.smtp.password', $password);
        Config::set('mail.mailers.smtp.encryption', $encrypt);
        Config::set('mail.from.address', $settings['mail_from_address'] ?? 'no-reply@romei.my.id');
        Config::set('mail.from.name', $settings['mail_from_name'] ?? 'ROMEI Platform');

        try {
            $otpDummy = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            
            Mail::to($recipient)->send(
                new OtpNotificationMail(
                    'Administrator ROMEI',
                    'ADMIN-TEST-HQ',
                    $otpDummy,
                    'Uji Coba Mail Gateway SMTP',
                    '10 Menit'
                )
            );

            AuditLog::create([
                'user_id'     => Auth::id() ?? 1,
                'activity'    => 'TEST_MAIL_GATEWAY',
                'description' => "Pengujian pengiriman email Mail Gateway ke alamat: {$recipient}",
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            return back()->with('success', "Email uji coba berhasil dikirimkan ke {$recipient}. Silakan periksa Kotak Masuk atau Spambox email tersebut.");
        } catch (\Exception $e) {
            Log::error("Gagal uji coba Mail Gateway ROMEI ke {$recipient}: " . $e->getMessage());
            return back()->with('error', 'Gagal mengirim email uji coba: ' . $e->getMessage());
        }
    }

    /**
     * Update .env file helper
     */
    private function updateEnvFile(array $data)
    {
        $envFile = base_path('.env');
        if (!file_exists($envFile) || !is_writable($envFile)) {
            return;
        }

        try {
            $content = file_get_contents($envFile);

            foreach ($data as $key => $value) {
                $pattern = "/^{$key}=.*/m";
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, "{$key}={$value}", $content);
                } else {
                    $content .= "\n{$key}={$value}";
                }
            }

            file_put_contents($envFile, $content);
        } catch (\Throwable $e) {
            Log::warning("Gagal memperbarui file .env: " . $e->getMessage());
        }
    }
}
