<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Logika bersama untuk membuat & memperkirakan varian media (video renditions + thumbnail,
 * serta thumbnail foto). Dipakai oleh command media:scan (read-only) dan media:backfill /
 * pemrosesan upload baru (execute).
 *
 * Aturan rendition video (berdasarkan sisi TERPENDEK / short-side supaya adil utk portrait):
 *   short >= 900  -> low(480) + medium(720) + asli   (3)
 *   short >= 600  -> low(480) + asli                  (2)
 *   short <  600  -> asli saja                        (1)
 *
 * Foto: asli + thumbnail (square, dipotong tengah). Video: + thumbnail (frame, square).
 */
class MediaVariantService
{
    public string $disk;
    public string $ffmpeg = '';
    public string $ffprobe = '';

    // Tinggi target rendition (sisi terpendek).
    public const LOW_H = 480;
    public const MED_H = 720;

    // Ambang short-side agar rendition tidak dibuat bila hampir sama dgn asli.
    public const LOW_MIN_SHORT = 600;
    public const MED_MIN_SHORT = 900;

    // Ukuran thumbnail square.
    public const THUMB = 480;

    // Estimasi bitrate (bps) untuk perkiraan ukuran output.
    public const BITRATE_LOW = 1_150_000;   // ~1.0 Mbps video + 128k audio
    public const BITRATE_MED = 2_630_000;   // ~2.5 Mbps video + 128k audio
    public const THUMB_EST_BYTES = 55_000;  // ~55 KB per thumbnail jpg

    public function __construct()
    {
        $this->disk = (string) config('filesystems.default', 'r2');
        $this->ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        $this->ffprobe = trim((string) @shell_exec('command -v ffprobe 2>/dev/null'));
    }

    public function hasFfmpeg(): bool
    {
        return $this->ffmpeg !== '' && @is_executable($this->ffmpeg)
            && $this->ffprobe !== '' && @is_executable($this->ffprobe);
    }

    public function isVideoUrl(?string $url): bool
    {
        return $url ? (bool) preg_match('/\.(mp4|mov|avi|mkv|webm|3gp|m4v)$/i', $url) : false;
    }

