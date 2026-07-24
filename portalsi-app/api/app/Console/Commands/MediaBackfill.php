<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\MediaVariantService;
use Illuminate\Console\Command;

/**
 * EXECUTE. Buat rendition video (rendah/sedang sesuai resolusi) + thumbnail,
 * lalu simpan ke media_variants. Foto: buat thumbnail square. Idempotent —
 * post yang sudah 'done' dilewati kecuali --force.
 *
 * Jalankan media:scan DULU untuk melihat estimasi. Lalu:
 *   sudo -u portalsi php8.3 artisan media:backfill --limit=20   # uji dulu
 *   sudo -u portalsi php8.3 artisan media:backfill              # semua
 *   sudo -u portalsi php8.3 artisan media:backfill --force      # bangun ulang
 */
class MediaBackfill extends Command
{
    protected $signature = 'media:backfill
        {--limit=0 : Batasi jumlah post yang diproses (0 = semua)}
        {--force : Bangun ulang walau sudah done}
        {--chunk=100 : Ukuran chunk query DB}';

    protected $description = 'Generate rendition video + thumbnail dan simpan media_variants (idempotent).';

    public function handle(MediaVariantService $svc): int
    {
        if (! $svc->hasFfmpeg()) {
            $this->error('ffmpeg/ffprobe tidak tersedia. Install: sudo apt-get install -y ffmpeg');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $chunk = max(10, (int) $this->option('chunk'));

        $query = Post::whereNotNull('media_url')->where('media_url', '!=', '');
        if (! $force) {
            // Belum 'done' ATAU thumbnail_url masih kosong (post lama yang thumbnailnya
            // tak pernah terisi). processPost idempotent → rendition yang sudah ada dilewati,
            // jadi ini tetap ringan; hanya menutup lubang thumbnail.
            $query->where(function ($q) {
                $q->whereNull('media_status')
                    ->orWhere('media_status', '!=', 'done')
                    ->orWhereNull('thumbnail_url')
                    ->orWhere('thumbnail_url', '');
            });
        }
        $total = (clone $query)->count();

        $this->line('');
        $this->info('=== Media Backfill (EXECUTE) ===');
        $this->line('  Disk        : '.$svc->disk);
        $this->line('  Target post : '.$total.($force ? ' (--force: semua)' : ' (belum done)'));
        $this->line('');

        $s = ['done' => 0, 'failed' => 0, 'skipped' => 0, 'added_bytes' => 0];
        $index = 0;
        $stop = false;

        $query->orderBy('post_id')->chunkById($chunk, function ($posts) use (
            &$s, &$index, &$stop, $svc, $limit, $force, $total
        ) {
            foreach ($posts as $post) {
                if ($stop) {
                    return false;
                }
                $index++;
                $pos = str_pad((string) $index, strlen((string) $total), ' ', STR_PAD_LEFT);
                $tag = "[{$pos}/{$total}] post={$post->post_id}";

                $key = $svc->relativePath($post->media_url);
                if (! $key) {
                    $s['failed']++;
                    $this->line("{$tag}  <fg=red>SKIP</> media_url tak dikenali");
                    continue;
                }

                $t0 = microtime(true);
                $this->output->write("{$tag} … ");
                try {
                    $added = $svc->processPost($post, $force);
                    $s['added_bytes'] += $added;
                    $s['done']++;
                    $ms = (int) round((microtime(true) - $t0) * 1000);
                    $this->line("<fg=green>OK</> {$ms}ms (+".MediaVariantService::humanBytes($added).')');
                } catch (\Throwable $e) {
                    $s['failed']++;
                    $post->media_status = 'failed';
                    $post->save();
                    $this->line('<fg=red>GAGAL</> '.$e->getMessage());
                }

                if ($limit > 0 && $s['done'] >= $limit) {
                    $stop = true;

                    return false;
                }
            }

            return ! $stop;
        }, 'post_id');

        $this->line('');
        $this->info('=== Selesai ===');
        $this->line('  Berhasil        : <fg=green>'.$s['done'].'</>');
        $this->line('  Gagal           : '.($s['failed'] > 0 ? '<fg=red>'.$s['failed'].'</>' : '0'));
        $this->line('  Storage ditambah: ~'.MediaVariantService::humanBytes($s['added_bytes']));
        $this->line('');

        return self::SUCCESS;
    }
}
