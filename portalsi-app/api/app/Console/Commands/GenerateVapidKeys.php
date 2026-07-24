<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Menghasilkan sepasang kunci VAPID untuk Web Push. Jalankan sekali, lalu tempel hasilnya
 * ke .env API (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY) dan ke .env web (PUBLIC_VAPID_PUBLIC_KEY).
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate sepasang kunci VAPID untuk Web Push';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('Kunci VAPID berhasil dibuat. Tempel ke .env:');
        $this->newLine();
        $this->line('# API (.env portalsi-app/api)');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:admin@portalsi.com');
        $this->newLine();
        $this->line('# WEB (.env portalsi-app/web) — sama dengan public key di atas');
        $this->line('PUBLIC_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->newLine();
        $this->warn('Simpan private key baik-baik. Jangan commit ke git.');

        return self::SUCCESS;
    }
}
