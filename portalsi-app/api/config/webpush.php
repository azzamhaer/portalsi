<?php

return [
    /*
    | Kunci VAPID untuk Web Push. Generate sekali (mis. `php artisan webpush:vapid` bila
    | pakai package, atau via node web-push) lalu tempel ke .env. JANGAN commit nilainya.
    |
    | VAPID_PUBLIC_KEY  -> juga diexpose ke frontend sebagai PUBLIC_VAPID_PUBLIC_KEY
    | VAPID_PRIVATE_KEY -> RAHASIA, hanya di server
    | VAPID_SUBJECT     -> mailto: atau URL kontak
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@portalsi.com'),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

    // Waktu hidup default pesan di push service (detik).
    'ttl' => (int) env('WEBPUSH_TTL', 43200),

    // URL app web (untuk membentuk deep link & aset ikon notifikasi).
    'app_url' => rtrim(env('FRONTEND_URL', 'https://app.portalsi.com'), '/'),
    'icon' => env('WEBPUSH_ICON', 'https://app.portalsi.com/assets/logo-mark.png'),
    'badge' => env('WEBPUSH_BADGE', 'https://app.portalsi.com/assets/logo-mark.png'),
];
