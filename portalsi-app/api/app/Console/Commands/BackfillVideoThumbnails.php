<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Scan & generate thumbnail untuk video LAMA yang:
 *   - belum punya thumbnail_url, ATAU
 *   - thumbnail_url mengarah ke gambar fallback/placeholder, ATAU
 *   - file thumbnail-nya sudah rusak/hilang di storage.
 *
 * Mengambil 1 frame (default detik ke-1) via ffmpeg lalu unggah ke storage
 * (R2/S3) dengan konvensi path yang dikenali controller:
 *   uploads/posts/thumbnails/{namaVideo}.jpg
 *
 * WAJIB ffmpeg terpasang di server. Jalankan lewat SSH, contoh:
 *   sudo -u portalsi php8.3 artisan thumbnails:backfill --dry-run   # lihat dulu apa yang akan diproses
 *   sudo -u portalsi php8.3 artisan thumbnails:backfill             # jalankan sungguhan
 *   sudo -u portalsi php8.3 artisan thumbnails:backfill --force --limit=200
 */
class BackfillVideoThumbnails extends Command
{
    protected $signature = 'thumbnails:backfill
        {--dry-run : Hanya laporkan apa yang AKAN diproses, tanpa mengubah apa pun}
        {--force : Regenerate walau thumbnail sudah ada & valid}
        {--limit=0 : Batasi jumlah video yang diproses (0 = tanpa batas)}
        {--second=1 : Detik frame yang diambil sebagai thumbnail}
        {--width=640 : Lebar thumbnail (tinggi mengikuti rasio)}
        {--chunk=200 : Ukuran chunk query DB}';

    protected $description = 'Scan & generate thumbnail untuk video lama yang belum punya / rusak / pakai placeholder.';

    private string $disk;
    private string $ffmpeg = '';

