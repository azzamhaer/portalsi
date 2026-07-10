<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import NotificationBell from '$lib/components/NotificationBell.svelte';
  import { afterNavigate, goto } from '$app/navigation';
  import { auth, cart, settings, wishlist, confirmDialog } from '$lib/stores.svelte';
  import { setToken, apiEndpoints } from '$lib/api';
  import { onMount } from 'svelte';

  let q = $state('');
  let mobileOpen = $state(false);
  let userOpen = $state(false);
  let userMenuRef: HTMLDivElement | null = $state(null);
  let searchBoxRef: HTMLDivElement | null = $state(null);

  // Live search suggestions
  let suggestOpen = $state(false);
  let suggestions = $state<{ products: any[]; vendors: any[]; tags: any[] }>({ products: [], vendors: [], tags: [] });
  let suggestLoading = $state(false);
  let suggestTimer: any;
  let unreadChats = $state(0);
  let activeOrders = $state(0);
  let chatPoll: any;

  function fetchSuggest(query: string) {
    clearTimeout(suggestTimer);
    if (!query.trim()) {
      suggestions = { products: [], vendors: [], tags: [] };
      suggestOpen = false;
      return;
    }
    suggestTimer = setTimeout(async () => {
      suggestLoading = true;
      try {
        const r: any = await apiEndpoints.searchSuggest(query.trim());
        suggestions = r;
        suggestOpen = true;
      } catch {} finally { suggestLoading = false; }
    }, 250);
  }
  function onQueryInput() { fetchSuggest(q); }

  function search(e: Event) {
    e.preventDefault();
    if (!q.trim()) return;
    suggestOpen = false;
    goto('/search?q=' + encodeURIComponent(q.trim()));
    mobileOpen = false;
  }
  function pickSuggestion(href: string) {
    suggestOpen = false;
    mobileOpen = false;
    q = '';
    goto(href);
  }

  async function logout() {
    const ok = await confirmDialog.ask({
      title: 'Keluar dari akun?',
      message: 'Anda perlu login ulang untuk mengakses pesanan, chat, dan seller center.',
      confirmText: 'Keluar',
      tone: 'danger'
    });
    if (!ok) return;
    try { await apiEndpoints.logout(); } catch {}
    setToken(null);
    auth.clear();
    cart.clear();
    wishlist.clear();
    userOpen = false;
    goto('/');
  }

  function nav(href: string) {
    userOpen = false;
    mobileOpen = false;
    goto(href);
  }

  function badgeText(value: number) {
    return value > 99 ? '99+' : String(value);
  }

  function authedHref(href: string) {
    return auth.user ? href : `/login?next=${encodeURIComponent(href)}`;
  }

  afterNavigate(() => {
    mobileOpen = false;
    userOpen = false;
    suggestOpen = false;
  });

  onMount(() => {
    async function refreshHeaderBadges() {
      if (!auth.user) { unreadChats = 0; activeOrders = 0; return; }
      try {
        const [chat, orders]: any[] = await Promise.all([
          apiEndpoints.chatsUnreadCount().catch(() => ({ count: 0 })),
          apiEndpoints.ordersActiveCount().catch(() => ({ count: 0 })),
        ]);
        unreadChats = chat.count ?? 0;
        activeOrders = orders.count ?? 0;
      } catch {}
    }
    refreshHeaderBadges();
    chatPoll = setInterval(refreshHeaderBadges, 30000);
    function onDocClick(e: MouseEvent) {
      if (userOpen && userMenuRef && !userMenuRef.contains(e.target as Node)) {
        userOpen = false;
      }
      if (suggestOpen && searchBoxRef && !searchBoxRef.contains(e.target as Node)) {
        suggestOpen = false;
      }
    }
    function onEsc(e: KeyboardEvent) {
      if (e.key === 'Escape') { userOpen = false; mobileOpen = false; suggestOpen = false; }
    }
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onEsc);
    return () => {
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onEsc);
      clearInterval(chatPoll);
    };
  });
</script>

