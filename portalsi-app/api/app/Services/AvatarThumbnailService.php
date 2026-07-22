<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Membuat thumbnail foto profil (kecil, square, di-crop tengah) memakai GD murni.
 *
 * Sengaja TIDAK memakai ffmpeg/shell_exec — ekstensi GD tersedia langsung di PHP-FPM,
 * jadi aman dijalankan baik dari web maupun CLI. Foto asli tidak diubah; hasilnya objek
 * baru berukuran kecil untuk ditampilkan di seluruh aplikasi.
 */
class AvatarThumbnailService
{
    /** Sisi thumbnail (px). Cukup tajam untuk avatar besar, tetap ringan. */
    public const SIZE = 160;

    public function disk(): string
    {
        return (string) config('filesystems.default', 'public');
    }

    public static function gdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
    }

    /** URL publik → path relatif (key) di storage. */
    public function keyFromUrl(?string $url): ?string
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

    /**
     * Buat thumbnail dari key foto asli. Kembalikan URL thumbnail, atau null bila gagal.
     */
    public function generateFromKey(string $originalKey): ?string
    {
        if (! self::gdAvailable()) {
            return null;
        }

        $disk = $this->disk();
        try {
            if (! Storage::disk($disk)->exists($originalKey)) {
                return null;
            }
            $bytes = Storage::disk($disk)->get($originalKey);
        } catch (\Throwable $e) {
            return null;
        }

        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        // Crop kotak dari tengah, lalu perkecil ke SIZE.
        $sx = (int) (($w - $side) / 2);
        $sy = (int) (($h - $side) / 2);

        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        // Latar putih supaya PNG transparan tidak jadi hitam saat disimpan sebagai JPG.
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, self::SIZE, self::SIZE, $white);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($dst, null, 82);
        $thumbBytes = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        if (! $thumbBytes) {
            return null;
        }

        $name = pathinfo($originalKey, PATHINFO_FILENAME);
        $thumbKey = "profile_pictures/thumbs/{$name}_".Str::random(6).'.jpg';
        try {
            Storage::disk($disk)->put($thumbKey, $thumbBytes, 'public');

            return Storage::disk($disk)->url($thumbKey);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Versi praktis: terima URL asli, kembalikan URL thumbnail. */
    public function generateFromUrl(?string $url): ?string
    {
        $key = $this->keyFromUrl($url);

        return $key ? $this->generateFromKey($key) : null;
    }
}
