<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\MediaVariantService;
use Illuminate\Console\Command;

/**
 * Verifikasi thumbnail tiap post langsung di R2 (cek magic-number, bukan lewat CDN),
 * lalu regenerasi HANYA yang korup/hilang. Ringan — tidak menyentuh rendition video.
 *
 *   php artisan thumbnails:repair --scan          → hanya laporkan (tidak mengubah)
 *   php artisan thumbnails:repair                 → perbaiki yang rusak
 *   php artisan thumbnails:repair --limit=200
 *
 * DIAGNOSTIK: kalau --scan melaporkan hampir semua VALID padahal di browser 500,
 * berarti objek di R2 sehat → biang keroknya CLOUDFLARE (Polish/optimasi), bukan file.
 */
class RepairThumbnails extends Command
{
    protected $signature = 'thumbnails:repair
        {--scan : Hanya periksa & laporkan, tanpa mengubah}
        {--limit=0 : Batasi jumlah post yang diperiksa (0 = semua)}
        {--chunk=200 : Ukuran chunk query}';

    protected $description = 'Cek thumbnail post di R2 & regenerasi hanya yang korup/hilang.';

    public function handle(MediaVariantService $svc): int
    {
        $scan = (bool) $this->option('scan');
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(20, (int) $this->option('chunk'));

        if (! $scan && ! $svc->hasFfmpeg()) {
            $this->error('ffmpeg/ffprobe tidak tersedia. Gunakan --scan untuk sekadar memeriksa.');

            return self::FAILURE;
        }

        $s = ['valid' => 0, 'fixed' => 0, 'failed' => 0, 'missing' => 0, 'checked' => 0];
        $stop = false;

        Post::whereNotNull('media_url')->where('media_url', '!=', '')
            ->orderByDesc('post_id')
            ->chunkById($chunk, function ($posts) use ($svc, $scan, $limit, &$s, &$stop) {
                foreach ($posts as $post) {
                    if ($stop) {
                        return false;
                    }
                    $s['checked']++;

                    // Key thumbnail yang seharusnya disajikan.
                    $variants = is_array($post->media_variants) ? $post->media_variants : [];
                    $thumbKey = ! empty($variants['thumbnail']['key'])
                        ? $variants['thumbnail']['key']
                        : (($k = $svc->relativePath($post->media_url)) ? $svc->thumbKey($k) : null);

                    if (! $thumbKey) {
                        $s['failed']++;
                        continue;
                    }

                    if ($svc->isValidImageObject($thumbKey)) {
                        $s['valid']++;
                        continue;
                    }

                    // Korup / hilang.
                    $s['missing']++;
                    $this->line("post={$post->post_id}  <fg=yellow>RUSAK/HILANG</> {$thumbKey}");
                    if ($scan) {
                        continue;
                    }

                    $r = $svc->regenerateThumbnailOnly($post);
                    if ($r === 'ok') {
                        $s['fixed']++;
                        $this->line("  → <fg=green>diperbaiki</>");
                    } else {
                        $s['failed']++;
                        $this->line("  → <fg=red>gagal ({$r})</>");
                    }

                    if ($limit > 0 && ($s['fixed'] + $s['failed']) >= $limit) {
                        $stop = true;

                        return false;
                    }
                }
            });

        $this->line('');
        $this->info('=== Ringkasan ===');
        $this->line("  Diperiksa : {$s['checked']}");
        $this->line("  Valid     : {$s['valid']}");
        $this->line("  Rusak/hilang: {$s['missing']}");
        if (! $scan) {
            $this->line("  Diperbaiki: {$s['fixed']}");
            $this->line("  Gagal     : {$s['failed']}");
        }
        $this->line('');
        if ($scan && $s['missing'] === 0 && $s['valid'] > 0) {
            $this->warn('Semua thumbnail VALID di R2. Kalau di browser tetap 500 → penyebabnya Cloudflare (Polish/optimasi gambar), bukan file. Nonaktifkan Polish untuk path thumbnail.');
        }

        return self::SUCCESS;
    }
}
