import { browser, dev } from '$app/environment';
import { PUBLIC_API_URL } from '$env/static/public';

const DEFAULT_API_URL = 'https://api-marketplace.portalsi.com/api';
const LOCAL_API_PORT = '8000';

function cleanBaseUrl(url: string): string {
  return url.trim().replace(/\/+$/, '');
}

function isLoopbackHost(hostname: string): boolean {
  return hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1' || hostname === '[::1]';
}

function isPrivateNetworkHost(hostname: string): boolean {
  return (
    /^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(hostname) ||
    /^192\.168\.\d{1,3}\.\d{1,3}$/.test(hostname) ||
    /^172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}$/.test(hostname)
  );
}

function resolveBaseUrl(): string {
  const configured = cleanBaseUrl(PUBLIC_API_URL || DEFAULT_API_URL);

  try {
    const apiUrl = new URL(configured);

    if (!browser) {
      return !dev && isLoopbackHost(apiUrl.hostname) ? DEFAULT_API_URL : configured;
    }

    const pageUrl = new URL(window.location.href);
    const pageIsLocal = isLoopbackHost(pageUrl.hostname) || isPrivateNetworkHost(pageUrl.hostname);

    if (isLoopbackHost(apiUrl.hostname)) {
      if (pageIsLocal && !isLoopbackHost(pageUrl.hostname)) {
        apiUrl.hostname = pageUrl.hostname;
        apiUrl.port = apiUrl.port || LOCAL_API_PORT;
        return cleanBaseUrl(apiUrl.toString());
      }

      if (!pageIsLocal) return DEFAULT_API_URL;
    }
  } catch {
    return configured;
  }

  return configured;
}

const BASE = resolveBaseUrl();
export const apiBaseUrl = BASE;

export class ApiError extends Error {
  constructor(public status: number, public data: any, message: string) { super(message); }
}

const tokenKey = 'pm_token';
export function getToken(): string | null {
  if (!browser) return null;
  return localStorage.getItem(tokenKey);
}
export function setToken(t: string | null) {
  if (!browser) return;
  if (t) localStorage.setItem(tokenKey, t);
  else   localStorage.removeItem(tokenKey);
}

export async function api<T = any>(
  path: string,
  init: RequestInit & { fetcher?: typeof fetch; auth?: boolean } = {}
): Promise<T> {
  const f = init.fetcher || fetch;
  const headers: Record<string, string> = {
    'Accept': 'application/json',
    ...((init.headers as Record<string, string>) || {})
  };
  if (init.body && !(init.body instanceof FormData) && typeof init.body !== 'string') {
    headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(init.body);
  }
  const tok = getToken();
  if (tok) headers['Authorization'] = `Bearer ${tok}`;

  const res = await f(`${BASE}${path}`, { ...init, headers });
  const text = await res.text();
  let data: any;
  try { data = text ? JSON.parse(text) : null; } catch { data = text; }

  if (!res.ok) {
    const validation = data?.errors && typeof data.errors === 'object'
      ? Object.values(data.errors).flat().filter(Boolean).join(' ')
      : '';
    const msg = validation || (data && (data.message || data.error)) || `HTTP ${res.status}`;
    throw new ApiError(res.status, data, msg);
  }
  return data as T;
}

