<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AvatarThumbnailService;
use Illuminate\Console\Command;

/**
 * Buat thumbnail untuk foto profil yang SUDAH ADA (belum punya thumbnail).
 *
 * Jalankan sekali setelah deploy:  php artisan avatars:thumbnail
 * Idempotent & bisa diulang: hanya memproses user yang punya foto tapi belum ada thumbnailnya.
 */
class AvatarsThumbnail extends Command
{
    protected $signature = 'avatars:thumbnail {--limit=0 : Batasi jumlah (0 = semua)} {--force : Buat ulang walau thumbnail sudah ada}';

    protected $description = 'Generate thumbnail kecil untuk foto profil pengguna (GD, tanpa ffmpeg).';

    public function handle(AvatarThumbnailService $svc): int
    {
        if (! AvatarThumbnailService::gdAvailable()) {
            $this->error('Ekstensi GD tidak tersedia di PHP CLI ini. Pasang php-gd lalu ulangi.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));

        $query = User::query()
            ->whereNotNull('profile_picture_url')
            ->where('profile_picture_url', '!=', '');
        if (! $force) {
            $query->whereNull('profile_picture_thumb_url');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Tidak ada foto profil yang perlu diproses.');

            return self::SUCCESS;
        }

        $this->info("Memproses {$total} foto profil…");
        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
        $bar->start();

        $done = 0;
        $failed = 0;
        $query->orderBy('user_id')->chunkById(100, function ($users) use ($svc, &$done, &$failed, &$bar, $limit) {
            foreach ($users as $user) {
                if ($limit > 0 && $done >= $limit) {
                    return false;
                }
                $thumb = $svc->generateFromUrl($user->profile_picture_url);
                if ($thumb) {
                    // Simpan tanpa memicu event/append berat.
                    User::where('user_id', $user->user_id)->update(['profile_picture_thumb_url' => $thumb]);
                    $done++;
                } else {
                    $failed++;
                }
                $bar->advance();
            }

            return true;
        }, 'user_id');

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai. Berhasil: {$done}, gagal: {$failed}.");

        return self::SUCCESS;
    }
}
