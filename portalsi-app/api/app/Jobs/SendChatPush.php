<?php

namespace App\Jobs;

use App\Models\DirectMessage;
use App\Services\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Kirim Web Push untuk satu pesan langsung (DM) ke penerima. Dijalankan via queue.
 */
class SendChatPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public int $messageId)
    {
    }

    public function handle(): void
    {
        $msg = DirectMessage::with('sender')->find($this->messageId);
        if (! $msg || $msg->is_read) {
            return; // sudah dibaca sebelum push terkirim → lewati
        }

        $sender = $msg->sender;
        $name = $sender ? ($sender->full_name ?: $sender->username) : 'Pesan baru';
        $appUrl = (string) config('webpush.app_url');

        $body = trim((string) ($msg->content ?? ''));
        if ($body === '') {
            $body = $msg->media_url ? '📎 Mengirim media' : 'Mengirim pesan';
        }

        WebPushSender::sendToUser((int) $msg->receiver_id, [
            'title' => $name,
            'body' => $body,
            'url' => $appUrl.'/messages/direct/'.$msg->sender_id,
            // Ikon pakai avatar pengirim bila ada, agar terasa personal.
            'icon' => $sender && $sender->profile_picture_thumb_url
                ? $sender->profile_picture_thumb_url
                : (string) config('webpush.icon'),
            'badge' => (string) config('webpush.badge'),
            // Satu tag per pengirim → pesan berturut-turut menumpuk jadi satu notifikasi.
            'tag' => 'portalsi-chat-'.$msg->sender_id,
        ]);
    }
}
