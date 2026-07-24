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

        $subs = PushSubscription::where('user_id', $notif->recipient_id)->get();
        if ($subs->isEmpty()) {
            return;
        }

        $payload = json_encode($this->buildPayload($notif));

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('webpush.vapid.subject'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ], [], 10);
            $webPush->setDefaultOptions(['TTL' => (int) config('webpush.ttl', 43200)]);
        } catch (\Throwable $e) {
            Log::warning('SendWebPush: gagal inisialisasi WebPush', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($subs as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }
            // Endpoint kadaluarsa / tak dikenal → buang langganannya.
            $status = method_exists($report, 'getResponse') && $report->getResponse()
                ? $report->getResponse()->getStatusCode()
                : null;
            if (in_array($status, [404, 410], true) || $report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
            }
        }
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
