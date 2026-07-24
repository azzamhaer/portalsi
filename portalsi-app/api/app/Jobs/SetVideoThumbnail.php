<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\MediaVariantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Menetapkan thumbnail video ke frame pilihan user (detik tertentu). Dijalankan lewat
 * QUEUE (worker CLI) — penting karena banyak konfigurasi PHP-FPM (HestiaCP) mematikan
 * shell_exec di request web, sementara CLI worker mengizinkannya. Jadi ekstraksi ffmpeg
 * dikerjakan di sini, bukan sinkron di controller.
 */
class SetVideoThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public int $postId, public float $second)
    {
    }

    public function handle(MediaVariantService $svc): void
    {
        $post = Post::find($this->postId);
        if (! $post || empty($post->media_url)) {
            return;
        }

        $url = $svc->extractVideoFrameThumbnail($post, $this->second);
        if (! $url) {
            Log::warning('SetVideoThumbnail: ekstraksi gagal', [
                'post_id' => $this->postId,
                'second' => $this->second,
            ]);

            return;
        }

        $post->thumbnail_url = $url;
        $post->has_custom_thumbnail = true;
        $variants = is_array($post->media_variants) ? $post->media_variants : [];
        unset($variants['thumbnail']);
        $post->media_variants = $variants;
        $post->save();

        Log::info('SetVideoThumbnail: thumbnail diperbarui', ['post_id' => $this->postId]);
    }
}
