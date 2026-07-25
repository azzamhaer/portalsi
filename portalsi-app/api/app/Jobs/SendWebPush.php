<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Mengirim Web Push untuk SATU notifikasi ke semua perangkat penerima.
 * Dijalankan lewat queue (worker CLI). Langganan yang sudah mati (404/410) dihapus.
 */
class SendWebPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(): void
    {
        $publicKey = (string) config('webpush.vapid.public_key');
        $privateKey = (string) config('webpush.vapid.private_key');
        if ($publicKey === '' || $privateKey === '') {
            return; // VAPID belum dikonfigurasi
        }

        $notif = Notification::with('sender')->find($this->notificationId);
        if (! $notif) {
            return;
        }

        \App\Services\WebPushSender::sendToUser((int) $notif->recipient_id, $this->buildPayload($notif));
    }

    /**
     * Bentuk isi notifikasi (judul, teks, tautan) sesuai tipe.
     */
    private function buildPayload(Notification $notif): array
    {
        $sender = $notif->sender;
        $name = $sender ? ($sender->full_name ?: $sender->username) : 'Portal SI';
        $appUrl = (string) config('webpush.app_url');

        $title = $name;
        $body = $notif->message ?: 'Ada aktivitas baru untukmu.';
        $url = $appUrl.'/notifications';

        switch ($notif->type) {
            case 'like':
                $body = 'menyukai postingan kamu';
                break;
            case 'comment':
                $body = 'mengomentari postingan kamu';
                break;
            case 'reply':
                $body = 'membalas komentar kamu';
                break;
            case 'follow':
                $body = 'mulai mengikuti kamu';
                break;
            case 'follow_request':
                $body = 'meminta mengikuti akun kamu';
                break;
            case 'follow_accepted':
                $body = 'menerima permintaan mengikuti kamu';
                break;
            case 'mention':
                $body = 'menyebut kamu di sebuah postingan atau komentar';
                break;
            case 'story_mention':
                $body = 'menyebut kamu di cerita';
                break;
            case 'collab_invite':
                $body = 'mengajak kamu berkolaborasi di sebuah postingan';
                break;
            case 'collab_accepted':
                $body = 'menerima ajakan kolaborasimu';
                break;
            case 'new_post':
                $body = 'membagikan postingan baru';
                break;
            case 'post_moderated':
                $title = 'Sistem AI · Portal SI';
                $body = 'Postingan kamu dimoderasi. Ketuk untuk melihat alasannya.';
                break;
        }

        // Deep link.
        if ($notif->type === 'post_moderated' && $notif->related_post_id) {
            $url = $appUrl.'/posts/'.$notif->related_post_id.'?moderation=1';
        } elseif ($notif->related_post_id) {
            $url = $appUrl.'/posts/'.$notif->related_post_id;
        } elseif ($notif->related_story_id && $sender) {
            $url = $appUrl.'/stories/'.$sender->user_id;
        } elseif (in_array($notif->type, ['follow', 'follow_request', 'follow_accepted'], true) && $sender) {
            $url = $appUrl.'/u/'.$sender->username;
        }

        return [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'icon' => (string) config('webpush.icon'),
            'badge' => (string) config('webpush.badge'),
            'tag' => 'portalsi-notif-'.$notif->notification_id,
        ];
    }
}