export const apiEndpoints = {
  /* settings */
  publicSettings: (f?: typeof fetch) => api('/settings/public', { fetcher: f }),

  /* home & catalog */
  home:        (f?: typeof fetch) => api('/home',           { fetcher: f }),
  products:    (q: string,  f?: typeof fetch) => api('/products' + (q ? '?' + q : ''), { fetcher: f }),
  product:     (id: string|number, f?: typeof fetch) => api(`/products/${id}`, { fetcher: f }),
  categories:  (f?: typeof fetch) => api('/categories', { fetcher: f }),
  tags:        (f?: typeof fetch) => api('/tags', { fetcher: f }),
  searchSuggest: (q: string, f?: typeof fetch) => api('/search/suggest?q=' + encodeURIComponent(q), { fetcher: f }),
  vendors:     (q: string = '', f?: typeof fetch) => api('/vendors' + (q ? '?' + q : ''), { fetcher: f }),
  vendor:      (idOrUsername: string|number, f?: typeof fetch) => api(`/vendors/${idOrUsername}`, { fetcher: f }),
  paymentMethods: (f?: typeof fetch) => api('/payment-methods', { fetcher: f }),
  shippingOptions:(f?: typeof fetch) => api('/shipping-options', { fetcher: f }),
  faqs:           (f?: typeof fetch) => api('/faqs', { fetcher: f }),

  /* auth */
  login:    (email: string, password: string) => api('/auth/login',    { method: 'POST', body: { email, password } as any }),
  register: (b: any) => api('/auth/register', { method: 'POST', body: b }),
  logout:   ()       => api('/auth/logout',  { method: 'POST' }),
  me:       ()       => api('/auth/me'),
  updateProfile: (b: any) => api('/auth/profile', { method: 'PUT', body: b }),
  changePassword: (current_password: string, new_password: string) =>
    api('/auth/change-password', { method: 'POST', body: { current_password, new_password } as any }),
  requestChangeEmail: (new_email: string) =>
    api('/auth/request-change-email', { method: 'POST', body: { new_email } as any }),
  forgotPassword: (email: string) => api('/auth/forgot-password', { method: 'POST', body: { email } as any }),
  resetPassword: (token: string, new_password: string) =>
    api('/auth/reset-password', { method: 'POST', body: { token, new_password } as any }),
  verifyEmail: (token: string) => api('/auth/verify-email', { method: 'POST', body: { token } as any }),
  resendVerification: () => api('/auth/resend-verification', { method: 'POST' }),
  confirmEmail: (token: string) => api('/auth/confirm-email', { method: 'POST', body: { token } as any }),

  /* address */
  addresses:       () => api('/addresses'),
  saveAddress:     (b: any) => api('/addresses', { method: 'POST', body: b }),
  updateAddress:   (id: number, b: any) => api(`/addresses/${id}`, { method: 'PUT', body: b }),
  deleteAddress:   (id: number) => api(`/addresses/${id}`, { method: 'DELETE' }),

  /* wishlist */
  getWishlist:    () => api('/wishlist'),
  toggleWishlist: (id: number) => api('/wishlist/toggle', { method: 'POST', body: { product_id: id } as any }),

  /* orders */
  orders:         (q='') => api('/orders' + (q ? '?' + q : '')),
  ordersActiveCount: () => api('/orders/active-count'),
  order:          (id: string|number) => api(`/orders/${id}`),
  checkout:       (b: any)     => api('/checkout', { method: 'POST', body: b }),
  shippingRates:  (b: any)     => api('/checkout/shipping-rates', { method: 'POST', body: b }),
  applyVoucher:   (b: any)     => api('/checkout/apply-voucher', { method: 'POST', body: b }),
  refreshOrder:   (id: string|number) => api(`/orders/${id}/refresh`, { method: 'POST' }),
  simulateOrder:  (id: string|number) => api(`/orders/${id}/simulate`,{ method: 'POST' }),
  markOrderDone:  (id: string|number) => api(`/orders/${id}/done`,    { method: 'POST' }),
  requestReturn:  (id: string|number, reason: string) => api(`/orders/${id}/return`, { method: 'POST', body: { reason } as any }),
  refunds:        () => api('/refunds'),

  /* seller */
  sellerRegister:   (b: any | FormData) => api('/seller/register', { method: 'POST', body: b }),
  sellerDashboard:  ()         => api('/seller/dashboard'),
  sellerProducts:   ()         => api('/seller/products'),
  sellerCreateProduct: (b: any)=> api('/seller/products', { method: 'POST', body: b }),
  sellerUpdateProduct: (id: number, b: any) => api(`/seller/products/${id}`, { method: 'PUT', body: b }),
  sellerDeleteProduct: (id: number) => api(`/seller/products/${id}`, { method: 'DELETE' }),
  sellerOrders:     (q='')     => api('/seller/orders' + (q ? '?' + q : '')),
  sellerProcessOrder:(id: number) => api(`/seller/orders/${id}/process`, { method: 'POST' }),
  sellerShipOrder:  (id: number) => api(`/seller/orders/${id}/ship`, { method: 'POST' }),
  sellerArriveOrder:(id: number) => api(`/seller/orders/${id}/arrive`, { method: 'POST' }),
  sellerVouchers:   ()         => api('/seller/vouchers'),
  sellerCreateVoucher: (b: any)=> api('/seller/vouchers', { method: 'POST', body: b }),
  sellerUpdateVoucher: (id: number, b: any) => api(`/seller/vouchers/${id}`, { method: 'PUT', body: b }),
  sellerDeleteVoucher: (id: number) => api(`/seller/vouchers/${id}`, { method: 'DELETE' }),
  sellerUpdateProfile:(b: any) => api('/seller/profile', { method: 'PUT', body: b }),
  sellerUpdateUsername:(username: string) => api('/seller/username', { method: 'POST', body: { username } as any }),
  sellerDismissWarning:() => api('/seller/dismiss-warning', { method: 'POST' }),
  sellerFinishTour:    () => api('/seller/finish-tour', { method: 'POST' }),

  /* reports */
  reportCategories: () => api('/reports/categories'),
  submitReport: (b: { target_type: 'PRODUCT'|'VENDOR'; target_id: number; category: string; description: string; attachments?: string[] }) =>
    api('/reports', { method: 'POST', body: b as any }),
  adminReports: (q='') => api('/admin/reports' + (q ? '?'+q : '')),
  adminResolveReport: (id: number, b: any) => api(`/admin/reports/${id}`, { method: 'POST', body: b }),

  /* notifications */
  notifications: (q='') => api('/notifications' + (q ? '?'+q : '')),
  notification: (id: string|number) => api(`/notifications/${id}`),
  notificationsUnreadCount: () => api('/notifications/unread-count'),
  notificationMarkRead: (id: number) => api(`/notifications/${id}/read`, { method: 'POST' }),
  notificationsReadAll: () => api('/notifications/read-all', { method: 'POST' }),
  notificationDelete: (id: number) => api(`/notifications/${id}`, { method: 'DELETE' }),

  /* reviews */
  canReviewProduct: (productId: number) => api(`/products/${productId}/can-review`),
  submitReview:     (productId: number, rating: number, comment: string) =>
    api(`/products/${productId}/reviews`, { method: 'POST', body: { rating, comment } as any }),
  deleteReview:     (reviewId: number) => api(`/reviews/${reviewId}`, { method: 'DELETE' }),

  /* chat */
  chats:       () => api('/chats'),
  chatsUnreadCount: () => api('/chats/unread-count'),
  chatThread:  (id: number) => api(`/chats/${id}`),
  startChat:   (vendor_id: number, product_id?: number, message?: string) => api('/chats', { method: 'POST', body: { vendor_id, product_id, message } as any }),
  sendMessage: (thread_id: number, message: string, image_url?: string | null) => api(`/chats/${thread_id}/messages`, { method: 'POST', body: { message, image_url } as any }),

  /* withdraw seller */
  sellerWithdraw:        () => api('/seller/withdraw'),
  sellerRequestWithdraw: (amount: number) => api('/seller/withdraw', { method: 'POST', body: { amount } as any }),
  sellerCancelWithdraw:  (id: number) => api(`/seller/withdraw/${id}`, { method: 'DELETE' }),
  userWallet:            () => api('/wallet'),
  userRequestWithdraw:   (b: any) => api('/wallet/withdraw', { method: 'POST', body: b }),

  /* follow */
  toggleFollow: (vendorId: number) => api(`/vendors/${vendorId}/follow`, { method: 'POST' }),
  followStatus: (vendorId: number) => api(`/vendors/${vendorId}/follow`),

  /* admin */
  adminStats:        () => api('/admin/stats'),
  adminFreshStartSummary: () => api('/admin/fresh-start/summary'),
  adminFreshStart:   (password: string) => api('/admin/fresh-start', { method: 'POST', body: { confirm: 'FRESH_START', password } as any }),
  adminUsers:        (q='') => api('/admin/users' + (q ? '?'+q : '')),
  adminUser:         (id: number) => api(`/admin/users/${id}`),
  adminDeleteUser:   (id: number) => api(`/admin/users/${id}`, { method: 'DELETE' }),
  adminUpdateUser:   (id: number, b: any) => api(`/admin/users/${id}`, { method: 'PUT', body: b }),
  adminVendors:      (q='') => api('/admin/vendors' + (q ? '?'+q : '')),
  adminVerifyVendor: (id: number, status: 'APPROVED'|'REJECTED', note?: string) => api(`/admin/vendors/${id}/verify`, { method: 'POST', body: { status, note } as any }),
  adminSetVendorBadge: (id: number, badge: string | null) => api(`/admin/vendors/${id}/badge`, { method: 'POST', body: { badge } as any }),
  adminSetVendorModeration: (id: number, mode: string, admin_warning?: string) =>
    api(`/admin/vendors/${id}/moderation`, { method: 'POST', body: { moderation_mode: mode, admin_warning } as any }),
  adminDeleteVendor: (id: number) => api(`/admin/vendors/${id}`, { method: 'DELETE' }),
  adminWithdrawals:  (q='') => api('/admin/withdrawals' + (q ? '?'+q : '')),
  adminProcessWithdraw: (id: number|string, status: string, admin_note?: string) => api(`/admin/withdrawals/${id}`, { method: 'POST', body: { status, admin_note } as any }),
  adminFaqs:           () => api('/admin/faqs'),
  adminSaveFaqs:       (items: any[]) => api('/admin/faqs', { method: 'PUT', body: { items } as any }),
  adminPaymentMethods: () => api('/admin/payment-methods'),
  adminSavePaymentMethods: (items: any[]) => api('/admin/payment-methods', { method: 'PUT', body: { items } as any }),
  adminOrders:       (q='') => api('/admin/orders' + (q ? '?'+q : '')),
  adminOrder:        (id: number) => api(`/admin/orders/${id}`),
  adminUpdateOrder:  (id: number, b: any) => api(`/admin/orders/${id}`, { method: 'PUT', body: b }),
  adminReturns:      () => api('/admin/returns'),
  adminApproveReturn:(id: number, status: string) => api(`/admin/returns/${id}`, { method: 'POST', body: { status } as any }),
  adminSettings:     () => api('/admin/settings'),
  adminSaveSettings: (b: any) => api('/admin/settings', { method: 'PUT', body: b }),
  adminUploadLogo:   (fd: FormData) => api('/admin/settings/logo', { method: 'POST', body: fd as any }),
  adminUploadHero:   (fd: FormData) => api('/admin/settings/hero', { method: 'POST', body: fd as any }),
  adminUploadSeoImage: (fd: FormData) => api('/admin/settings/seo-image', { method: 'POST', body: fd as any }),
  adminShipping:     () => api('/admin/shipping-options'),
  adminSaveShipping: (list: any[]) => api('/admin/shipping-options', { method: 'PUT', body: { items: list } as any }),
  adminTags:         (q='') => api('/admin/tags' + (q ? '?'+q : '')),
  adminSaveTag:      (id: number | null, b: any) => api(id ? `/admin/tags/${id}` : '/admin/tags', { method: id ? 'PUT' : 'POST', body: b }),
  adminDeleteTag:    (id: number) => api(`/admin/tags/${id}`, { method: 'DELETE' }),
  adminCategories:   () => api('/admin/categories'),
  adminSaveCategory: (id: string | null, b: any) => api(id ? `/admin/categories/${id}` : '/admin/categories', { method: id ? 'PUT' : 'POST', body: b }),
  adminDeleteCategory: (id: string) => api(`/admin/categories/${id}`, { method: 'DELETE' }),
};