    public function handle(): int
    {
        $this->disk = (string) config('filesystems.default', 'public');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));
        $second = max(0, (int) $this->option('second'));
        $width = max(64, (int) $this->option('width'));
        $chunk = max(10, (int) $this->option('chunk'));

        // ---- Pastikan ffmpeg ada (kecuali dry-run) --------------------------
        if (! \App\Services\MediaVariantService::shellAvailable()) {
            $this->error('shell_exec dimatikan pada PHP ini (disable_functions). Jalankan lewat PHP CLI yang mengizinkannya.');

            return self::FAILURE;
        }
        $this->ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        if (! $dryRun && ($this->ffmpeg === '' || ! @is_executable($this->ffmpeg))) {
            $this->error('ffmpeg tidak ditemukan di server. Install dulu: sudo apt-get install -y ffmpeg');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('=== Backfill Thumbnail Video ===');
        $this->line('  Disk penyimpanan : '.$this->disk);
        $this->line('  Mode             : '.($dryRun ? 'DRY-RUN (tidak menyimpan)' : 'LIVE'));
        $this->line('  Regenerate paksa : '.($force ? 'YA' : 'tidak'));
        $this->line('  Frame diambil    : detik ke-'.$second.', lebar '.$width.'px');
        $this->line('  ffmpeg           : '.($this->ffmpeg !== '' ? $this->ffmpeg : '(tidak dicek pada dry-run)'));

        // ---- Hitung total kandidat kasar (video posts) ----------------------
        $totalVideos = Post::where('is_video', true)
            ->whereNotNull('media_url')
            ->where('media_url', '!=', '')
            ->count();
        $this->line('  Total post video : '.$totalVideos);
        $this->line('');

        if ($totalVideos === 0) {
            $this->warn('Tidak ada post video sama sekali. Selesai.');

            return self::SUCCESS;
        }

        $stats = [
            'scanned' => 0,
            'ok_skip' => 0,      // sudah punya thumbnail valid
            'candidate' => 0,    // butuh diproses
            'generated' => 0,
            'failed' => 0,
            'no_video_file' => 0,
        ];
        $startedAll = microtime(true);
        $index = 0;
        $stop = false;

        Post::where('is_video', true)
            ->whereNotNull('media_url')
            ->where('media_url', '!=', '')
            ->orderBy('post_id')
            ->chunkById($chunk, function ($posts) use (
                &$stats, &$index, &$stop, $dryRun, $force, $limit, $second, $width, $totalVideos
            ) {
                foreach ($posts as $post) {
                    if ($stop) {
                        return false;
                    }
                    $stats['scanned']++;
                    $index++;
                    $pos = str_pad((string) $index, strlen((string) $totalVideos), ' ', STR_PAD_LEFT);
                    $tag = "[{$pos}/{$totalVideos}] post_id={$post->post_id}";

                    $videoPath = $this->relativePath((string) $post->media_url);
                    if (! $videoPath) {
                        $stats['failed']++;
                        $this->line("{$tag}  <fg=red>GAGAL</> media_url tidak dikenali");
                        continue;
                    }

                    // Sudah punya thumbnail valid & bukan placeholder?
                    $reason = $this->needsThumbnail($post);
                    if (! $force && $reason === null) {
                        $stats['ok_skip']++;
                        if ($this->output->isVerbose()) {
                            $this->line("{$tag}  <fg=gray>lewati</> thumbnail sudah valid");
                        }
                        continue;
                    }

                    $stats['candidate']++;
                    $why = $force ? 'force' : $reason;

                    if ($dryRun) {
                        $this->line("{$tag}  <fg=yellow>AKAN DIPROSES</> ({$why})");
                        if ($limit > 0 && $stats['candidate'] >= $limit) {
                            $stop = true;

                            return false;
                        }

                        continue;
                    }

                    $t0 = microtime(true);
                    $this->output->write("{$tag}  ({$why}) … ");
                    $result = $this->generate($post, $videoPath, $second, $width);
                    $ms = (int) round((microtime(true) - $t0) * 1000);

                    if ($result === true) {
                        $stats['generated']++;
                        $this->line("<fg=green>OK</> {$ms}ms → {$post->thumbnail_url}");
                    } elseif ($result === 'no_file') {
                        $stats['no_video_file']++;
                        $this->line("<fg=red>GAGAL</> {$ms}ms (file video tidak ada di storage)");
                    } else {
                        $stats['failed']++;
                        $this->line("<fg=red>GAGAL</> {$ms}ms ({$result})");
                    }

                    if ($limit > 0 && $stats['generated'] >= $limit) {
                        $stop = true;

                        return false;
                    }
                }

                return ! $stop;
            }, 'post_id');

        $elapsed = round(microtime(true) - $startedAll, 1);
        $this->line('');
        $this->info('=== Selesai dalam '.$elapsed.'s ===');
        $this->line('  Dipindai             : '.$stats['scanned']);
        $this->line('  Sudah valid (lewati) : '.$stats['ok_skip']);
        $this->line('  Kandidat             : '.$stats['candidate']);
        if (! $dryRun) {
            $this->line('  Berhasil dibuat      : <fg=green>'.$stats['generated'].'</>');
            $this->line('  File video hilang    : '.$stats['no_video_file']);
            $this->line('  Gagal                : '.($stats['failed'] > 0 ? '<fg=red>'.$stats['failed'].'</>' : '0'));
        } else {
            $this->line('  (dry-run: tidak ada perubahan disimpan)');
        }
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Kenapa post ini butuh thumbnail? Return alasan (string) atau null kalau sudah valid.
     */
    private function needsThumbnail(Post $post): ?string
    {
        $thumb = (string) ($post->thumbnail_url ?? '');
        if ($thumb === '') {
            return 'belum ada thumbnail';
        }
        // Placeholder / gambar fallback / bukan aset upload.
        if (preg_match('#placeholder|/img/#i', $thumb)) {
            return 'pakai placeholder';
        }
        $rel = $this->relativePath($thumb);
        if (! $rel || ! str_contains($rel, 'uploads/')) {
            return 'thumbnail bukan aset upload';
        }
        if (! $this->safeExists($rel)) {
            return 'file thumbnail hilang';
        }

        return null;
    }

    /**
     * @return true|string  true=sukses; selain itu string alasan gagal ('no_file' khusus).
     */
    private function generate(Post $post, string $videoPath, int $second, int $width)
    {
        if (! $this->safeExists($videoPath)) {
            return 'no_file';
        }

        $ext = pathinfo($videoPath, PATHINFO_EXTENSION) ?: 'mp4';
        $tmpVideo = tempnam(sys_get_temp_dir(), 'psi_vid_').'.'.$ext;
        $tmpThumb = tempnam(sys_get_temp_dir(), 'psi_thumb_').'.jpg';

        try {
            $stream = Storage::disk($this->disk)->readStream($videoPath);
            if (! $stream) {
                return 'gagal baca video';
            }
            $out = fopen($tmpVideo, 'wb');
            stream_copy_to_stream($stream, $out);
            fclose($out);
            if (is_resource($stream)) {
                fclose($stream);
            }

            // Ambil 1 frame pada detik yang diminta. -ss sebelum -i = seek cepat.
            $cmd = sprintf(
                '%s -y -ss %d -i %s -frames:v 1 -vf %s -q:v 3 %s 2>/dev/null',
                escapeshellarg($this->ffmpeg),
                $second,
                escapeshellarg($tmpVideo),
                escapeshellarg("scale={$width}:-2"),
                escapeshellarg($tmpThumb)
            );
            @shell_exec($cmd);

            // Kalau frame di detik itu kosong (durasi video < detik itu), fallback ke frame awal.
            if (! file_exists($tmpThumb) || filesize($tmpThumb) < 100) {
                $cmd0 = sprintf(
                    '%s -y -i %s -frames:v 1 -vf %s -q:v 3 %s 2>/dev/null',
                    escapeshellarg($this->ffmpeg),
                    escapeshellarg($tmpVideo),
                    escapeshellarg("scale={$width}:-2"),
                    escapeshellarg($tmpThumb)
                );
                @shell_exec($cmd0);
            }

            if (! file_exists($tmpThumb) || filesize($tmpThumb) < 100) {
                return 'ffmpeg gagal ekstrak frame';
            }

            $nameOnly = pathinfo($videoPath, PATHINFO_FILENAME);
            $thumbPath = "uploads/posts/thumbnails/{$nameOnly}.jpg";
            Storage::disk($this->disk)->put($thumbPath, file_get_contents($tmpThumb), 'public');

            $post->thumbnail_url = Storage::disk($this->disk)->url($thumbPath);
            $post->save();

            return true;
        } catch (\Throwable $e) {
            return 'exception: '.$e->getMessage();
        } finally {
            @unlink($tmpVideo);
            @unlink($tmpThumb);
        }
    }

    private function safeExists(string $path): bool
    {
        try {
            return Storage::disk($this->disk)->exists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function relativePath(string $url): ?string
    {
        if (preg_match('#https?://[^/]+/(.*)$#', $url, $m)) {
            return $m[1];
        }
        if (str_starts_with($url, '/storage/')) {
            return substr($url, 9);
        }

        return ltrim($url, '/') ?: null;
    }
}
