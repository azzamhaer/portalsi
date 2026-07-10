<script lang="ts">
  import { onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp, calcDiscount } from '$lib/utils';
  import { cart, auth, toast, wishlist, settings } from '$lib/stores.svelte';
  import { apiBaseUrl, apiEndpoints } from '$lib/api';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import VendorBadge from '$lib/components/VendorBadge.svelte';
  import ReportButton from '$lib/components/ReportButton.svelte';
  import { loginHref } from '$lib/stores.svelte';

  let { data } = $props();
  const p = $derived(data.product);
  const v = $derived(p.vendor);
  const disc = $derived(calcDiscount(p.price, p.original_price));
  let qty = $state(1);
  const inWishlist = $derived(wishlist.has(p.id));
  const isAdmin = $derived(auth.user?.role === 'ADMIN');
  const isOutOfStock = $derived((p.stock ?? 0) <= 0);
  const productUrl = $derived($page.url.origin + $page.url.pathname);
  const shareDomain = $derived($page.url.hostname.replace(/^www\./, ''));
  const seoDescription = $derived(String(p.description || `${p.name} dari ${v.name}`).replace(/\s+/g, ' ').slice(0, 155));
  const shareTitle = $derived(`${p.name} - ${fmtRp(p.price)}`);
  const shareText = $derived(`${shareTitle} di ${settings.appName ?? 'MPSI'}`);
  const shareMessage = $derived(`Temukan ${p.name} seharga ${fmtRp(p.price)} di ${settings.appName ?? 'MPSI'}.\n${productUrl}`);
  const canNativeShare = $derived(typeof navigator !== 'undefined' && 'share' in navigator);
  let shareOpen = $state(false);

  // Gallery state
  const images = $derived.by(() => {
    const arr = Array.isArray(p?.images) ? p.images.filter((x: any) => typeof x === 'string' && x) : [];
    return arr.length ? arr : [p?.image].filter(Boolean);
  });
  const apiBase = apiBaseUrl;
  const productShareImageUrl = $derived(`${apiBase}/products/${encodeURIComponent(p.slug || p.id)}/share-image`);
  const seoImage = $derived(productShareImageUrl);
  let activeImgIdx = $state(0);
  const previewImage = $derived(images[activeImgIdx] || images[0] || p.image || productShareImageUrl);
  let zoomOpen = $state(false);

  function nextImg() { activeImgIdx = (activeImgIdx + 1) % images.length; }
  function prevImg() { activeImgIdx = (activeImgIdx - 1 + images.length) % images.length; }

  // Variants state: supports old ["Merah"] and new [{ label, image }].
  const variantNames = $derived(p?.variants && typeof p.variants === 'object' ? Object.keys(p.variants) : []);
  let pickedVariants = $state<Record<string, string>>({});
  let pickedVariantDetails = $state<Record<string, any>>({});

  function optLabel(opt: any) {
    return typeof opt === 'string' ? opt : (opt?.label ?? opt?.name ?? opt?.value ?? '');
  }
  function optImage(opt: any) {
    return typeof opt === 'object' ? (opt?.image ?? '') : '';
  }
  function pickVariant(name: string, opt: any) {
    const label = optLabel(opt);
    pickedVariants = { ...pickedVariants, [name]: label };
    pickedVariantDetails = { ...pickedVariantDetails, [name]: { label, image: optImage(opt) || null } };
    const image = optImage(opt);
    if (image) {
      const idx = images.findIndex((src: string) => src === image);
      if (idx >= 0) activeImgIdx = idx;
    }
  }

  function variantText(): string | null {
    if (!variantNames.length) return null;
    return variantNames.map((k) => `${k}: ${pickedVariants[k] ?? '-'}`).join(', ');
  }
  function allVariantsPicked(): boolean {
    return variantNames.every((k) => !!pickedVariants[k]);
  }

  // Reviews state
  let reviews = $state<any[]>(p?.reviews ?? []);
  let canReview = $state(false);
  let hasPurchased = $state(false);
  let alreadyReviewed = $state(false);
  let myRating = $state(5);
  let myComment = $state('');
  let submittingReview = $state(false);
  let chatOpen = $state(false);
  let chatMessage = $state('Hai, apakah barang ini masih tersedia?');
  let sendingChat = $state(false);
  const chatTemplates = [
    'Hai, apakah barang ini masih tersedia?',
    'Apakah produk ini original dan bergaransi?',
    'Bisa dikirim hari ini?',
    'Apakah stok untuk varian yang saya pilih masih ada?'
  ];

  onMount(async () => {
    reviews = p?.reviews ?? [];
    if (auth.user) {
      try {
        const r: any = await apiEndpoints.canReviewProduct(p.id);
        canReview = r.can_review;
        hasPurchased = r.has_purchased;
        alreadyReviewed = r.already_reviewed;
      } catch {}
    }
    const action = $page.url.searchParams.get('resume_action');
    if (auth.user && action) {
      const clean = new URL($page.url);
      clean.searchParams.delete('resume_action');
      history.replaceState(null, '', clean.pathname + clean.search);
      if (action === 'cart') addToCart();
      if (action === 'wishlist') await toggleWish();
      if (action === 'buy') buyNow();
    }
  });

  async function submitReview() {
    if (!myComment.trim()) { toast.error('Tulis ulasan dulu'); return; }
    submittingReview = true;
    try {
      const r: any = await apiEndpoints.submitReview(p.id, myRating, myComment.trim());
      reviews = [r, ...reviews];
      canReview = false; alreadyReviewed = true;
      myComment = ''; myRating = 5;
      toast.success('Ulasan terkirim, terima kasih!');
    } catch (e: any) { toast.error(e.message); } finally { submittingReview = false; }
  }

  const avgRating = $derived(
    reviews.length
      ? (reviews.reduce((s, r) => s + r.rating, 0) / reviews.length)
      : (p?.rating ?? 5)
  );

  function addToCart() {
    if (auth.user?.role === 'ADMIN') { toast.warn('Admin tidak bisa berbelanja'); return false; }
    if (!auth.user) { goto(loginHref($page.url.pathname + $page.url.search, 'cart')); return false; }
    if (isOutOfStock) {
      toast.warn('Stok habis. Masukkan ke wishlist untuk diberi notifikasi ketika stok tersedia kembali.');
      return false;
    }
    if (variantNames.length && !allVariantsPicked()) {
      toast.warn('Pilih ' + variantNames.join(' & ') + ' dulu');
      return false;
    }
    const added = cart.add({
      cart_key: `${p.id}:${variantText() || ''}`,
      product_id: p.id, product_slug: p.slug, name: p.name, image: p.image, price: p.price,
      stock: p.stock, vendor_id: v.id, vendor_name: v.name, vendor_username: v.username,
      variant_selection: variantText(), variant_details: pickedVariantDetails, qty
    });
    if (!added) {
      toast.warn('Stok habis. Masukkan ke wishlist untuk diberi notifikasi ketika stok tersedia kembali.');
      return false;
    }
    toast.success('Ditambahkan ke keranjang');
    return true;
  }
  function buyNow() {
    if (auth.user?.role === 'ADMIN') { toast.warn('Admin tidak bisa berbelanja'); return; }
    if (!auth.user) { goto(loginHref($page.url.pathname + $page.url.search, 'buy')); return; }
    if (addToCart()) goto('/checkout');
  }
  async function toggleWish() {
    if (!auth.user) { goto(loginHref($page.url.pathname + $page.url.search, 'wishlist')); return; }
    wishlist.toggle(p.id);
    try { await apiEndpoints.toggleWishlist(p.id); toast.success(inWishlist ? 'Dihapus dari wishlist' : 'Ditambahkan ke wishlist'); }
    catch (e: any) { wishlist.toggle(p.id); toast.error(e.message); }
  }
  async function chatVendor() {
    if (!auth.user) { goto(loginHref($page.url.pathname + $page.url.search)); return; }
    chatOpen = true;
  }
  async function sendChatMessage() {
    if (!chatMessage.trim()) { toast.warn('Pilih atau tulis pesan dulu'); return; }
    sendingChat = true;
    try {
      const t: any = await apiEndpoints.startChat(v.id, p.id, chatMessage.trim());
      goto(`/chats/${t.id}`);
    } catch (e: any) { toast.error(e.message); }
    finally { sendingChat = false; }
  }
  async function shareProduct(kind: 'native' | 'copy' | 'whatsapp') {
    shareOpen = false;
    if (kind === 'whatsapp') {
      window.open(`https://wa.me/?text=${encodeURIComponent(shareMessage)}`, '_blank', 'noopener,noreferrer');
      return;
    }
    if (kind === 'native' && navigator.share) {
      try { await navigator.share({ title: shareTitle, text: seoDescription, url: productUrl }); return; }
      catch {}
    }
    await navigator.clipboard.writeText(productUrl);
    toast.success('Link produk disalin');
  }
