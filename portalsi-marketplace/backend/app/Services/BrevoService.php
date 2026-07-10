<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    public string $apiKey;
    public string $senderEmail;
    public string $senderName;

    public function __construct()
    {
        $this->apiKey      = Setting::get('brevo_api_key', config('services.brevo.api_key', ''));
        $this->senderEmail = Setting::get('brevo_sender_email', config('services.brevo.sender_email', 'noreply@mpsi.id'));
        $this->senderName  = Setting::get('brevo_sender_name', config('services.brevo.sender_name', Setting::get('app_name', 'MPSI')));
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Kirim email transaksional via Brevo API.
     * Mengembalikan true jika berhasil; log saja jika gagal (tidak block flow user).
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        if (!$this->isConfigured()) {
            // Fallback: log saja kalau Brevo belum dikonfigurasi (jadi flow user tetap jalan)
            Log::info("[Brevo:disabled] To: {$toEmail} | Subject: {$subject}");
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'api-key' => $this->apiKey,
                    'accept'  => 'application/json',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => ['email' => $this->senderEmail, 'name' => $this->senderName],
                    'to'     => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
                    'subject'=> $subject,
                    'htmlContent' => $htmlBody,
                    'textContent' => $textBody ?? strip_tags($htmlBody),
                ]);

            if (!$response->successful()) {
                Log::warning('[Brevo:error] ' . $response->body());
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('[Brevo:exception] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper: bungkus body dengan layout HTML standar (logo, color palette dari Settings).
     */
    public function layout(string $title, string $contentHtml, ?string $ctaUrl = null, ?string $ctaLabel = null): string
    {
        $appName  = Setting::get('app_name', 'MPSI');
        $primary  = Setting::get('primary_color', '#0a0a0a');
        $textFg   = Setting::get('primary_fg', '#ffffff');
        $cta = '';
        if ($ctaUrl && $ctaLabel) {
            $cta = "<a href='" . htmlspecialchars($ctaUrl, ENT_QUOTES) . "' style='display:inline-block;padding:14px 28px;background:{$primary};color:{$textFg};text-decoration:none;border-radius:999px;font-weight:600;font-size:14px;margin-top:16px;'>" . htmlspecialchars($ctaLabel) . "</a>";
        }
        $initial = strtoupper(mb_substr(trim($appName), 0, 1) ?: 'M');
        $logoBlock = "<table role='presentation' cellpadding='0' cellspacing='0'><tr>
            <td style='width:44px;height:44px;border-radius:12px;background:{$primary};color:{$textFg};font-size:20px;font-weight:800;text-align:center;vertical-align:middle;line-height:44px;'>" . htmlspecialchars($initial) . "</td>
            <td style='padding-left:12px;font-size:18px;font-weight:800;color:#111;vertical-align:middle;'>" . htmlspecialchars($appName) . "</td>
          </tr></table>";

        return "<!doctype html>
<html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:24px;background:#f5f5f5;font-family:-apple-system,Segoe UI,Inter,Roboto,sans-serif;color:#1a1a1a;'>
  <table role='presentation' width='100%' style='max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;'>
    <tr><td style='padding:24px 24px 8px;'>{$logoBlock}</td></tr>
    <tr><td style='padding:8px 24px 24px;'>
      <h1 style='font-size:22px;margin:8px 0 16px;'>" . htmlspecialchars($title) . "</h1>
      <div style='font-size:14px;line-height:1.6;color:#404040;'>{$contentHtml}</div>
      {$cta}
    </td></tr>
    <tr><td style='padding:16px 24px;background:#fafafa;font-size:11px;color:#888;border-top:1px solid #eee;'>
      Email otomatis dari {$appName}. Jangan balas email ini.
    </td></tr>
  </table>
</body></html>";
    }
}
