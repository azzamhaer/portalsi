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
            $query->where(fn ($q) => $q->whereNull('media_status')->orWhere('media_status', '!=', 'done'));
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
                    $added = $this->process($svc, $post, $key, $force);
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

    /** Proses satu post; return jumlah byte baru yang diunggah. */
    private function process(MediaVariantService $svc, Post $post, string $key, bool $force): int
    {
        $post->media_status = 'processing';
        $post->save();

        $isVideo = (bool) $post->is_video || $svc->isVideoUrl($post->media_url);
        $ext = pathinfo($key, PATHINFO_EXTENSION) ?: ($isVideo ? 'mp4' : 'jpg');
        $src = $svc->downloadToTemp($key, '.'.$ext);
        if (! $src) {
            throw new \RuntimeException('gagal download asli dari storage');
        }

        $tmpFiles = [$src];
        $added = 0;
        try {
            $probe = $svc->probe($src);
            $origBytes = $svc->size($key) ?? @filesize($src) ?: null;
            $variants = is_array($post->media_variants) && ! $force ? $post->media_variants : [];

            $variants['original'] = array_filter([
                'url' => $post->media_url,
                'key' => $key,
                'w' => $probe['w'] ?? null,
                'h' => $probe['h'] ?? null,
                'bytes' => $origBytes,
            ], fn ($v) => $v !== null);

            // Thumbnail (video: frame; foto: crop tengah).
            $thumbKey = $svc->thumbKey($key);
            if ($force || ! isset($variants['thumbnail']) || ! $svc->exists($thumbKey)) {
                $tThumb = tempnam(sys_get_temp_dir(), 'psi_thumb_').'.jpg';
                $tmpFiles[] = $tThumb;
                if ($svc->makeThumbnail($src, $tThumb, $isVideo)) {
                    if ($svc->upload($tThumb, $thumbKey)) {
                        $b = @filesize($tThumb) ?: null;
                        $added += (int) $b;
                        $variants['thumbnail'] = array_filter([
                            'url' => $svc->publicUrl($thumbKey),
                            'key' => $thumbKey,
                            'w' => MediaVariantService::THUMB,
                            'h' => MediaVariantService::THUMB,
                            'bytes' => $b,
                        ], fn ($v) => $v !== null);
                        $post->thumbnail_url = $variants['thumbnail']['url'];
                    }
                }
            }

            // Rendition video.
            if ($isVideo && $probe) {
                foreach ($svc->neededVideoRenditions($probe['w'], $probe['h']) as $q => $h) {
                    $vKey = $svc->variantKey($key, $q);
                    if (! $force && isset($variants[$q]) && $svc->exists($vKey)) {
                        continue;
                    }
                    $tOut = tempnam(sys_get_temp_dir(), "psi_{$q}_").'.mp4';
                    $tmpFiles[] = $tOut;
                    if ($svc->transcodeTo($src, $h, $tOut) && $svc->upload($tOut, $vKey)) {
                        $b = @filesize($tOut) ?: null;
                        $added += (int) $b;
                        $rp = $svc->probe($tOut);
                        $variants[$q] = array_filter([
                            'url' => $svc->publicUrl($vKey),
                            'key' => $vKey,
                            'w' => $rp['w'] ?? null,
                            'h' => $rp['h'] ?? $h,
                            'bytes' => $b,
                        ], fn ($v) => $v !== null);
                    }
                }
            }

            $post->media_variants = $variants;
            $post->media_status = 'done';
            $post->save();

            return $added;
        } finally {
            foreach ($tmpFiles as $f) {
                @unlink($f);
            }
        }
    }
}