</script>

<svelte:head>
  <title>{p.name} - {fmtRp(p.price)} | {settings.appName ?? 'MPSI'}</title>
  <meta name="description" content={seoDescription} />
  <link rel="canonical" href={productUrl} />
  <meta property="og:type" content="product" />
  <meta property="og:site_name" content={settings.appName ?? 'MPSI Marketplace'} />
  <meta property="og:title" content={shareTitle} />
  <meta property="og:description" content={seoDescription} />
  <meta property="og:url" content={productUrl} />
  <meta property="og:image" content={seoImage} />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="product:price:amount" content={String(p.price)} />
  <meta property="product:price:currency" content="IDR" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content={shareTitle} />
  <meta name="twitter:description" content={seoDescription} />
  <meta name="twitter:image" content={seoImage} />
</svelte:head>

<div class="container-x py-4 sm:py-6 md:py-10">
  <nav class="flex items-center gap-1 text-xs text-ink-500 mb-4 overflow-hidden whitespace-nowrap">
    <a href="/" class="hover:text-ink-900">Beranda</a>
    <Icon name="chevron-right" size={12} />
    <a href="/products" class="hover:text-ink-900">Produk</a>
    <Icon name="chevron-right" size={12} />
    <span class="text-ink-700 truncate">{p.name}</span>
  </nav>

  <div class="grid lg:grid-cols-2 gap-6 lg:gap-16 items-start">
    <div class="space-y-3">
      <div class="relative aspect-square rounded-2xl sm:rounded-3xl overflow-hidden bg-ink-50 group">
        <img src={images[activeImgIdx]} alt={p.name} class="w-full h-full object-cover cursor-zoom-in" on:click={() => zoomOpen = true} />
        {#if images.length > 1}
          <button type="button" on:click={prevImg} class="absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 w-10 h-10 grid place-items-center bg-white/90 backdrop-blur rounded-full shadow-soft hover:bg-white" aria-label="Sebelumnya">
            <Icon name="chevron-left" size={18} />
          </button>
          <button type="button" on:click={nextImg} class="absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 w-10 h-10 grid place-items-center bg-white/90 backdrop-blur rounded-full shadow-soft hover:bg-white" aria-label="Berikutnya">
            <Icon name="chevron-right" size={18} />
          </button>
          <span class="absolute bottom-3 right-3 bg-black/60 text-white text-xs px-2.5 py-1 rounded-full">{activeImgIdx + 1} / {images.length}</span>
        {/if}
      </div>
      {#if images.length > 1}
        <div class="grid grid-cols-5 sm:grid-cols-6 gap-2">
          {#each images.slice(0, 12) as src, i}
            <button type="button" on:click={() => activeImgIdx = i} class="aspect-square rounded-xl overflow-hidden bg-ink-50 border-2 transition" class:border-ink-950={i===activeImgIdx} class:border-transparent={i!==activeImgIdx}>
              <img src={src} alt="" class="w-full h-full object-cover" />
            </button>
          {/each}
        </div>
      {/if}
    </div>

    <div class="lg:sticky lg:top-24 space-y-5">
      <div>
        <div class="text-xs uppercase tracking-widest text-ink-500 mb-2">{v.name}</div>
        <h1 class="font-display text-2xl sm:text-3xl md:text-4xl font-bold tracking-tightest leading-tight text-balance">{p.name}</h1>
        <div class="flex items-center gap-3 sm:gap-4 mt-3 text-xs sm:text-sm text-ink-600 flex-wrap">
          <span class="flex items-center gap-1"><Icon name="star" size={14} class="text-amber-400" fill="currentColor" /> <b class="text-ink-900">{avgRating.toFixed(1)}</b> ({reviews.length} ulasan)</span>
          <span>·</span>
          <span><b class="text-ink-900">{p.sold.toLocaleString('id-ID')}</b> terjual</span>
        </div>
        {#if p.tags?.length}
          <div class="flex flex-wrap gap-1.5 mt-3">
            {#each p.tags as t}
              <a href={`/products?tag=${t}`} class="text-xs px-2.5 py-1 rounded-full bg-ink-100 hover:bg-app-primary hover:text-app-pfg transition">#{t}</a>
            {/each}
          </div>
        {/if}
        <div class="mt-4">
          <button type="button" on:click={() => shareOpen = true} class="btn-outline btn-sm">
            <Icon name="share-2" size={13} /> Bagikan
          </button>
        </div>
      </div>

      <div class="border-t border-b border-ink-100 py-5">
        <div class="flex items-baseline gap-3 flex-wrap">
          <span class="font-display text-3xl sm:text-4xl font-bold tracking-tightest text-ink-950">{fmtRp(p.price)}</span>
          {#if p.original_price && p.original_price > p.price}
            <span class="text-sm sm:text-base text-ink-400 line-through">{fmtRp(p.original_price)}</span>
            <span class="pill-ink !bg-app-primary !text-app-pfg">Hemat {disc}%</span>
          {/if}
        </div>
        {#if isOutOfStock}
          <div class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">
            <b>Stok habis.</b> Silahkan masukkan ke wishlist untuk diberikan notifikasi ketika stok ada kembali.
          </div>
        {/if}
      </div>

      <a href={v.username ? `/${v.username}` : `/vendors/${v.id}`} class="flex items-center gap-3 p-3 sm:p-4 rounded-2xl bg-ink-50 hover:bg-ink-100 transition-colors">
        <img src={v.avatar} alt="" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover" />
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-sm truncate flex items-center gap-1.5">
            {v.name}
            {#if v.badge}<VendorBadge badge={v.badge} size={14} />{/if}
            {#if v.is_official}<span class="pill-ink text-[10px]">Resmi</span>{/if}
          </div>
          <div class="text-xs text-ink-500">{v.city} · {v.rating} <Icon name="star" size={10} class="inline text-amber-400" fill="currentColor" /></div>
        </div>
        <Icon name="store" size={18} class="text-ink-700 hidden sm:block" />
      </a>

      <!-- Variant picker -->
      {#if variantNames.length}
        <div class="space-y-3">
          {#each variantNames as vname}
            <div>
              <div class="text-sm font-medium mb-2">{vname}</div>
              <div class="flex flex-wrap gap-2">
                {#each p.variants[vname] as opt}
                  {@const label = optLabel(opt)}
                  {@const image = optImage(opt)}
                  <button type="button" on:click={() => pickVariant(vname, opt)}
                          class="{image ? 'w-[86px] min-h-[104px] rounded-2xl p-2' : 'px-3 py-1.5 rounded-full'} text-xs font-medium border transition
                                 {pickedVariants[vname] === label
                                    ? 'bg-blue-50 text-blue-700 border-blue-500 ring-1 ring-blue-500'
                                    : 'bg-white text-ink-700 border-ink-200 hover:border-ink-950'}">
                    {#if image}
                      <img src={image} alt="" class="mx-auto mb-1 h-14 w-14 rounded-lg object-cover" />
                      <span class="block truncate">{label}</span>
                    {:else}
                      {label}
                    {/if}
                  </button>
                {/each}
              </div>
            </div>
          {/each}
        </div>
      {/if}

      <div class="flex items-center gap-3 sm:gap-4">
        <span class="text-sm text-ink-700 w-16 sm:w-20">Jumlah</span>
        <div class="inline-flex items-center border border-ink-200 rounded-full">
          <button on:click={() => qty = Math.max(1, qty-1)} disabled={isOutOfStock} class="w-9 h-9 grid place-items-center hover:bg-ink-50 rounded-l-full disabled:opacity-40"><Icon name="minus" size={14} /></button>
          <input type="number" bind:value={qty} min="1" max={p.stock} disabled={isOutOfStock} class="w-12 text-center bg-transparent text-sm outline-none disabled:opacity-40" />
          <button on:click={() => qty = Math.min(p.stock, qty+1)} disabled={isOutOfStock} class="w-9 h-9 grid place-items-center hover:bg-ink-50 rounded-r-full disabled:opacity-40"><Icon name="plus" size={14} /></button>
        </div>
        <span class="text-xs {isOutOfStock ? 'text-red-600' : 'text-ink-500'}">Stok <b class={isOutOfStock ? 'text-red-700' : 'text-ink-700'}>{p.stock}</b></span>
      </div>

      <!-- Action buttons -->
      <!-- Mobile: cart=icon, beli=text; Desktop: keduanya pakai text + icon -->
      {#if auth.user?.role === 'ADMIN'}
        <div class="bg-amber-50 text-amber-800 text-xs p-3 rounded-xl flex items-center gap-2">
          <Icon name="shield-alert" size={14} /> Anda login sebagai admin. Fitur belanja & chat dinonaktifkan.
        </div>
      {/if}

      <div class="flex gap-2 sm:gap-3">
        <!-- Tambah keranjang -->
        <button on:click={addToCart} disabled={isAdmin || isOutOfStock}
                class="rounded-full transition inline-flex items-center justify-center border border-ink-300
                       h-11 w-11 sm:h-auto sm:w-auto sm:px-5 sm:py-3 sm:gap-2 sm:flex-1 shrink-0
                       {isAdmin || isOutOfStock ? 'opacity-40 cursor-not-allowed' : 'hover:bg-ink-50'}"
                aria-label="Tambah ke keranjang">
          <Icon name="shopping-bag" size={18} />
          <span class="hidden sm:inline text-sm font-medium">Keranjang</span>
        </button>
        <!-- Beli sekarang -->
        <button on:click={buyNow} disabled={isAdmin || isOutOfStock}
                class="btn-primary rounded-full inline-flex items-center justify-center gap-2 h-11 sm:h-auto sm:py-3 flex-1 text-sm font-medium px-4
                       {isAdmin || isOutOfStock ? 'opacity-40 cursor-not-allowed' : ''}">
          <Icon name="zap" size={16} />
          <span>{isOutOfStock ? 'Stok Habis' : 'Beli Sekarang'}</span>
        </button>
        <!-- Wishlist -->
        <button on:click={toggleWish} disabled={isAdmin}
                class="rounded-full transition inline-flex items-center justify-center h-11 w-11 sm:h-12 sm:w-12 shrink-0
                       {isAdmin ? 'opacity-40 cursor-not-allowed border border-ink-300' :
                         (inWishlist ? 'bg-red-500 text-white hover:bg-red-600' : 'border border-ink-300 hover:bg-ink-50')}"
                aria-label="Wishlist">
          <Icon name="heart" size={18} fill={inWishlist && !isAdmin ? 'currentColor' : 'none'} />
        </button>
      </div>

      {#if auth.user?.role !== 'ADMIN'}
        <button on:click={chatVendor} class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-full border border-ink-200 hover:bg-ink-50 text-sm font-medium transition">
          <Icon name="message-circle" size={16} /> Tanyakan barang ini ke penjual
        </button>
        <div class="flex justify-end">
          <ReportButton targetType="PRODUCT" targetId={p.id} targetName={p.name} label="Laporkan produk ini" />
        </div>
      {/if}

      <div class="grid grid-cols-2 gap-3">
        <div class="flex items-start gap-2 p-3 rounded-xl bg-ink-50">
          <Icon name="truck" size={16} class="text-ink-700 shrink-0 mt-0.5" />
          <div>
            <div class="text-xs font-semibold">Cepat</div>
            <div class="text-[11px] text-ink-500">{v.city} · 2-4 hari</div>
          </div>
        </div>
        <div class="flex items-start gap-2 p-3 rounded-xl bg-ink-50">
          <Icon name="shield-check" size={16} class="text-emerald-600 shrink-0 mt-0.5" />
          <div>
            <div class="text-xs font-semibold">100% Original</div>
            <div class="text-[11px] text-ink-500">Garansi 7 hari</div>
          </div>
        </div>
      </div>

      <div class="rounded-xl bg-ink-50 p-3 text-sm">
        <div class="text-xs font-semibold text-ink-500 mb-1">Spesifikasi</div>
        <div class="flex justify-between gap-3">
          <span class="text-ink-600">Berat</span>
          <b>{p.weight >= 1000 ? `${(p.weight / 1000).toLocaleString('id-ID')} kg` : `${p.weight} gram`}</b>
        </div>
      </div>
    </div>
  </div>

  <section class="mt-12 sm:mt-16 max-w-4xl">
    <div class="section-eyebrow mb-2">Deskripsi</div>
    <h2 class="text-xl sm:text-2xl font-bold tracking-tightest mb-5">Tentang produk ini</h2>
    <p class="text-base text-ink-700 leading-relaxed whitespace-pre-line">{p.description}</p>
  </section>

  <!-- Reviews -->
  <section class="mt-12 sm:mt-16">
    <div class="section-eyebrow mb-2">Ulasan</div>
    <div class="flex items-end justify-between flex-wrap gap-4 mb-6">
      <h2 class="text-xl sm:text-2xl font-bold tracking-tightest">Ulasan pembeli ({reviews.length})</h2>
      <div class="flex items-center gap-2 text-sm">
        <Icon name="star" size={16} class="text-amber-400" fill="currentColor" />
        <span class="font-bold text-lg">{avgRating.toFixed(1)}</span>
        <span class="text-ink-500">dari 5</span>
      </div>
    </div>

    {#if auth.user}
      {#if canReview}
        <div class="card mb-6 bg-ink-50">
          <h3 class="font-semibold mb-3">Beri ulasan Anda</h3>
          <div class="flex gap-1 mb-3">
            {#each [1,2,3,4,5] as n}
              <button type="button" on:click={() => myRating = n} aria-label={`${n} bintang`}>
                <Icon name="star" size={28} class={n <= myRating ? 'text-amber-400' : 'text-ink-300'} fill="currentColor" />
              </button>
            {/each}
            <span class="ml-2 text-sm text-ink-600 self-center">({myRating}/5)</span>
          </div>
          <textarea bind:value={myComment} class="input" rows={3} placeholder="Bagaimana pengalaman Anda dengan produk ini?"></textarea>
          <button on:click={submitReview} disabled={submittingReview || !myComment.trim()} class="btn-primary btn-md mt-3">
            {submittingReview ? 'Mengirim…' : 'Kirim ulasan'}
          </button>
        </div>
      {:else if alreadyReviewed}
        <div class="bg-emerald-50 text-emerald-800 text-sm p-3 rounded-xl mb-6 flex items-center gap-2">
          <Icon name="check-circle" size={16} /> Anda sudah memberi ulasan untuk produk ini. Terima kasih!
        </div>
      {:else if !hasPurchased}
        <div class="bg-amber-50 text-amber-800 text-sm p-3 rounded-xl mb-6 flex items-center gap-2">
          <Icon name="info" size={16} /> Hanya pembeli yang sudah menyelesaikan pesanan produk ini yang dapat memberikan ulasan.
        </div>
      {/if}
    {:else}
      <div class="bg-ink-50 text-ink-700 text-sm p-3 rounded-xl mb-6 flex items-center gap-2">
        <Icon name="info" size={16} /> <a href="/login" class="font-semibold underline">Login</a> dulu untuk memberi ulasan (setelah Anda menyelesaikan pembelian).
      </div>
    {/if}

    {#if reviews.length === 0}
      <div class="text-center py-10 text-ink-500 card">
        <Icon name="message-square" size={36} class="mx-auto mb-2 text-ink-300" />
        Belum ada ulasan. Jadilah yang pertama!
      </div>
    {:else}
      <div class="space-y-3">
        {#each reviews as r (r.id)}
          <div class="card">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-full bg-app-primary text-app-pfg grid place-items-center font-semibold shrink-0">
                {(r.user?.name ?? 'U')[0].toUpperCase()}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                  <div class="font-semibold text-sm">{r.user?.name ?? 'Anonim'}</div>
                  <div class="text-xs text-ink-500">{new Date(r.created_at).toLocaleDateString('id-ID', { year:'numeric', month:'short', day:'numeric' })}</div>
                </div>
                <div class="flex gap-0.5 my-1">
                  {#each [1,2,3,4,5] as n}
                    <Icon name="star" size={14} class={n <= r.rating ? 'text-amber-400' : 'text-ink-200'} fill="currentColor" />
                  {/each}
                </div>
                <p class="text-sm text-ink-700 leading-relaxed whitespace-pre-line">{r.comment}</p>
              </div>
            </div>
          </div>
        {/each}
      </div>
    {/if}
  </section>

  {#if data.related?.length}
    <section class="mt-16 sm:mt-20">
      <div class="section-eyebrow mb-2">Lainnya</div>
      <h2 class="section-title mb-6 sm:mb-8">Produk serupa</h2>
      <ProductGrid products={data.related} />
    </section>
  {/if}
</div>

{#if shareOpen}
  <div class="fixed inset-0 z-50 grid place-items-end bg-black/40 p-0 backdrop-blur-sm sm:place-items-center sm:p-4" role="dialog" aria-modal="true">
    <button type="button" class="absolute inset-0 cursor-default" aria-label="Tutup popup share" on:click={() => shareOpen = false}></button>
    <div class="relative w-full rounded-t-[24px] bg-white p-4 shadow-elevated sm:max-w-md sm:rounded-2xl">
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <h2 class="text-base font-bold text-ink-950">Bagikan produk</h2>
          <p class="mt-0.5 text-xs text-ink-500">Preview link akan mengikuti metadata halaman produk.</p>
        </div>
        <button type="button" on:click={() => shareOpen = false} class="grid h-9 w-9 place-items-center rounded-full hover:bg-ink-100" aria-label="Tutup">
          <Icon name="x" size={18} />
        </button>
      </div>

      <div class="overflow-hidden rounded-2xl border border-ink-100 bg-white">
        <div class="aspect-[1.91/1] bg-ink-50">
          <img src={previewImage} alt="" class="h-full w-full object-cover" />
        </div>
        <div class="border-t border-ink-100 p-3">
          <div class="line-clamp-2 text-sm font-semibold text-ink-950">{shareTitle}</div>
          <div class="mt-1 line-clamp-2 text-xs leading-relaxed text-ink-500">{seoDescription}</div>
          <div class="mt-3 flex items-center gap-2 text-xs text-ink-500">
            <Icon name="link" size={13} />
            {shareDomain}
          </div>
        </div>
      </div>

      <div class="mt-4 grid gap-2">
        <button type="button" on:click={() => shareProduct('whatsapp')} class="flex items-center gap-3 rounded-xl border border-ink-100 px-3 py-3 text-left text-sm font-semibold transition hover:bg-ink-50">
          <span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-50 text-emerald-700"><Icon name="message-circle" size={18} /></span>
          Share ke WhatsApp
        </button>
        {#if canNativeShare}
          <button type="button" on:click={() => shareProduct('native')} class="flex items-center gap-3 rounded-xl border border-ink-100 px-3 py-3 text-left text-sm font-semibold transition hover:bg-ink-50">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-ink-100 text-ink-700"><Icon name="send" size={17} /></span>
            Share lewat perangkat
          </button>
        {/if}
        <button type="button" on:click={() => shareProduct('copy')} class="flex items-center gap-3 rounded-xl border border-ink-100 px-3 py-3 text-left text-sm font-semibold transition hover:bg-ink-50">
          <span class="grid h-9 w-9 place-items-center rounded-full bg-ink-100 text-ink-700"><Icon name="copy" size={17} /></span>
          Salin link produk
        </button>
      </div>
    </div>
  </div>
{/if}

<!-- Zoom lightbox -->
{#if zoomOpen}
  <div class="fixed inset-0 z-50 bg-black/90 grid place-items-center p-4 animate-fadeIn" on:click={() => zoomOpen = false} role="dialog" aria-modal="true">
    <button type="button" on:click={() => zoomOpen = false} class="absolute top-4 right-4 w-10 h-10 grid place-items-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Tutup">
      <Icon name="x" size={20} />
    </button>
    {#if images.length > 1}
      <button type="button" on:click|stopPropagation={prevImg} class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 grid place-items-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Sebelumnya">
        <Icon name="chevron-left" size={24} />
      </button>
      <button type="button" on:click|stopPropagation={nextImg} class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 grid place-items-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Berikutnya">
        <Icon name="chevron-right" size={24} />
      </button>
      <span class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-white/10 text-white text-xs px-3 py-1.5 rounded-full">{activeImgIdx + 1} / {images.length}</span>
    {/if}
    <img src={images[activeImgIdx]} alt={p.name} class="max-w-[95vw] max-h-[90vh] object-contain rounded-xl" on:click|stopPropagation />
  </div>
{/if}

{#if chatOpen}
  <div class="fixed inset-0 z-50 grid place-items-end bg-black/50 p-0 backdrop-blur-sm sm:place-items-center sm:p-4" role="dialog" aria-modal="true">
    <div class="w-full rounded-t-[28px] bg-white p-5 shadow-elevated sm:max-w-md sm:rounded-[28px]">
      <div class="mb-4 flex items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold">Tanyakan ke penjual</h2>
          <p class="mt-1 text-sm text-ink-500">Pilih template atau tulis pesan sendiri.</p>
        </div>
        <button type="button" on:click={() => chatOpen = false} class="grid h-9 w-9 place-items-center rounded-full hover:bg-ink-100"><Icon name="x" size={18} /></button>
      </div>
      <div class="mb-3 flex gap-2 overflow-x-auto pb-1 no-scrollbar">
        {#each chatTemplates as t}
          <button type="button" on:click={() => chatMessage = t} class="min-w-[210px] rounded-2xl border border-ink-100 bg-ink-50 px-3 py-2 text-left text-xs hover:border-ink-300">
            {t}
          </button>
        {/each}
      </div>
      <textarea bind:value={chatMessage} class="input" rows={4} placeholder="Tulis pertanyaan Anda"></textarea>
      <button type="button" on:click={sendChatMessage} disabled={sendingChat || !chatMessage.trim()} class="btn-primary btn-lg mt-4 w-full">
        <Icon name="send" size={15} /> {sendingChat ? 'Mengirim...' : 'Kirim pertanyaan'}
      </button>
    </div>
  </div>
{/if}
