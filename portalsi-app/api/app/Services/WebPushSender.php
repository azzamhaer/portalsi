<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Pengirim Web Push bersama (dipakai job notifikasi & chat). Mengirim payload ke semua
 * perangkat seorang user dan membuang langganan yang sudah mati (404/410).
 */
class WebPushSender
{
    /**
     * @param  array{title:string,body:string,url?:string,icon?:string,badge?:string,tag?:string}  $payload
     */
    public static function sendToUser(int $userId, array $payload): void
    {
        $publicKey = (string) config('webpush.vapid.public_key');
        $privateKey = (string) config('webpush.vapid.private_key');
        if ($publicKey === '' || $privateKey === '') {
            return; // VAPID belum dikonfigurasi
        }

        $subs = PushSubscription::where('user_id', $userId)->get();
        if ($subs->isEmpty()) {
            return;
        }

        $json = json_encode($payload);

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
            Log::warning('WebPushSender: gagal inisialisasi', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($subs as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
                ]),
                $json
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }
            $status = method_exists($report, 'getResponse') && $report->getResponse()
                ? $report->getResponse()->getStatusCode()
                : null;
            if (in_array($status, [404, 410], true) || $report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
            }
        }
    }
}
