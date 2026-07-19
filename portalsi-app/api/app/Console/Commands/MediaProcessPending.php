<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\MediaVariantService;
use Illuminate\Console\Command;

/**
 * Memproses upload BARU yang ditandai media_status='pending'.
 * Dijadwalkan cron tiap menit (lihat Console\Kernel). Memproses sedikit per run
 * agar ringan; sisa antrean tergarap pada run berikutnya.
 *
 *   php artisan media:process-pending --limit=5
 */
class MediaProcessPending extends Command
{
    protected $signature = 'media:process-pending
        {--limit=5 : Maksimal post pending yang diproses per run}';

    protected $description = 'Generate varian media untuk upload baru (media_status=pending).';

    public function handle(MediaVariantService $svc): int
    {
        if (! $svc->hasFfmpeg()) {
            $this->warn('ffmpeg/ffprobe tidak tersedia — pending dilewati.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $posts = Post::where('media_status', 'pending')
            ->whereNotNull('media_url')
            ->where('media_url', '!=', '')
            ->orderBy('post_id')
            ->limit($limit)
            ->get();

        if ($posts->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($posts as $post) {
            try {
                $added = $svc->processPost($post);
                $this->line("post {$post->post_id}: OK (+".MediaVariantService::humanBytes($added).')');
            } catch (\Throwable $e) {
                $post->media_status = 'failed';
                $post->save();
                $this->line("post {$post->post_id}: GAGAL ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
