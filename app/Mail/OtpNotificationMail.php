<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $identifier;
    public string $otpCode;
    public string $type;
    public string $expiredInfo;

    /**
     * Create a new message instance.
     */
    public function __construct(string $name, string $identifier, string $otpCode, string $type = 'Verifikasi Keamanan (2FA)', string $expiredInfo = '10 Menit')
    {
        $this->name        = $name;
        $this->identifier  = $identifier;
        $this->otpCode     = $otpCode;
        $this->type        = $type;
        $this->expiredInfo = $expiredInfo;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('[ROMEI HQ] Kode Verifikasi Keamanan ' . $this->type)
                    ->html($this->htmlContent());
    }

    /**
     * Generates clean, modern HTML Email Content without emojis
     */
    private function htmlContent(): string
    {
        $year = date('Y');

        return "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Kode Verifikasi ROMEI</title>
</head>
<body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #0f172a; margin: 0; padding: 32px 16px; color: #334155;'>
    <div style='max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.2); border: 1px solid #1e293b;'>
        
        <!-- HEADER -->
        <div style='background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 32px 28px; text-align: center; border-bottom: 3px solid #2563eb;'>
            <div style='display: inline-block; padding: 6px 16px; border-radius: 9999px; background: rgba(37, 99, 235, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); margin-bottom: 12px;'>
                <span style='color: #60a5fa; font-size: 11px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;'>SECURITY VERIFICATION</span>
            </div>
            <h1 style='color: #ffffff; margin: 0; font-size: 22px; font-weight: 900; letter-spacing: 1px;'>ROMEI OPERATING SYSTEM</h1>
            <p style='color: #94a3b8; margin: 6px 0 0 0; font-size: 11px; font-weight: 600; letter-spacing: 0.5px;'>High-Security Multi-Factor Authentication</p>
        </div>

        <!-- CONTENT BODY -->
        <div style='padding: 36px 32px;'>
            <p style='font-size: 15px; color: #0f172a; margin-top: 0; font-weight: 700;'>
                Halo, {$this->name}
            </p>
            
            <p style='font-size: 13px; line-height: 1.6; color: #475569; margin: 12px 0 24px 0;'>
                Permintaan otentikasi terdeteksi untuk akun Anda pada gerbang administratif ROMEI HQ. Masukkan kode verifikasi One-Time Password (OTP) berikut untuk melanjutkan:
            </p>

            <!-- OTP HIGHLIGHT CARD -->
            <div style='background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; padding: 24px; text-align: center; margin: 24px 0;'>
                <span style='display: block; font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px;'>KODE VERIFIKASI (OTP)</span>
                <div style='font-size: 36px; font-weight: 900; letter-spacing: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; color: #38bdf8; text-indent: 12px;'>
                    {$this->otpCode}
                </div>
                <div style='display: inline-block; margin-top: 12px; padding: 4px 12px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px;'>
                    <span style='font-size: 11px; color: #f87171; font-weight: 600;'>Berlaku Selama: {$this->expiredInfo}</span>
                </div>
            </div>

            <!-- AUDIT DETAIL TABLE -->
            <table style='width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 24px; background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;'>
                <tr>
                    <td style='padding: 10px 14px; color: #64748b; font-weight: 600; width: 140px; border-bottom: 1px solid #e2e8f0;'>Tujuan Otentikasi</td>
                    <td style='padding: 10px 14px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #e2e8f0;'>{$this->type}</td>
                </tr>
                <tr>
                    <td style='padding: 10px 14px; color: #64748b; font-weight: 600; border-bottom: 1px solid #e2e8f0;'>Identitas Akun</td>
                    <td style='padding: 10px 14px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #e2e8f0;'>{$this->identifier}</td>
                </tr>
                <tr>
                    <td style='padding: 10px 14px; color: #64748b; font-weight: 600;'>Waktu Penerbitan</td>
                    <td style='padding: 10px 14px; font-weight: 700; color: #0f172a;'>" . date('d M Y, H:i') . " WIB</td>
                </tr>
            </table>

            <!-- SECURITY NOTICE -->
            <div style='background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; border-radius: 6px; font-size: 12px; color: #991b1b; line-height: 1.5;'>
                <strong>PERINGATAN KEAMANAN:</strong> Jangan pernah memberikan kode OTP ini kepada siapa pun, termasuk staf operasional ROMEI. Sistem kami tidak akan pernah meminta kode ini melalui pesan pribadi.
            </div>

            <p style='font-size: 12px; color: #94a3b8; margin: 24px 0 0 0; line-height: 1.5; border-top: 1px solid #f1f5f9; padding-top: 16px;'>
                Jika Anda tidak merasa melakukan proses login ini, segera ubah kata sandi akun administratif Anda atau hubungi penanggung jawab keamanan sistem.
            </p>
        </div>

        <!-- FOOTER -->
        <div style='background-color: #f8fafc; padding: 20px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b;'>
            <strong style='color: #0f172a;'>ROMEI PLATFORM - CONTROL HQ</strong><br/>
            <span style='color: #94a3b8; margin-top: 4px; display: inline-block;'>Pesan otomatis ini di-generate oleh Security Daemon ROMEI. Mohon untuk tidak membalas email ini.</span><br/>
            <span style='color: #cbd5e1; font-size: 10px; margin-top: 4px; display: inline-block;'>&copy; {$year} ROMEI Platform. All rights reserved.</span>
        </div>
    </div>
</body>
</html>";
    }
}
