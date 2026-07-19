<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\MediaVariantService;
use Illuminate\Console\Command;

/**
 * READ-ONLY. Scan semua post dan laporkan:
 *   - resolusi & ukuran asli tiap media,
 *   - rendition (rendah/sedang) & thumbnail yang BELUM ada,
 *   - estimasi ukuran hasil,
 *   - TOTAL storage R2 tambahan bila media:backfill dijalankan.
 *
 * Tidak mengubah apa pun. Jalankan ini DULU sebelum media:backfill.
 *
 *   sudo -u portalsi php8.3 artisan media:scan
 *   sudo -u portalsi php8.3 artisan media:scan -v            # detail per post
 *   sudo -u portalsi php8.3 artisan media:scan --limit=500   # cicil
 */
class MediaScan extends Command
{
    protected $signature = 'media:scan
        {--limit=0 : Batasi jumlah post yang diperiksa (0 = semua)}
        {--chunk=200 : Ukuran chunk query DB}';

    protected $description = 'Estimasi & detail varian media yang akan dibuat (read-only, tanpa perubahan).';

    public function handle(MediaVariantService $svc): int
    {
        if ($svc->ffprobe === '') {
            $this->warn('ffprobe tidak ditemukan — resolusi video tak bisa dibaca. Install: sudo apt-get install -y ffmpeg');
        }

        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(10, (int) $this->option('chunk'));

        $total = Post::whereNotNull('media_url')->where('media_url', '!=', '')->count();
        $this->line('');
        $this->info('=== Media Scan (READ-ONLY) ===');
        $this->line('  Disk        : '.$svc->disk);
        $this->line('  Total post  : '.$total);
        $this->line('  ffprobe     : '.($svc->ffprobe !== '' ? $svc->ffprobe : '(tidak ada)'));
        $this->line('');

        $s = [
            'scanned' => 0,
            'videos' => 0,
            'photos' => 0,
            'orig_bytes' => 0,
            'need_low' => 0,
            'need_med' => 0,
            'need_thumb' => 0,
            'est_low_bytes' => 0,
            'est_med_bytes' => 0,
            'est_thumb_bytes' => 0,
            'probe_fail' => 0,
            'missing_file' => 0,
        ];
        $index = 0;
        $stop = false;

        Post::whereNotNull('media_url')->where('media_url', '!=', '')
            ->orderBy('post_id')
            ->chunkById($chunk, function ($posts) use (&$s, &$index, &$stop, $svc, $limit, $total) {
                foreach ($posts as $post) {
                    if ($stop) {
                        return false;
                    }
                    $s['scanned']++;
                    $index++;
                    $key = $svc->relativePath($post->media_url);
                    if (! $key) {
                        continue;
                    }
                    $isVideo = (bool) $post->is_video || $svc->isVideoUrl($post->media_url);
                    $origBytes = $svc->size($key);
                    if ($origBytes === null) {
                        $s['missing_file']++;
                    } else {
                        $s['orig_bytes'] += $origBytes;
                    }

                    $variants = is_array($post->media_variants) ? $post->media_variants : [];
                    $hasThumb = isset($variants['thumbnail'])
                        || ($post->thumbnail_url && ! preg_match('#placeholder|/img/#i', (string) $post->thumbnail_url)
                            && $svc->exists($svc->thumbKey($key)));

                    $detail = "  post {$post->post_id} ".($isVideo ? '[video]' : '[foto] ')
                        .' asli='.MediaVariantService::humanBytes($origBytes);

                    if ($isVideo) {
                        $s['videos']++;
                        $probe = $svc->probe($svc->publicUrl($key));
                        if (! $probe) {
                            $s['probe_fail']++;
                            $detail .= ' resolusi=? (probe gagal)';
                        } else {
                            $short = min($probe['w'], $probe['h']);
                            $detail .= " {$probe['w']}x{$probe['h']} (short {$short}p, ".round($probe['duration']).'s)';
                            $needed = $svc->neededVideoRenditions($probe['w'], $probe['h']);
                            foreach ($needed as $q => $h) {
                                $exists = isset($variants[$q]) || $svc->exists($svc->variantKey($key, $q));
                                if ($exists) {
                                    continue;
                                }
                                $est = $svc->estimateVideoBytes($q, $probe['duration']);
                                if ($q === 'low') {
                                    $s['need_low']++;
                                    $s['est_low_bytes'] += $est;
                                } else {
                                    $s['need_med']++;
                                    $s['est_med_bytes'] += $est;
                                }
                                $detail .= " +{$q}~".MediaVariantService::humanBytes($est);
                            }
                        }
                    } else {
                        $s['photos']++;
                    }

                    if (! $hasThumb) {
                        $s['need_thumb']++;
                        $s['est_thumb_bytes'] += MediaVariantService::THUMB_EST_BYTES;
                        $detail .= ' +thumb~'.MediaVariantService::humanBytes(MediaVariantService::THUMB_EST_BYTES);
                    }

                    if ($this->output->isVerbose()) {
                        $this->line($detail);
                    } elseif ($index % 200 === 0) {
                        $this->line("  … {$index}/{$total} diperiksa");
                    }

                    if ($limit > 0 && $s['scanned'] >= $limit) {
                        $stop = true;

                        return false;
                    }
                }

                return ! $stop;
            }, 'post_id');

        $estTotal = $s['est_low_bytes'] + $s['est_med_bytes'] + $s['est_thumb_bytes'];

        $this->line('');
        $this->info('=== RINGKASAN ===');
        $this->line('  Post diperiksa       : '.$s['scanned'].' (video: '.$s['videos'].', foto: '.$s['photos'].')');
        $this->line('  Ukuran asli total    : '.MediaVariantService::humanBytes($s['orig_bytes']));
        if ($s['missing_file'] > 0) {
            $this->line('  File asli hilang     : <fg=red>'.$s['missing_file'].'</>');
        }
        if ($s['probe_fail'] > 0) {
            $this->line('  Gagal baca resolusi  : <fg=yellow>'.$s['probe_fail'].'</> (dilewati saat estimasi rendition)');
        }
        $this->line('');
        $this->line('  Akan dibuat (belum ada):');
        $this->line('    Rendition rendah 480p : '.$s['need_low'].'  (~'.MediaVariantService::humanBytes($s['est_low_bytes']).')');
        $this->line('    Rendition sedang 720p : '.$s['need_med'].'  (~'.MediaVariantService::humanBytes($s['est_med_bytes']).')');
        $this->line('    Thumbnail             : '.$s['need_thumb'].'  (~'.MediaVariantService::humanBytes($s['est_thumb_bytes']).')');
        $this->line('');
        $this->info('  ➜ Estimasi TAMBAHAN storage R2 : ~'.MediaVariantService::humanBytes($estTotal));
        $this->line('    (angka rendition adalah perkiraan bitrate; thumbnail ~'
            .MediaVariantService::humanBytes(MediaVariantService::THUMB_EST_BYTES).'/berkas)');
        $this->line('');
        $this->line('  Untuk mengeksekusi: php artisan media:backfill  (mulai --limit kecil dulu)');
        $this->line('');

        return self::SUCCESS;
    }
}
