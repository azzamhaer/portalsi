<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Proses varian media untuk upload baru (pending) tiap menit.
        $schedule->command('media:process-pending --limit=5')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Hapus permanen postingan termoderasi yang melewati masa retensi 30 hari.
        $schedule->command('moderation:purge')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Bersihkan jejak tayang beranda yang lebih tua dari 14 hari (algoritma hanya
        // memakai jendela 7 hari; sisanya cukup dibuang agar tabel tetap ramping).
        $schedule->call(function () {
            DB::table('post_impressions')
                ->where('last_seen_at', '<', now()->subDays(14))
                ->delete();
        })->dailyAt('03:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
