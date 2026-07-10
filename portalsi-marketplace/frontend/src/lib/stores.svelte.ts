import { browser } from '$app/environment';

/* ===== Auth store ===== */
function createAuthStore() {
  let user = $state<any>(null);
  let loading = $state(true);

  if (browser) {
    const cached = localStorage.getItem('pm_user');
    if (cached) try { user = JSON.parse(cached); } catch {}
    loading = false;
    // Cross-tab sync: kalau tab lain update auth.user, tab ini juga ikut
    window.addEventListener('storage', (e) => {
      if (e.key === 'pm_user') {
        try { user = e.newValue ? JSON.parse(e.newValue) : null; } catch {}
      }
    });
  }

  return {
    get user() { return user; },
    get loading() { return loading; },
    set(u: any) {
      user = u;
      if (browser) {
        if (u) localStorage.setItem('pm_user', JSON.stringify(u));
        else   localStorage.removeItem('pm_user');
      }
    },
    clear() {
      user = null;
      if (browser) {
        localStorage.removeItem('pm_user');
        localStorage.removeItem('pm_token');
      }
    }
  };
}
export const auth = createAuthStore();

/* ===== Cart store ===== */
export interface CartItem {
  cart_key?: string;
  product_id: number;
  product_slug?: string | null;
  name: string;
  image: string;
  price: number;
  vendor_id: number;
  vendor_name: string;
  vendor_username?: string | null;
  variant_selection?: string | null;
  variant_details?: Record<string, { label: string; image?: string | null }> | null;
  qty: number;
  stock: number;
  checked: boolean;
}

function createCartStore() {
  let items = $state<CartItem[]>([]);
  if (browser) {
    const cached = localStorage.getItem('pm_cart');
    if (cached) try { items = JSON.parse(cached); } catch {}
  }
  function save() { if (browser) localStorage.setItem('pm_cart', JSON.stringify(items)); }

  return {
    get items() { return items; },
    get count() { return items.reduce((s, i) => s + i.qty, 0); },
    get checkedItems() { return items.filter(i => i.checked); },
    get subtotal() { return items.filter(i => i.checked).reduce((s,i) => s + i.price * i.qty, 0); },
    add(it: Omit<CartItem, 'qty'|'checked'> & { qty?: number }) {
      if (it.stock <= 0) return false;
      const cartKey = it.cart_key || `${it.product_id}:${it.variant_selection || ''}`;
      const existing = items.find(i => (i.cart_key || `${i.product_id}:${i.variant_selection || ''}`) === cartKey);
      if (existing) existing.qty = Math.min(it.stock, existing.qty + (it.qty ?? 1));
      else items = [...items, { ...it, cart_key: cartKey, qty: it.qty ?? 1, checked: true }];
      save();
      return true;
    },
    update(key: string | number, qty: number) { items = items.map(i => (i.cart_key || i.product_id) === key ? { ...i, qty: Math.max(1, Math.min(i.stock, qty)) } : i); save(); },
    remove(key: string | number) { items = items.filter(i => (i.cart_key || i.product_id) !== key); save(); },
    toggleCheck(key: string | number) { items = items.map(i => (i.cart_key || i.product_id) === key ? { ...i, checked: !i.checked } : i); save(); },
    checkAll(state: boolean) { items = items.map(i => ({ ...i, checked: state })); save(); },
    clear() { items = []; save(); },
    clearChecked() { items = items.filter(i => !i.checked); save(); }
  };
}
export const cart = createCartStore();

/* ===== Wishlist store (local cache + server sync) ===== */
function createWishlistStore() {
  let ids = $state<number[]>([]);
  if (browser) {
    const cached = localStorage.getItem('pm_wishlist');
    if (cached) try { ids = JSON.parse(cached); } catch {}
  }
  function save() { if (browser) localStorage.setItem('pm_wishlist', JSON.stringify(ids)); }
  return {
    get ids() { return ids; },
    has(id: number) { return ids.includes(id); },
    toggle(id: number) {
      if (ids.includes(id)) ids = ids.filter(x => x !== id);
      else ids = [...ids, id];
      save();
    },
    setAll(newIds: number[]) { ids = newIds; save(); },
    clear() { ids = []; save(); }
  };
}
export const wishlist = createWishlistStore();

