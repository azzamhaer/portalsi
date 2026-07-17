<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Throttle keamanan per-IP untuk login (dengan eskalasi blokir) dan pendaftaran.
 * Dipakai lintas app (app, admin, marketplace, meet) karena semua auth lewat API ini.
 */
class SecurityThrottle
{
    public const MAX_FAILS = 10;          // gagal login sebelum IP diblok
    public const WARN_WITHIN = 3;         // peringatan merah pada 3 percobaan terakhir
    public const REGISTER_MAX_PER_DAY = 3;

    /** Durasi blokir per level (menit): 5m → 30m → 1 jam → 1 hari, lalu reset. */
    private const DURATIONS = [1 => 5, 2 => 30, 3 => 60, 4 => 1440];

    /** Status blokir IP saat ini (null bila tidak diblok). */
    public function loginBlock(string $ip): ?array
    {
        $row = DB::table('ip_login_blocks')->where('ip', $ip)->first();
        if (! $row || ! $row->blocked_until) {
            return null;
        }
        $until = Carbon::parse($row->blocked_until);
        if ($until->isFuture()) {
            return ['until' => $until, 'seconds' => now()->diffInSeconds($until), 'level' => $row->block_level];
        }

        return null;
    }

    /** Catat kegagalan login. Mengembalikan info peringatan / blokir. */
    public function loginFailure(string $ip, ?string $username, ?int $userId, string $app): array
    {
        $row = DB::table('ip_login_blocks')->where('ip', $ip)->first();
        $now = now();

        $failCount = ($row->fail_count ?? 0) + 1;
        $level = $row->block_level ?? 0;
        $total = ($row->total_failures ?? 0) + 1;
        $blockedUntil = null;
        $blocked = false;
        $seconds = 0;

        if ($failCount >= self::MAX_FAILS) {
            // Naik level (setelah level 4 / 1 hari, siklus reset ke level 1).
            $level = $level >= 4 ? 1 : $level + 1;
            $minutes = self::DURATIONS[$level] ?? 5;
            $blockedUntil = $now->copy()->addMinutes($minutes);
            $seconds = $minutes * 60;
            $blocked = true;
            $failCount = 0; // reset streak untuk ronde berikutnya
        }

        $data = [
            'ip' => $ip,
            'fail_count' => $failCount,
            'block_level' => $level,
            'blocked_until' => $blockedUntil,
            'last_username' => $username ? mb_substr($username, 0, 190) : ($row->last_username ?? null),
            'last_user_id' => $userId ?? ($row->last_user_id ?? null),
            'last_app' => $app,
            'total_failures' => $total,
            'updated_at' => $now,
        ];
        if ($row) {
            DB::table('ip_login_blocks')->where('ip', $ip)->update($data);
        } else {
            $data['created_at'] = $now;
            DB::table('ip_login_blocks')->insert($data);
        }

        $remaining = max(0, self::MAX_FAILS - $failCount);

        return [
            'blocked' => $blocked,
            'seconds' => $seconds,
            'level' => $level,
            'remaining' => $remaining,
            'warn' => ! $blocked && $remaining <= self::WARN_WITHIN,
        ];
    }

    /** Login berhasil → reset streak (level tetap sebagai memori eskalasi). */
    public function loginSuccess(string $ip): void
    {
        DB::table('ip_login_blocks')->where('ip', $ip)->update([
            'fail_count' => 0,
            'blocked_until' => null,
            'updated_at' => now(),
        ]);
    }

    /** Sisa kuota pendaftaran hari ini untuk IP. */
    public function registrationsLeft(string $ip): int
    {
        $used = DB::table('ip_registrations')
            ->where('ip', $ip)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return max(0, self::REGISTER_MAX_PER_DAY - $used);
    }

    public function canRegister(string $ip): bool
    {
        return $this->registrationsLeft($ip) > 0;
    }

    public function logRegistration(string $ip, string $app, ?string $username): void
    {
        DB::table('ip_registrations')->insert([
            'ip' => $ip,
            'app' => $app,
            'username' => $username ? mb_substr($username, 0, 190) : null,
            'created_at' => now(),
        ]);
    }

    /** IP asli klien: header dari SSR proxy > IP request. */
    public function clientIp($request): string
    {
        return $request->header('X-Real-Client-Ip')
            ?: ($request->header('CF-Connecting-IP')
            ?: ($request->ip() ?: 'unknown'));
    }
}