    /** URL publik -> path relatif (key) di storage. */
    public function relativePath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('#https?://[^/]+/(.*)$#', $url, $m)) {
            return $m[1];
        }
        if (str_starts_with($url, '/storage/')) {
            return substr($url, 9);
        }

        return ltrim($url, '/') ?: null;
    }

    public function publicUrl(string $key): string
    {
        return Storage::disk($this->disk)->url($key);
    }

    public function exists(string $key): bool
    {
        try {
            return Storage::disk($this->disk)->exists($key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Ukuran byte objek di storage (HEAD, tanpa download). null bila gagal. */
    public function size(string $key): ?int
    {
        try {
            return Storage::disk($this->disk)->size($key);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function variantKey(string $originalKey, string $quality): string
    {
        $name = pathinfo($originalKey, PATHINFO_FILENAME);

        return "uploads/posts/variants/{$name}_{$quality}.mp4";
    }

    public function thumbKey(string $originalKey): string
    {
        $name = pathinfo($originalKey, PATHINFO_FILENAME);

        return "uploads/posts/thumbnails/{$name}.jpg";
    }

    /**
     * Probe resolusi & durasi. $input boleh path lokal atau URL publik
     * (ffprobe hanya membaca sebagian byte untuk URL).
     *
     * @return array{w:int,h:int,duration:float}|null
     */
    public function probe(string $input): ?array
    {
        if ($this->ffprobe === '') {
            return null;
        }
        $cmd = sprintf(
            '%s -v error -select_streams v:0 -show_entries stream=width,height -show_entries format=duration -of json %s 2>/dev/null',
            escapeshellarg($this->ffprobe),
            escapeshellarg($input)
        );
        $out = @shell_exec($cmd);
        if (! $out) {
            return null;
        }
        $j = json_decode($out, true);
        $w = $j['streams'][0]['width'] ?? null;
        $h = $j['streams'][0]['height'] ?? null;
        $dur = (float) ($j['format']['duration'] ?? 0);
        if (! $w || ! $h) {
            return null;
        }

        return ['w' => (int) $w, 'h' => (int) $h, 'duration' => $dur];
    }

    /**
     * Rendition video yang perlu dibuat untuk dimensi tertentu.
     *
     * @return array<string,int>  ['low'=>480, 'medium'=>720] (subset), 'original' tersirat.
     */
    public function neededVideoRenditions(int $w, int $h): array
    {
        $short = min($w, $h);
        $out = [];
        if ($short >= self::LOW_MIN_SHORT) {
            $out['low'] = self::LOW_H;
        }
        if ($short >= self::MED_MIN_SHORT) {
            $out['medium'] = self::MED_H;
        }

        return $out;
    }

    public function estimateVideoBytes(string $quality, float $duration): int
    {
        $bps = $quality === 'low' ? self::BITRATE_LOW : ($quality === 'medium' ? self::BITRATE_MED : 0);

        return (int) round($bps * max(0.0, $duration) / 8);
    }

    // ------------------------------------------------------------------
    // EKSEKUSI (dipakai media:backfill & pemrosesan upload baru)
    // ------------------------------------------------------------------

    /**
     * Transcode file lokal ke rendition tinggi $targetH (sisi terpendek), tulis ke $dest.
     * Scale menjaga rasio & orientasi; hanya downscale.
     */
    public function transcodeTo(string $srcLocal, int $targetH, string $dest): bool
    {
        // Jika landscape (iw>ih): height=targetH, width=-2; jika portrait: width=targetH, height=-2.
        $scale = "scale='if(gt(iw,ih),-2,{$targetH})':'if(gt(iw,ih),{$targetH},-2)'";
        $cmd = sprintf(
            '%s -y -i %s -vf %s -c:v libx264 -preset veryfast -crf 24 -c:a aac -b:a 128k -movflags +faststart %s 2>/dev/null',
            escapeshellarg($this->ffmpeg),
            escapeshellarg($srcLocal),
            escapeshellarg($scale),
            escapeshellarg($dest)
        );
        @shell_exec($cmd);

        return file_exists($dest) && filesize($dest) > 1000;
    }

    /**
     * Buat thumbnail square (crop tengah) dari gambar atau video (frame detik ke-1).
     */
    public function makeThumbnail(string $srcLocal, string $dest, bool $isVideo): bool
    {
        $t = self::THUMB;
        $vf = "crop='min(iw,ih)':'min(iw,ih)',scale={$t}:{$t}";
        if ($isVideo) {
            $cmd = sprintf(
                '%s -y -ss 1 -i %s -frames:v 1 -vf %s -q:v 3 %s 2>/dev/null',
                escapeshellarg($this->ffmpeg),
                escapeshellarg($srcLocal),
                escapeshellarg($vf),
                escapeshellarg($dest)
            );
            @shell_exec($cmd);
            if (! file_exists($dest) || filesize($dest) < 100) {
                // Fallback ke frame awal bila detik-1 kosong.
                $cmd0 = sprintf(
                    '%s -y -i %s -frames:v 1 -vf %s -q:v 3 %s 2>/dev/null',
                    escapeshellarg($this->ffmpeg),
                    escapeshellarg($srcLocal),
                    escapeshellarg($vf),
                    escapeshellarg($dest)
                );
                @shell_exec($cmd0);
            }
        } else {
            $cmd = sprintf(
                '%s -y -i %s -vf %s -q:v 3 %s 2>/dev/null',
                escapeshellarg($this->ffmpeg),
                escapeshellarg($srcLocal),
                escapeshellarg($vf),
                escapeshellarg($dest)
            );
            @shell_exec($cmd);
        }

        return file_exists($dest) && filesize($dest) > 100;
    }

    /** Download objek storage ke file lokal sementara. Return path atau null. */
    public function downloadToTemp(string $key, string $suffix = ''): ?string
    {
        try {
            $stream = Storage::disk($this->disk)->readStream($key);
            if (! $stream) {
                return null;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'psi_media_').$suffix;
            $out = fopen($tmp, 'wb');
            stream_copy_to_stream($stream, $out);
            fclose($out);
            if (is_resource($stream)) {
                fclose($stream);
            }

            return $tmp;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Unggah file lokal ke storage sebagai public. */
    public function upload(string $localPath, string $key): bool
    {
        try {
            Storage::disk($this->disk)->put($key, file_get_contents($localPath), 'public');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function humanBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '?';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }

        return round($v, $v >= 100 || $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
