<script lang="ts">
  import '../app.css';
  import Header from '$lib/components/Header.svelte';
  import Footer from '$lib/components/Footer.svelte';
  import Toaster from '$lib/components/Toaster.svelte';
  import GlobalLoadingBar from '$lib/components/GlobalLoadingBar.svelte';
  import MobileBottomBar from '$lib/components/MobileBottomBar.svelte';
  import ConfirmDialog from '$lib/components/ConfirmDialog.svelte';
  import EmailVerificationGate from '$lib/components/EmailVerificationGate.svelte';
  import { onMount } from 'svelte';
  import { auth, settings, wishlist, applyPalette } from '$lib/stores.svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import { page } from '$app/stores';

  let { data, children } = $props();

  // Pages yang TIDAK perlu email verifikasi (selalu boleh diakses)
  const PUBLIC_PATHS = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-email', '/confirm-email'];
  const isExempt = $derived(PUBLIC_PATHS.some((p) => $page.url.pathname === p || $page.url.pathname.startsWith(p + '/')));
  const needsVerify = $derived(!!auth.user && !auth.user.email_verified_at && !isExempt);
  const siteName = $derived(settings.appName ?? 'MPSI Marketplace');
  const defaultTitle = $derived(settings.seoTitle || siteName);
  const defaultDescription = $derived(settings.seoDescription || 'Marketplace MPSI untuk belanja produk lokal, elektronik, kebutuhan harian, dan toko terpercaya dengan pembayaran aman.');

  // Apply server-loaded settings IMMEDIATELY (no flash)
  if (data?.settings) settings.setAll(data.settings);

  // Generate fallback favicon dari nama + palette (SVG data URI)
  function makeFallbackFavicon(name: string, bg: string, fg: string): string {
    const letter = (name?.[0] ?? 'M').toUpperCase();
    const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>
      <rect width='100' height='100' rx='22' fill='${bg}'/>
      <text x='50' y='72' text-anchor='middle' font-size='62' font-weight='800' fill='${fg}' font-family='Inter,sans-serif'>${letter}</text>
    </svg>`;
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }
  const faviconHref = $derived(
    settings.logo || makeFallbackFavicon(settings.appName, settings.primary || '#0a0a0a', settings.primaryFg || '#ffffff')
  );
  function absoluteAsset(url?: string) {
    if (!url) return '';
    if (/^data:/.test(url)) return '';
    if (/^https?:/.test(url)) return url;
    return new URL(url, $page.url.origin).href;
  }
  const defaultSeoImage = $derived(absoluteAsset(settings.seoImage || ''));
  const usesOwnSeoHead = $derived(
    $page.status >= 400 || ['/', '/products', '/product/[id]'].includes($page.route.id ?? '')
  );

  onMount(async () => {
    applyPalette({ primary: settings.primary, primaryFg: settings.primaryFg, accent: settings.accent });
    // Re-fetch settings client-side untuk sinkronisasi terbaru (background)
    try {
      const s: any = await apiEndpoints.publicSettings();
      settings.setAll(s);
    } catch {}
    if (getToken() && !auth.user) {
      try { auth.set(await apiEndpoints.me()); } catch {}
    }
  });

  let lastSyncedUserId = $state<number | null>(null);
  $effect(() => {
    const u = auth.user;
    if (u && u.id !== lastSyncedUserId) {
      lastSyncedUserId = u.id;
      apiEndpoints.getWishlist()
        .then((list: any) => wishlist.setAll((list as any[]).map(w => w.product_id)))
        .catch(() => {});
    } else if (!u && lastSyncedUserId !== null) {
      lastSyncedUserId = null;
      wishlist.clear();
    }
  });
</script>

<svelte:head>
  {#if !usesOwnSeoHead}
    <title>{defaultTitle}</title>
    <meta name="description" content={defaultDescription} />
    <meta name="robots" content="index,follow" />
    <link rel="canonical" href={$page.url.href} />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content={siteName} />
    <meta property="og:title" content={defaultTitle} />
    <meta property="og:description" content={defaultDescription} />
    <meta property="og:url" content={$page.url.href} />
    {#if defaultSeoImage}
      <meta property="og:image" content={defaultSeoImage} />
      <meta property="og:image:width" content="1200" />
      <meta property="og:image:height" content="630" />
      <meta name="twitter:image" content={defaultSeoImage} />
    {/if}
    <meta name="twitter:card" content={defaultSeoImage ? 'summary_large_image' : 'summary'} />
    <meta name="twitter:title" content={defaultTitle} />
    <meta name="twitter:description" content={defaultDescription} />
  {/if}
  <link rel="icon" type="image/svg+xml" href={faviconHref} />
  <link rel="apple-touch-icon" href={faviconHref} />
  <meta name="theme-color" content={settings.primary || '#0a0a0a'} />
  <style>
    :root {
      --app-primary: {settings.primary || '#0a0a0a'};
      --app-primary-fg: {settings.primaryFg || '#ffffff'};
      --app-accent: {settings.accent || '#6366f1'};
    }
  </style>
</svelte:head>

<div class="min-h-screen flex flex-col">
  <GlobalLoadingBar />
  <Header />
  <main class="flex-1 pb-24 md:pb-0">
    {#if needsVerify}
      <EmailVerificationGate />
    {:else}
      {@render children()}
    {/if}
  </main>
  <Footer />
  <MobileBottomBar />
  <ConfirmDialog />
  <Toaster />
</div>
