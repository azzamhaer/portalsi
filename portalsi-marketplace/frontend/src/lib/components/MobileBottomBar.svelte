<script lang="ts">
  import { page } from '$app/stores';
  import { auth } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { onMount } from 'svelte';
  import Icon from './Icon.svelte';

  let unreadNotifications = $state(0);
  let unreadChats = $state(0);
  let timer: any;

  const items = $derived([
    { label: 'Beranda', href: '/', match: '/', icon: 'home', badge: 0 },
    { label: 'Produk', href: '/products', match: '/products', icon: 'package-search', badge: 0 },
    { label: 'Notifikasi', href: auth.user ? '/notifications' : '/login?next=/notifications', match: '/notifications', icon: 'bell', badge: unreadNotifications },
    { label: 'Profil', href: auth.user ? '/profile' : '/login?next=/profile', match: '/profile', icon: 'user-round', badge: unreadChats },
  ]);

  async function refreshBadges() {
    if (!auth.user) { unreadNotifications = 0; unreadChats = 0; return; }
    try {
      const [n, c]: any[] = await Promise.all([
        apiEndpoints.notificationsUnreadCount().catch(() => ({ count: 0 })),
        apiEndpoints.chatsUnreadCount().catch(() => ({ count: 0 })),
      ]);
      unreadNotifications = n.count ?? 0;
      unreadChats = c.count ?? 0;
    } catch {}
  }

  onMount(() => {
    refreshBadges();
    timer = setInterval(refreshBadges, 30000);
    return () => clearInterval(timer);
  });

  function active(match: string) {
    if (match === '/') return $page.url.pathname === '/';
    return $page.url.pathname === match || $page.url.pathname.startsWith(match + '/');
  }
</script>

<nav class="fixed inset-x-0 bottom-0 z-50 px-3 pb-3 md:hidden pointer-events-none" aria-label="Navigasi utama mobile">
  <div class="pointer-events-auto mx-auto max-w-md rounded-[28px] border border-white/60 bg-white/72 p-1.5 shadow-[0_18px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl supports-[backdrop-filter]:bg-white/60">
    <div class="grid grid-cols-4 gap-1">
      {#each items as item}
        {@const isActive = active(item.match)}
        <a
          href={item.href}
          class="relative flex h-14 min-w-0 flex-col items-center justify-center gap-0.5 rounded-[22px] text-[11px] font-medium transition-all"
          class:bg-app-primary={isActive}
          class:text-app-pfg={isActive}
          class:text-ink-500={!isActive}
          class:hover:bg-ink-100={!isActive}
          aria-current={isActive ? 'page' : undefined}
        >
          <Icon name={item.icon} size={19} />
          {#if item.badge > 0}
            <span class="absolute right-3 top-2 grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">{item.badge > 99 ? '99+' : item.badge}</span>
          {/if}
          <span class="max-w-full truncate px-1">{item.label}</span>
        </a>
      {/each}
    </div>
  </div>
</nav>
