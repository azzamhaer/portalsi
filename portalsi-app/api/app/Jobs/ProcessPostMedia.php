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
 * Membuat varian media (thumbnail square + rendition video) untuk SATU post,
 * segera setelah upload. Di-dispatch ->afterResponse() dari PostController agar
 * user tidak menunggu, dan cron media:process-pending menjadi jaring pengaman.
 */
class ProcessPostMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public int $postId)
    {
    }

    public function handle(MediaVariantService $svc): void
    {
        $post = Post::find($this->postId);
        if (! $post || empty($post->media_url)) {
            return;
        }
        if (! $svc->hasFfmpeg()) {
            return; // biarkan cron menanganinya bila ffmpeg belum siap
        }
        try {
            $svc->processPost($post);
        } catch (\Throwable $e) {
            $post->media_status = 'failed';
            $post->save();
            Log::warning('ProcessPostMedia gagal', ['post_id' => $this->postId, 'error' => $e->getMessage()]);
        }
    }
}