/* ===== Settings store (loaded from backend) ===== */
function createSettingsStore() {
  const defaults = {
    appName: 'MPSI',
    logo: '' as string,
    palette: 'mono' as string,
    primary: '#0a0a0a',
    primaryFg: '#ffffff',
    accent: '#6366f1',
    tagline: 'Marketplace untuk semua',
    heroTitle: 'Belanja yang membuat hidup lebih mudah.',
    heroSubtitle: 'Ribuan produk pilihan dari toko terverifikasi. Pembayaran aman, pengiriman cepat.',
    heroCtaLabel: 'Mulai belanja',
    heroCtaHref: '/products',
    heroImage: '',
    heroEnabled: true,
    paymentIntro: '',
    helpIntro: '',
    footerColumns: [] as any[],
    footerBottom: '',
    footerContact: '',
    footerDesc: '',
    hiddenPages: [] as string[],
    seoTitle: 'MPSI',
    seoDescription: 'Marketplace MPSI untuk belanja produk lokal, elektronik, kebutuhan harian, dan toko terpercaya dengan pembayaran aman.',
    seoImage: '',
    seoHomeTitle: '',
    seoHomeDescription: '',
    seoHomeImage: '',
    seoProductsTitle: '',
    seoProductsDescription: '',
    seoProductsImage: '',
  };
  let s = $state<any>({ ...defaults });
  if (browser) {
    const cached = localStorage.getItem('pm_settings');
    if (cached) try { s = { ...defaults, ...JSON.parse(cached) }; } catch {}
  }
  return {
    get appName()      { return s.appName; },
    get logo()         { return s.logo; },
    get palette()      { return s.palette; },
    get primary()      { return s.primary; },
    get primaryFg()    { return s.primaryFg; },
    get accent()       { return s.accent; },
    get tagline()      { return s.tagline; },
    get heroTitle()    { return s.heroTitle; },
    get heroSubtitle() { return s.heroSubtitle; },
    get heroCtaLabel() { return s.heroCtaLabel; },
    get heroCtaHref()  { return s.heroCtaHref; },
    get heroImage()    { return s.heroImage; },
    get heroEnabled()  { return s.heroEnabled !== false; },
    get paymentIntro() { return s.paymentIntro; },
    get helpIntro()    { return s.helpIntro; },
    get footerColumns(){ return s.footerColumns ?? []; },
    get footerBottom() { return s.footerBottom; },
    get footerContact(){ return s.footerContact; },
    get footerDesc()   { return s.footerDesc; },
    get hiddenPages()  { return s.hiddenPages ?? []; },
    get seoTitle()     { return s.seoTitle; },
    get seoDescription(){ return s.seoDescription; },
    get seoImage()     { return s.seoImage; },
    get seoHomeTitle() { return s.seoHomeTitle; },
    get seoHomeDescription(){ return s.seoHomeDescription; },
    get seoHomeImage() { return s.seoHomeImage; },
    get seoProductsTitle(){ return s.seoProductsTitle; },
    get seoProductsDescription(){ return s.seoProductsDescription; },
    get seoProductsImage(){ return s.seoProductsImage; },
    setAll(data: any) {
      s = { ...defaults, ...data };
      if (browser) {
        localStorage.setItem('pm_settings', JSON.stringify(s));
        applyPalette(s);
      }
    }
  };
}
export const settings = createSettingsStore();

export function applyPalette(s: any) {
  if (!browser) return;
  const root = document.documentElement;
  root.style.setProperty('--app-primary',    s.primary    || '#0a0a0a');
  root.style.setProperty('--app-primary-fg', s.primaryFg  || '#ffffff');
  root.style.setProperty('--app-accent',     s.accent     || '#6366f1');
  if (document.title === 'undefined' || !document.title) document.title = s.appName || 'MPSI';
}

/* ===== Toast store ===== */
export interface Toast { id: number; msg: string; type: 'info'|'success'|'error'|'warn' }
function createToastStore() {
  let items = $state<Toast[]>([]);
  return {
    get items() { return items; },
    push(t: Omit<Toast, 'id'>) {
      const id = Date.now() + Math.random();
      items = [...items, { ...t, id }];
      setTimeout(() => { items = items.filter(x => x.id !== id); }, 3000);
    }
  };
}
export const toasts = createToastStore();
export const toast = {
  success: (msg: string) => toasts.push({ msg, type:'success' }),
  error:   (msg: string) => toasts.push({ msg, type:'error' }),
  warn:    (msg: string) => toasts.push({ msg, type:'warn' }),
  info:    (msg: string) => toasts.push({ msg, type:'info' })
};

/* ===== Confirm dialog store ===== */
function createConfirmStore() {
  let open = $state(false);
  let title = $state('Apakah Anda yakin?');
  let message = $state('');
  let confirmText = $state('Ya, lanjutkan');
  let cancelText = $state('Batal');
  let tone = $state<'default' | 'danger'>('default');
  let resolver: ((ok: boolean) => void) | null = null;

  function close(ok: boolean) {
    open = false;
    resolver?.(ok);
    resolver = null;
  }

  return {
    get open() { return open; },
    get title() { return title; },
    get message() { return message; },
    get confirmText() { return confirmText; },
    get cancelText() { return cancelText; },
    get tone() { return tone; },
    ask(opts: { title?: string; message?: string; confirmText?: string; cancelText?: string; tone?: 'default' | 'danger' } = {}) {
      title = opts.title ?? 'Apakah Anda yakin?';
      message = opts.message ?? '';
      confirmText = opts.confirmText ?? 'Ya, lanjutkan';
      cancelText = opts.cancelText ?? 'Batal';
      tone = opts.tone ?? 'default';
      open = true;
      return new Promise<boolean>((resolve) => { resolver = resolve; });
    },
    confirm() { close(true); },
    cancel() { close(false); }
  };
}

export const confirmDialog = createConfirmStore();

export function loginHref(next?: string, action?: string) {
  if (!browser) return '/login';
  const url = new URL('/login', window.location.origin);
  url.searchParams.set('next', next || window.location.pathname + window.location.search);
  if (action) url.searchParams.set('action', action);
  return url.pathname + url.search;
}
