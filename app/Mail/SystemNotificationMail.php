<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SystemNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $title;
    public string $contentMessage;
    public ?string $actionUrl;
    public ?string $actionText;

    /**
     * Create a new message instance.
     */
    public function __construct(string $title, string $contentMessage, ?string $actionUrl = null, ?string $actionText = null)
    {
        $this->title          = $title;
        $this->contentMessage = $contentMessage;
        $this->actionUrl      = $actionUrl;
        $this->actionText     = $actionText;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('[ROMEI Notification] ' . $this->title)
                    ->html($this->htmlContent());
    }

    /**
     * Generates clean HTML content
     */
    private function htmlContent(): string
    {
        $actionBtnHtml = '';
        if ($this->actionUrl && $this->actionText) {
            $actionBtnHtml = "
            <div style='text-align: center; margin: 28px 0;'>
                <a href='{$this->actionUrl}' style='display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 13px; letter-spacing: 0.5px;'>
                    {$this->actionText}
                </a>
            </div>";
        }

        return "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='utf-8'>
    <title>{$this->title}</title>
</head>
<body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; background-color: #0f172a; margin: 0; padding: 32px 16px;'>
    <div style='max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #1e293b;'>
        <div style='background: #0f172a; padding: 28px 24px; text-align: center; border-bottom: 3px solid #2563eb;'>
            <h1 style='color: #ffffff; margin: 0; font-size: 20px; font-weight: 900;'>ROMEI PLATFORM</h1>
            <p style='color: #94a3b8; margin: 4px 0 0 0; font-size: 11px;'>Sistem Notifikasi Operasional</p>
        </div>
        <div style='padding: 32px 28px; color: #334155;'>
            <h2 style='color: #0f172a; margin-top: 0; font-size: 17px; font-weight: 800;'>{$this->title}</h2>
            <div style='font-size: 13px; line-height: 1.6; color: #475569;'>
                " . nl2br(htmlspecialchars($this->contentMessage)) . "
            </div>
            {$actionBtnHtml}
        </div>
        <div style='background-color: #f8fafc; padding: 16px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8;'>
            &copy; " . date('Y') . " ROMEI Platform. Automated System Notification.
        </div>
    </div>
</body>
</html>";
    }
}
