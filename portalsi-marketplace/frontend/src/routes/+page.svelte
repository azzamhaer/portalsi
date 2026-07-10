<script lang="ts">
  import Hero from '$lib/components/Hero.svelte';
  import HomeCategoryNav from '$lib/components/HomeCategoryNav.svelte';
  import SmartSearch from '$lib/components/SmartSearch.svelte';
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import ProductGridSkeleton from '$lib/components/ProductGridSkeleton.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { settings } from '$lib/stores.svelte';
  import { page } from '$app/stores';
  let { data } = $props();

  function absoluteAsset(url?: string) {
    if (!url) return '';
    if (/^data:/.test(url)) return '';
    if (/^https?:/.test(url)) return url;
    return new URL(url, $page.url.origin).href;
  }
  const seo = $derived($page.data.settings ?? {});
  const seoTitle = $derived(seo.seoHomeTitle || seo.seoTitle || `${seo.appName ?? 'MPSI'} Marketplace`);
  const seoDescription = $derived(seo.seoHomeDescription || seo.seoDescription || 'Marketplace untuk belanja produk lokal, elektronik, kebutuhan harian, dan toko terpercaya.');
  const seoImage = $derived(absoluteAsset(seo.seoHomeImage || seo.seoImage || ''));
  const canonicalUrl = $derived($page.url.origin + $page.url.pathname);
</script>

<svelte:head>
  <title>{seoTitle}</title>
  <meta name="description" content={seoDescription} />
  <link rel="canonical" href={canonicalUrl} />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content={seo.appName ?? 'MPSI Marketplace'} />
  <meta property="og:title" content={seoTitle} />
  <meta property="og:description" content={seoDescription} />
  <meta property="og:url" content={canonicalUrl} />
  {#if seoImage}
    <meta property="og:image" content={seoImage} />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta name="twitter:image" content={seoImage} />
  {/if}
  <meta name="twitter:card" content={seoImage ? 'summary_large_image' : 'summary'} />
  <meta name="twitter:title" content={seoTitle} />
  <meta name="twitter:description" content={seoDescription} />
</svelte:head>

<div class="container-x py-6 md:py-10 space-y-12 md:space-y-20">
  {#if settings.heroEnabled}
    <Hero />
  {/if}

  {#await data.streamed.home}
    <section>
      <div class="h-7 w-40 bg-ink-100 rounded animate-pulse mb-6"></div>
      <div class="flex flex-wrap gap-2">
        {#each Array(10) as _}
          <span class="h-8 w-24 bg-ink-100 animate-pulse rounded-full"></span>
        {/each}
      </div>
    </section>
    <section>
      <div class="h-7 w-56 bg-ink-100 rounded animate-pulse mb-6"></div>
      <ProductGridSkeleton count={12} />
    </section>
  {:then home}
    <section class="mx-auto max-w-3xl">
      <SmartSearch placeholder="Cari produk, toko, brand, atau tag" />
    </section>

    <HomeCategoryNav categories={home.categories ?? []} />

    {#if home.flashSale?.length}
      <section>
        <div class="flex items-end justify-between mb-6 sm:mb-8">
          <div>
            <div class="section-eyebrow text-amber-600 mb-2 flex items-center gap-2">
              <Icon name="sparkles" size={12} /> Penawaran Terbatas
            </div>
            <h2 class="section-title">Penawaran terbaik hari ini</h2>
          </div>
          <a href="/products?flash=1" class="hidden sm:flex items-center gap-1 text-sm text-ink-700 hover:text-ink-950">Lihat semua <Icon name="arrow-right" size={14} /></a>
        </div>
        <ProductGrid products={home.flashSale} />
      </section>
    {/if}

    {#if home.official?.length}
      <section>
        <div class="flex items-end justify-between mb-6 sm:mb-8">
          <div>
            <div class="section-eyebrow mb-2">Toko Resmi</div>
            <h2 class="section-title">Brand pilihan, terverifikasi</h2>
          </div>
          <a href="/vendors?f=official" class="hidden sm:flex items-center gap-1 text-sm text-ink-700 hover:text-ink-950">Lihat semua <Icon name="arrow-right" size={14} /></a>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
          {#each home.official as v (v.id)}
            <a href={v.username ? `/${v.username}` : `/vendors/${v.id}`} class="group block">
              <div class="aspect-square rounded-2xl bg-ink-100 overflow-hidden mb-2 group-hover:scale-[1.02] transition-transform">
                <img src={v.avatar} alt={v.name} class="w-full h-full object-cover" />
              </div>
              <div class="font-medium text-sm text-ink-900 truncate">{v.name}</div>
              <div class="text-xs text-ink-500">{v.city}</div>
            </a>
          {/each}
        </div>
      </section>
    {/if}

    {#if home.recommended?.length}
      <section>
        <div class="flex items-end justify-between mb-6 sm:mb-8">
          <div>
            <div class="section-eyebrow mb-2">Rekomendasi</div>
            <h2 class="section-title">Produk pilihan untuk Anda</h2>
          </div>
        </div>
        <ProductGrid products={home.recommended} />
      </section>
    {/if}

    {#if home.tags?.length}
      <section>
        <div class="flex items-end justify-between mb-5">
          <div>
            <div class="section-eyebrow mb-2">Jelajahi</div>
            <h2 class="section-title">Tag populer</h2>
          </div>
          <a href="/products" class="hidden sm:flex items-center gap-1 text-sm text-ink-700 hover:text-ink-950">Semua produk <Icon name="arrow-right" size={14} /></a>
        </div>
        <div class="flex flex-wrap gap-2">
          {#each home.tags.slice(0, 10) as t}
            <a href={`/products?tag=${t.slug}`} class="px-3 py-1.5 rounded-full bg-ink-100 hover:bg-app-primary hover:text-app-pfg text-xs sm:text-sm transition-colors">
              #{t.slug} <span class="text-[11px] opacity-60">({t.count})</span>
            </a>
          {/each}
        </div>
      </section>
    {/if}
  {/await}

  <section class="border-t border-ink-100 pt-12 sm:pt-16">
    <div class="grid sm:grid-cols-3 gap-8 text-center">
      {#each [{ i:'shield', t:'Pembayaran terjamin', d:'Dana ditahan hingga barang Anda terima.' },
              { i:'truck', t:'Pengiriman cepat', d:'kurir resmi, tracking real-time.' },
              { i:'headphones', t:'Dukungan 24/7', d:'Tim siap membantu kapan saja.' }] as item}
        <div>
          <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-ink-100 grid place-items-center">
            <Icon name={item.i} size={20} class="text-ink-950" />
          </div>
          <h3 class="font-semibold mb-1">{item.t}</h3>
          <p class="text-sm text-ink-500">{item.d}</p>
        </div>
      {/each}
    </div>
  </section>
</div>
