<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Hapus PERMANEN postingan yang sudah dimoderasi lebih dari masa retensi (default 30 hari).
 *
 *   php artisan moderation:purge --scan   → hanya lihat berapa yang akan dihapus
 *   php artisan moderation:purge          → hapus (beserta media & relasinya via destroy)
 *
 * Dijadwalkan harian lewat scheduler. Idempoten.
 */
class ModerationPurge extends Command
{
    protected $signature = 'moderation:purge {--days=30 : Masa retensi (hari)} {--scan : Hanya hitung, tidak menghapus}';

    protected $description = 'Hapus permanen postingan termoderasi yang melewati masa retensi.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = Post::whereNotNull('moderated_at')->where('moderated_at', '<', $cutoff);
        $total = (clone $query)->count();

        $this->info("Postingan termoderasi > {$days} hari: {$total}");
        if ($this->option('scan')) {
            $this->line('Mode scan — tidak ada yang dihapus.');

            return self::SUCCESS;
        }
        if ($total === 0) {
            return self::SUCCESS;
        }

        $deleted = 0;
        $query->orderBy('post_id')->chunkById(100, function ($posts) use (&$deleted) {
            foreach ($posts as $post) {
                // delete() model → cascade relasi (likes/comments/dll via FK) + observer bila ada.
                $post->delete();
                $deleted++;
            }
        }, 'post_id');

        $this->info("Dihapus permanen: {$deleted}");

        return self::SUCCESS;
    }
}
