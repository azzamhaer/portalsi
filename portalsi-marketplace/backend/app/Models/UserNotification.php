<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'message', 'action_url', 'severity', 'payload', 'read_at'];
    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * Helper static untuk kirim notifikasi cepat dari mana saja.
     * Setelah simpan ke DB, juga akan dikirim email via BrevoService (kalau dikonfigurasi).
     */
    public static function send(int $userId, string $type, string $title, string $message, ?string $actionUrl = null, string $severity = 'INFO', array $payload = []): self
    {
        $notif = self::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'action_url' => $actionUrl,
            'severity'   => $severity,
            'payload'    => $payload,
        ]);
        // Best-effort email
        try {
            $user = User::find($userId);
            if ($user && $user->email) {
                $brevo = new \App\Services\BrevoService();
                $front = rtrim(config('services.frontend_url', 'http://localhost:5173'), '/');
                $url = $front . '/notifications/' . $notif->id;
                $brevo->send($user->email, $user->name, $title, $brevo->layout(
                    $title,
                    "<p>Hai <b>" . htmlspecialchars($user->name) . "</b>,</p><p>" . nl2br(htmlspecialchars($message)) . "</p>",
                    $url, 'Lihat detail'
                ));
            }
        } catch (\Throwable $e) {
            \Log::warning('Notification email failed: ' . $e->getMessage());
        }
        return $notif;
    }
}
