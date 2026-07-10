import { apiEndpoints } from '$lib/api';
import { error } from '@sveltejs/kit';
import type { PageLoad } from './$types';

const RESERVED = new Set<string>([
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
  'mall', 'official', 'verified', 'star', 'platinum', 'gold', 'silver',
  'home', 'beranda', 'index', 'site', 'app', 'blog', 'news',
  'notification', 'notifications', 'notif',
  'returns', 'return', 'withdraw', 'withdrawal', 'withdrawals',
  'shipping', 'tracking',
  'favicon.ico', 'robots.txt', 'sitemap.xml',
  'null', 'undefined', 'true', 'false',
  'forgot-password', 'reset-password', 'verify-email', 'confirm-email',
  'appearance', 'notifications',
]);

export const load: PageLoad = async ({ params, fetch }) => {
  const u = (params.username ?? '').toLowerCase();
  if (RESERVED.has(u) || !/^[a-z0-9][a-z0-9_-]*$/.test(u)) {
    throw error(404, 'Halaman tidak ditemukan');
  }
  try {
    return await apiEndpoints.vendor(u, fetch) as any;
  } catch {
    throw error(404, 'Toko tidak ditemukan');
  }
};