<header class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-ink-100">
  <div class="container-x">
    <div class="flex items-center gap-3 sm:gap-6 h-16">
      <a href="/" class="flex items-center gap-2 font-display font-extrabold text-lg sm:text-xl tracking-tightest text-ink-950 shrink-0">
        {#if settings.logo}
          <img src={settings.logo} alt={settings.appName} class="w-8 h-8 rounded-lg object-cover" />
        {:else}
          <span class="w-8 h-8 bg-app-primary text-app-pfg rounded-lg grid place-items-center text-sm">{settings.appName?.[0] ?? 'P'}</span>
        {/if}
        <span class="hidden xs:inline">{settings.appName ?? 'MPSI'}</span>
      </a>

      <nav class="hidden lg:flex items-center gap-7 text-sm text-ink-700">
        <a href="/" class="hover:text-ink-950 transition-colors">Beranda</a>
        <a href="/products" class="hover:text-ink-950 transition-colors">Produk</a>
        {#if !settings.hiddenPages.includes('vendors')}<a href="/vendors" class="hover:text-ink-950 transition-colors">Toko</a>{/if}
        {#if !settings.hiddenPages.includes('payment-info')}<a href="/payment-info" class="hover:text-ink-950 transition-colors">Pembayaran</a>{/if}
        {#if !settings.hiddenPages.includes('help')}<a href="/help" class="hover:text-ink-950 transition-colors">Bantuan</a>{/if}
      </nav>

      <div class="ml-auto hidden md:block relative" bind:this={searchBoxRef}>
        <form on:submit={search} class="flex items-center gap-2 bg-ink-50 hover:bg-ink-100 transition-colors rounded-full pl-4 pr-1.5 py-1.5 w-72 lg:w-96">
          <Icon name="search" size={16} class="text-ink-400" />
          <input bind:value={q} on:input={onQueryInput} on:focus={() => { if (q.trim()) suggestOpen = true; }} type="text" placeholder="Cari produk, brand, atau toko" class="flex-1 bg-transparent text-sm outline-none placeholder:text-ink-400" />
          <button type="submit" class="text-xs bg-app-primary text-app-pfg px-3.5 py-1.5 rounded-full font-medium hover:bg-ink-800 transition-colors">Cari</button>
        </form>
        {#if suggestOpen && (suggestions.products.length || suggestions.vendors.length || suggestions.tags.length || suggestLoading || q.trim())}
          <div class="absolute left-0 right-0 top-full mt-1 bg-white rounded-2xl shadow-elevated border border-ink-100 p-2 animate-fadeIn z-50 max-h-[400px] overflow-y-auto">
            {#if suggestLoading}
              <div class="text-xs text-ink-500 p-3">Mencari…</div>
            {:else}
              {#if q.trim()}
                <button type="button" on:click={search} class="mb-1 flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left hover:bg-ink-50">
                  <span class="grid h-9 w-9 place-items-center rounded-xl bg-app-primary/10 text-app-primary">
                    <Icon name="search" size={15} />
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">Cari kata kunci "{q.trim()}"</span>
                    <span class="block text-xs text-ink-500">Lihat semua hasil yang cocok</span>
                  </span>
                </button>
              {/if}
              {#if suggestions.products.length}
                <div class="text-[10px] uppercase tracking-widest text-ink-400 px-3 py-1.5">Produk</div>
                {#each suggestions.products.slice(0, 5) as p}
                  <button type="button" on:click={() => pickSuggestion(`/product/${p.slug || p.id}`)} class="flex items-center gap-2 w-full px-2 py-1.5 rounded-lg hover:bg-ink-50 text-left">
                    <img src={p.image} alt="" class="w-9 h-9 rounded-lg object-cover shrink-0" />
                    <div class="flex-1 min-w-0">
                      <div class="text-sm font-medium truncate">{p.name}</div>
                      <div class="text-xs text-ink-500">Rp {p.price.toLocaleString('id-ID')}</div>
                    </div>
                  </button>
                {/each}
              {/if}
              {#if suggestions.vendors.length}
                <div class="text-[10px] uppercase tracking-widest text-ink-400 px-3 py-1.5 mt-1">Toko</div>
                {#each suggestions.vendors.slice(0, 4) as v}
                  <button type="button" on:click={() => pickSuggestion(v.username ? `/${v.username}` : `/vendors/${v.id}`)} class="flex items-center gap-2 w-full px-2 py-1.5 rounded-lg hover:bg-ink-50 text-left">
                    <img src={v.avatar} alt="" class="w-9 h-9 rounded-full object-cover shrink-0" />
                    <div class="flex-1 min-w-0">
                      <div class="text-sm font-medium truncate">{v.name}</div>
                      <div class="text-xs text-ink-500">@{v.username} · {v.city}</div>
                    </div>
                  </button>
                {/each}
              {/if}
              {#if suggestions.tags.length}
                <div class="text-[10px] uppercase tracking-widest text-ink-400 px-3 py-1.5 mt-1">Tag</div>
                <div class="flex flex-wrap gap-1.5 px-2 pb-2">
                  {#each suggestions.tags.slice(0, 6) as t}
                    <button type="button" on:click={() => pickSuggestion(`/products?tag=${t.slug}`)} class="text-xs px-2.5 py-1 rounded-full bg-ink-100 hover:bg-app-primary hover:text-app-pfg transition">#{t.slug} <span class="opacity-60">({t.product_count})</span></button>
                  {/each}
                </div>
              {/if}
              <div class="border-t border-ink-100 mt-1 pt-1">
                <button type="button" on:click={search} class="w-full text-sm px-3 py-2 rounded-lg hover:bg-ink-50 text-left flex items-center gap-2">
                  <Icon name="search" size={14} class="text-ink-400" /> Cari "{q}" di semua produk
                </button>
              </div>
            {/if}
          </div>
        {/if}
      </div>

      <div class="hidden md:flex items-center gap-1 ml-2">
        {#if auth.user?.role !== 'ADMIN'}
          <a href="/wishlist" class="relative w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100 transition-colors" aria-label="Wishlist">
            <Icon name="heart" size={18} class="text-ink-700" />
            {#if auth.user && wishlist.ids.length > 0}
              <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] grid place-items-center px-1.5">{wishlist.ids.length > 99 ? '99+' : wishlist.ids.length}</span>
            {/if}
          </a>
          <a href="/cart" class="w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100 transition-colors relative" aria-label="Keranjang">
            <Icon name="shopping-bag" size={18} class="text-ink-700" />
            {#if auth.user && cart.count > 0}
              <span class="absolute -top-0.5 -right-0.5 bg-app-primary text-app-pfg text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] grid place-items-center px-1.5">{cart.count}</span>
            {/if}
          </a>
        {/if}
        <NotificationBell />
        {#if auth.user}
          <a href="/chats" class="w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100 transition-colors relative" aria-label="Chat">
            <Icon name="message-circle" size={18} class="text-ink-700" />
            {#if unreadChats > 0}
              <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] grid place-items-center px-1.5">{unreadChats > 99 ? '99+' : unreadChats}</span>
            {/if}
          </a>
        {/if}
        {#if auth.user}
          <div class="relative" bind:this={userMenuRef}>
            <button type="button" on:click|stopPropagation={() => userOpen = !userOpen} class="w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100 transition-colors" aria-label="Akun" aria-expanded={userOpen}>
              <Icon name="user" size={18} class="text-ink-700" />
            </button>
            {#if userOpen}
              <div class="absolute right-0 top-full mt-1 w-56 bg-white rounded-2xl shadow-elevated border border-ink-100 p-2 animate-fadeIn z-50">
                <div class="px-3 py-2 border-b border-ink-100 mb-1">
                  <div class="font-semibold text-sm">{auth.user.name}</div>
                  <div class="text-xs text-ink-500 truncate">{auth.user.email}</div>
                </div>
                <button type="button" on:click={() => nav('/profile')}  class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Profil</button>
                {#if auth.user.role === 'ADMIN'}
                  <button type="button" on:click={() => nav('/admin')} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50 text-accent-dark font-semibold">Admin Center</button>
                {:else}
                  <button type="button" on:click={() => nav('/orders')}   class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Pesanan</button>
                  <button type="button" on:click={() => nav('/refunds')}  class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Riwayat Refund</button>
                  <button type="button" on:click={() => nav('/wishlist')} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Wishlist</button>
                  <button type="button" on:click={() => nav('/chats')}    class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Chat</button>
                  {#if auth.user.vendor_id}
                    <button type="button" on:click={() => nav(auth.user.vendor_status === 'APPROVED' ? '/seller/dashboard' : '/seller/pending')} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">
                      {auth.user.vendor_status === 'APPROVED' ? 'Seller Center' : 'Status Toko'}
                    </button>
                  {:else}
                    <button type="button" on:click={() => nav('/seller/register')} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Buka Toko</button>
                  {/if}
                {/if}
                <button type="button" on:click={logout} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50 text-red-600">Keluar</button>
              </div>
            {/if}
          </div>
        {:else}
          <a href="/login" class="btn-primary btn-md ml-2">Masuk</a>
        {/if}
      </div>

      <div class="ml-auto flex items-center gap-0.5 md:hidden">
        {#if auth.user?.role !== 'ADMIN'}
          <a href={authedHref('/wishlist')} class="relative grid h-9 w-9 place-items-center rounded-full hover:bg-ink-100" aria-label="Wishlist">
            <Icon name="heart" size={17} class="text-ink-700" />
            {#if auth.user && wishlist.ids.length > 0}
              <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">{badgeText(wishlist.ids.length)}</span>
            {/if}
          </a>
          <a href={authedHref('/cart')} class="relative grid h-9 w-9 place-items-center rounded-full hover:bg-ink-100" aria-label="Keranjang">
            <Icon name="shopping-bag" size={17} class="text-ink-700" />
            {#if auth.user && cart.count > 0}
              <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-app-primary px-1 text-[9px] font-bold text-app-pfg">{badgeText(cart.count)}</span>
            {/if}
          </a>
          <a href={authedHref('/orders')} class="relative grid h-9 w-9 place-items-center rounded-full hover:bg-ink-100" aria-label="Pesanan">
            <Icon name="receipt-text" size={17} class="text-ink-700" />
            {#if activeOrders > 0}
              <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-amber-500 px-1 text-[9px] font-bold text-white">{badgeText(activeOrders)}</span>
            {/if}
          </a>
          <a href={authedHref('/chats')} class="relative grid h-9 w-9 place-items-center rounded-full hover:bg-ink-100" aria-label="Chat">
            <Icon name="message-circle" size={17} class="text-ink-700" />
            {#if unreadChats > 0}
              <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">{badgeText(unreadChats)}</span>
            {/if}
          </a>
        {/if}
      </div>

      <button on:click={() => mobileOpen = !mobileOpen} class="md:hidden w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100" aria-label="Menu">
        <Icon name={mobileOpen ? 'x' : 'menu'} size={20} />
      </button>
    </div>
  </div>

  {#if mobileOpen}
    {@const isAdmin = auth.user?.role === 'ADMIN'}
    {@const navItems = isAdmin
      ? [
          ['Admin Center','/admin','layout-dashboard'],
          ['Cari toko','/vendors','store'],
          ['Opsi pembayaran','/payment-info','credit-card'],
          ['FAQ','/help','circle-help'],
        ]
      : [
          ['Cari toko','/vendors','store'],
          ['Opsi pembayaran','/payment-info','credit-card'],
          ['FAQ','/help','circle-help'],
        ]}
    <div class="md:hidden border-t border-ink-100 bg-white animate-fadeIn max-h-[calc(100vh-64px)] overflow-y-auto overscroll-contain">
      <div class="container-x py-4 space-y-3 pb-8">
        <form on:submit={search} class="flex items-center gap-2 bg-ink-50 rounded-full pl-4 pr-1.5 py-1.5">
          <Icon name="search" size={16} class="text-ink-400" />
          <input bind:value={q} on:input={onQueryInput} on:focus={() => { if (q.trim()) suggestOpen = true; }} type="text" placeholder="Cari produk" class="flex-1 bg-transparent text-sm outline-none" />
          <button type="submit" class="text-xs bg-app-primary text-app-pfg px-3 py-1.5 rounded-full">Cari</button>
        </form>
        {#if suggestOpen && (suggestions.products.length || suggestions.vendors.length || suggestions.tags.length || suggestLoading || q.trim())}
          <div class="rounded-2xl border border-ink-100 bg-white p-2 shadow-soft">
            {#if suggestLoading}
              <div class="p-3 text-xs text-ink-500">Mencari...</div>
            {:else}
              <button type="button" on:click={search} class="mb-1 flex w-full items-center gap-2 rounded-xl px-2 py-2 text-left hover:bg-ink-50">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-app-primary/10 text-app-primary"><Icon name="search" size={15} /></span>
                <span class="min-w-0 flex-1">
                  <span class="block text-sm font-semibold">Cari kata kunci "{q.trim()}"</span>
                  <span class="block text-xs text-ink-500">Lihat hasil lengkap</span>
                </span>
              </button>
              {#if suggestions.products.length}
                <div class="px-3 py-1.5 text-[10px] uppercase tracking-widest text-ink-400">Produk</div>
                {#each suggestions.products.slice(0, 4) as p}
                  <button type="button" on:click={() => pickSuggestion(`/product/${p.slug || p.id}`)} class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-ink-50">
                    <img src={p.image} alt="" class="h-9 w-9 shrink-0 rounded-lg object-cover" />
                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-sm font-medium">{p.name}</span>
                      <span class="block text-xs text-ink-500">Rp {p.price.toLocaleString('id-ID')}</span>
                    </span>
                  </button>
                {/each}
              {/if}
              {#if suggestions.vendors.length}
                <div class="mt-1 px-3 py-1.5 text-[10px] uppercase tracking-widest text-ink-400">Toko</div>
                {#each suggestions.vendors.slice(0, 3) as v}
                  <button type="button" on:click={() => pickSuggestion(v.username ? `/${v.username}` : `/vendors/${v.id}`)} class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-ink-50">
                    <img src={v.avatar} alt="" class="h-9 w-9 shrink-0 rounded-full object-cover" />
                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-sm font-medium">{v.name}</span>
                      <span class="block text-xs text-ink-500">@{v.username}</span>
                    </span>
                  </button>
                {/each}
              {/if}
              {#if suggestions.tags.length}
                <div class="mt-1 px-3 py-1.5 text-[10px] uppercase tracking-widest text-ink-400">Tag</div>
                <div class="flex flex-wrap gap-1.5 px-2 pb-1">
                  {#each suggestions.tags.slice(0, 5) as t}
                    <button type="button" on:click={() => pickSuggestion(`/products?tag=${t.slug}`)} class="rounded-full bg-ink-100 px-2.5 py-1 text-xs hover:bg-app-primary hover:text-app-pfg">#{t.slug}</button>
                  {/each}
                </div>
              {/if}
            {/if}
          </div>
        {/if}
        <nav class="grid gap-1 text-sm">
          {#each navItems as [label, href, icon]}
            <a {href} on:click={() => mobileOpen = false} class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-ink-50">
              <Icon name={icon} size={16} class="text-ink-500" />
              <span>{label}</span>
            </a>
          {/each}
          {#if auth.user}
            {#if !isAdmin}
              {#if auth.user.vendor_id}
                <a href={auth.user.vendor_status === 'APPROVED' ? '/seller/dashboard' : '/seller/pending'} on:click={() => mobileOpen = false} class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-ink-50">
                  <Icon name={auth.user.vendor_status === 'APPROVED' ? 'store' : 'clock'} size={16} class="text-ink-500" />
                  <span>{auth.user.vendor_status === 'APPROVED' ? 'Seller Center' : 'Status Toko'}</span>
                </a>
              {:else}
                <a href="/seller/register" on:click={() => mobileOpen = false} class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-ink-50">
                  <Icon name="store" size={16} class="text-ink-500" />
                  <span>Buka Toko</span>
                </a>
              {/if}
            {/if}
            <button on:click={() => { logout(); mobileOpen = false; }} class="flex items-center gap-3 text-left px-3 py-2.5 rounded-lg hover:bg-ink-50 text-red-600">
              <Icon name="log-out" size={16} />
              <span>Keluar</span>
            </button>
          {:else}
            <a href="/login" on:click={() => mobileOpen = false} class="btn-primary btn-md mt-2">Masuk</a>
          {/if}
        </nav>
      </div>
    </div>
  {/if}
</header>
