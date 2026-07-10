<script lang="ts">
  import { settings } from '$lib/stores.svelte';

  const defaultColumns = [
    { title: 'Belanja', links: [
      { label: 'Semua Produk', href: '/products' },
      { label: 'Semua Toko', href: '/vendors' },
      { label: 'Flash Sale', href: '/products?flash=1' },
      { label: 'Wishlist', href: '/wishlist' },
    ]},
    { title: 'Pembayaran', links: [
      { label: 'Cara Pembayaran', href: '/payment-info' },
      { label: 'Virtual Account', href: '/payment-info#va' },
      { label: 'E-Wallet', href: '/payment-info#ewallet' },
      { label: 'QRIS', href: '/payment-info#qris' },
    ]},
    { title: 'Bantuan', links: [
      { label: 'Pusat Bantuan', href: '/help' },
      { label: 'Tentang Kami', href: '/about' },
      { label: 'Buka Toko', href: '/seller/register' },
      { label: 'Seller Center', href: '/seller/dashboard' },
    ]},
  ];

  const columns = $derived((settings.footerColumns?.length ? settings.footerColumns : defaultColumns) as any[]);
  const desc = $derived(
    settings.footerDesc ||
    'Marketplace multivendor terdepan di Indonesia. Belanja produk asli dari ribuan toko terpercaya dengan pembayaran terenkripsi.'
  );
  const contact = $derived(settings.footerContact || 'support@mpsi.id · 0800-1-MPSI');
  const bottom  = $derived(settings.footerBottom || `© ${new Date().getFullYear()} ${settings.appName ?? 'MPSI'}. Semua hak dilindungi.`);
</script>

<footer class="mt-16 sm:mt-24 border-t border-ink-100 bg-ink-50/50">
  <div class="container-x py-10 sm:py-14">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-6 sm:gap-8">
      <div class="col-span-2 md:col-span-2">
        <a href="/" class="flex items-center gap-2 font-display font-extrabold text-xl tracking-tightest mb-3">
          {#if settings.logo}
            <img src={settings.logo} alt={settings.appName} class="w-8 h-8 rounded-lg object-cover" />
          {:else}
            <span class="w-8 h-8 bg-app-primary text-app-pfg rounded-lg grid place-items-center text-sm">{settings.appName?.[0] ?? 'M'}</span>
          {/if}
          {settings.appName ?? 'MPSI'}
        </a>
        <p class="text-sm text-ink-600 leading-relaxed max-w-sm whitespace-pre-line">{desc}</p>
        <p class="text-xs text-ink-400 mt-4 whitespace-pre-line">{contact}</p>
      </div>

      {#each columns.slice(0, 3) as col}
        <div>
          <h5 class="text-xs font-semibold uppercase tracking-widest text-ink-500 mb-3">{col.title}</h5>
          <ul class="space-y-2 text-sm text-ink-700">
            {#each (col.links ?? []) as link}
              <li><a href={link.href} class="hover:text-ink-950">{link.label}</a></li>
            {/each}
          </ul>
        </div>
      {/each}
    </div>

    <div class="mt-10 pt-6 border-t border-ink-200 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between text-xs text-ink-500">
      <span class="whitespace-pre-line">{bottom}</span>
      <span>Pembayaran diamankan dengan enkripsi end-to-end</span>
    </div>
  </div>
</footer>
