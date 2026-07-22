<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AvatarThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Buat thumbnail untuk foto profil yang SUDAH ADA (belum punya thumbnail).
 *
 *   php artisan avatars:thumbnail --scan   → hanya melihat: berapa yang akan diproses & ukurannya
 *   php artisan avatars:thumbnail          → eksekusi, dengan progres realtime + detail ukuran
 *
 * Idempotent & bisa diulang: hanya memproses user yang punya foto tapi belum ada thumbnailnya.
 */
class AvatarsThumbnail extends Command
{
    protected $signature = 'avatars:thumbnail
        {--scan : Hanya hitung & tampilkan berapa yang akan diproses (tidak menulis apa pun)}
        {--limit=0 : Batasi jumlah (0 = semua)}
        {--force : Buat ulang walau thumbnail sudah ada}';

    protected $description = 'Generate thumbnail kecil untuk foto profil pengguna (GD, tanpa ffmpeg).';

    public function handle(AvatarThumbnailService $svc): int
    {
        if (! AvatarThumbnailService::gdAvailable()) {
            $this->error('Ekstensi GD tidak tersedia di PHP CLI ini. Pasang php-gd lalu ulangi.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $scanOnly = (bool) $this->option('scan');
        $limit = max(0, (int) $this->option('limit'));
        $disk = $svc->disk();

        $query = User::query()
            ->whereNotNull('profile_picture_url')
            ->where('profile_picture_url', '!=', '');
        if (! $force) {
            $query->whereNull('profile_picture_thumb_url');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Tidak ada foto profil yang perlu diproses. Semua sudah punya thumbnail.');

            return self::SUCCESS;
        }

        // ---------- MODE SCAN: hitung dulu, tanpa menulis ----------
        if ($scanOnly) {
            $this->line('');
            $this->info('== Pratinjau (scan) — tidak ada yang diubah ==');
            $this->line("  Foto profil yang akan diproses : <fg=yellow>{$total}</>");

            $sampleN = min(200, $total);
            $origBytes = 0;
            $measured = 0;
            (clone $query)->orderBy('user_id')->limit($sampleN)->get(['user_id', 'profile_picture_url'])
                ->each(function ($u) use ($svc, $disk, &$origBytes, &$measured) {
                    $key = $svc->keyFromUrl($u->profile_picture_url);
                    try {
                        if ($key && Storage::disk($disk)->exists($key)) {
                            $origBytes += (int) Storage::disk($disk)->size($key);
                            $measured++;
                        }
                    } catch (\Throwable $e) {
                        // abaikan file yang tak terjangkau
                    }
                });

            if ($measured > 0) {
                $avg = $origBytes / $measured;
                $estTotalOrig = $avg * $total;
                // Thumbnail 160px JPEG q82 rata-rata ~12 KB.
                $estThumbEach = 12_000;
                $this->line('  Rata-rata ukuran foto asli     : '.$this->human((int) $avg)." (sampel {$measured})");
                $this->line('  Perkiraan total foto asli      : ~'.$this->human((int) $estTotalOrig));
                $this->line('  Perkiraan total thumbnail      : ~'.$this->human((int) ($estThumbEach * $total)));
                $this->line('  Perkiraan tambahan storage     : ~'.$this->human((int) ($estThumbEach * $total)));
            }
            $this->line('');
            $this->info('Jalankan tanpa --scan untuk mulai membuat thumbnail.');

            return self::SUCCESS;
        }

        // ---------- MODE EKSEKUSI ----------
        $cap = $limit > 0 ? min($limit, $total) : $total;
        $this->info("Memproses {$cap} dari {$total} foto profil…");
        $this->line('');

        $done = 0;
        $failed = 0;
        $sumOrig = 0;
        $sumThumb = 0;

        $query->orderBy('user_id')->chunkById(100, function ($users) use ($svc, $disk, &$done, &$failed, &$sumOrig, &$sumThumb, $limit) {
            foreach ($users as $user) {
                if ($limit > 0 && $done + $failed >= $limit) {
                    return false;
                }

                $key = $svc->keyFromUrl($user->profile_picture_url);
                $origSize = 0;
                try {
                    if ($key && Storage::disk($disk)->exists($key)) {
                        $origSize = (int) Storage::disk($disk)->size($key);
                    }
                } catch (\Throwable $e) {
                    // biarkan 0
                }

                $thumbUrl = $key ? $svc->generateFromKey($key) : null;

                if ($thumbUrl) {
                    $thumbSize = 0;
                    try {
                        $thumbKey = $svc->keyFromUrl($thumbUrl);
                        if ($thumbKey) {
                            $thumbSize = (int) Storage::disk($disk)->size($thumbKey);
                        }
                    } catch (\Throwable $e) {
                        // biarkan 0
                    }
                    User::where('user_id', $user->user_id)->update(['profile_picture_thumb_url' => $thumbUrl]);
                    $done++;
                    $sumOrig += $origSize;
                    $sumThumb += $thumbSize;
                    $this->line(sprintf(
                        '  <fg=green>OK</>  @%-20s %s → %s',
                        \Illuminate\Support\Str::limit($user->username, 20, ''),
                        $this->human($origSize),
                        $this->human($thumbSize)
                    ));
                } else {
                    $failed++;
                    $this->line('  <fg=red>GAGAL</> @'.$user->username.' (foto tak terbaca / bukan gambar)');
                }
            }

            return true;
        }, 'user_id');

        $this->line('');
        $this->info('== Selesai ==');
        $this->line("  Berhasil : <fg=green>{$done}</>");
        $this->line("  Gagal    : ".($failed > 0 ? "<fg=red>{$failed}</>" : '0'));
        $this->line('  Ukuran asli (yang diproses)   : '.$this->human($sumOrig));
        $this->line('  Ukuran thumbnail (hasil)      : '.$this->human($sumThumb));
        if ($sumOrig > 0) {
            $pct = round((1 - $sumThumb / $sumOrig) * 100, 1);
            $this->line("  Penghematan saat ditampilkan  : <fg=yellow>{$pct}%</> lebih ringan");
        }

        return self::SUCCESS;
    }

    private function human(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($units) - 1));

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
