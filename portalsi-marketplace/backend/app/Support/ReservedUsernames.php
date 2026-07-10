<?php

namespace App\Support;

class ReservedUsernames
{
    /**
     * Daftar lengkap reserved path / username yang tidak boleh dipakai vendor.
     * Pastikan dalam huruf kecil.
     */
    public const LIST = [
        // existing routes
        '', 'admin', 'admins', 'administrator',
        'profile', 'account', 'accounts', 'me',
        'cart', 'checkout', 'orders', 'order',
        'products', 'product', 'vendors', 'vendor',
        'search', 'browse',
        'chats', 'chat', 'messages', 'inbox',
        'wishlist', 'favorites', 'favorite',
        'login', 'register', 'signup', 'signin', 'logout', 'auth',
        'seller', 'sellers', 'dashboard',
        'help', 'support', 'about', 'contact', 'terms', 'privacy',
        'payment-info', 'payments', 'pay', 'invoice', 'invoices',
        'api', 'www', 'mail', 'ftp', 'cdn', 'static', 'assets',
        'categories', 'category', 'tags', 'tag',
        'system', 'root', 'staff', 'owner', 'team', 'config', 'settings',
        // badge / status keywords (avoid impersonation)
        'mall', 'official', 'verified', 'star', 'platinum', 'gold', 'silver',
        // others
        'home', 'beranda', 'index', 'site', 'app', 'blog', 'news',
        'notification', 'notifications', 'notif',
        'returns', 'return', 'withdraw', 'withdrawal', 'withdrawals',
        'shipping', 'tracking',
        'favicon.ico', 'robots.txt', 'sitemap.xml',
        'null', 'undefined', 'true', 'false',
        // Auth flows
        'forgot-password', 'reset-password', 'verify-email', 'confirm-email',
        // Admin extra
        'appearance',
    ];

    public static function isReserved(string $username): bool
    {
        $u = strtolower(trim($username));
        return in_array($u, self::LIST, true);
    }
}
