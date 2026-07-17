<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disiarkan saat seseorang membuka & membaca pesan DM dari lawan bicara.
 * Pengirim (otherId) memakai ini untuk mengubah centangnya jadi biru secara realtime.
 */
class MessageRead implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $readerId, public int $otherId) {}

    public function broadcastOn(): array
    {
        $ids = [$this->readerId, $this->otherId];
        sort($ids);

        return [new PrivateChannel('dm.'.implode('-', $ids))];
    }

    public function broadcastAs(): string
    {
        return 'dm.read';
    }

    public function broadcastWith(): array
    {
        return ['reader_id' => $this->readerId];
    }
}
