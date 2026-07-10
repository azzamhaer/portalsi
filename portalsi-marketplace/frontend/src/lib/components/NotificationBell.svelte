<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import Icon from './Icon.svelte';
  import { auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { goto } from '$app/navigation';

  let open = $state(false);
  let items = $state<any[]>([]);
  let unread = $state(0);
  let loading = $state(false);
  let boxRef: HTMLDivElement | null = $state(null);
  let pollTimer: any;

  async function refresh() {
    if (!auth.user) { unread = 0; return; }
    try {
      const r: any = await apiEndpoints.notificationsUnreadCount();
      unread = r.count ?? 0;
    } catch {}
  }
  async function loadList() {
    loading = true;
    try {
      const r: any = await apiEndpoints.notifications();
      items = r.data ?? [];
    } finally { loading = false; }
  }

  async function toggle() {
    open = !open;
    if (open) {
      await loadList();
      // Mark all read
      try { await apiEndpoints.notificationsReadAll(); unread = 0; } catch {}
    }
  }

  async function pick(n: any) {
    open = false;
    try { await apiEndpoints.notificationMarkRead(n.id); } catch {}
    goto(`/notifications/${n.id}`);
  }

  onMount(() => {
    refresh();
    pollTimer = setInterval(refresh, 30000); // poll every 30s
    function onDoc(e: MouseEvent) {
      if (open && boxRef && !boxRef.contains(e.target as Node)) open = false;
    }
    document.addEventListener('click', onDoc);
    return () => {
      clearInterval(pollTimer);
      document.removeEventListener('click', onDoc);
    };
  });
  onDestroy(() => clearInterval(pollTimer));

  // Auto refresh saat auth berubah
  let lastUid: number | null = $state(null);
  $effect(() => {
    if (auth.user?.id !== lastUid) {
      lastUid = auth.user?.id ?? null;
      refresh();
    }
  });

  function sevDot(sev: string) {
    return sev === 'SUCCESS' ? 'bg-emerald-500' : sev === 'WARNING' ? 'bg-amber-500' : sev === 'DANGER' ? 'bg-red-500' : 'bg-sky-500';
  }
  function sevIcon(sev: string) {
    return sev === 'SUCCESS' ? 'check-circle' : sev === 'WARNING' ? 'alert-triangle' : sev === 'DANGER' ? 'alert-octagon' : 'info';
  }
</script>

{#if auth.user}
  <div class="relative" bind:this={boxRef}>
    <button type="button" on:click|stopPropagation={toggle} class="w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100 transition-colors relative" aria-label="Notifikasi">
      <Icon name="bell" size={18} class="text-ink-700" />
      {#if unread > 0}
        <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] grid place-items-center px-1.5">{unread > 99 ? '99+' : unread}</span>
      {/if}
    </button>
    {#if open}
      <div class="absolute right-0 top-full mt-1 w-80 sm:w-96 bg-white rounded-2xl shadow-elevated border border-ink-100 animate-fadeIn z-50 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100">
          <h3 class="font-semibold text-sm">Notifikasi</h3>
          <a href="/notifications" on:click={() => open = false} class="text-xs text-ink-500 hover:text-ink-950">Lihat semua</a>
        </div>
        <div class="max-h-[400px] overflow-y-auto">
          {#if loading}
            <div class="p-6 text-center text-ink-500 text-xs">Memuat…</div>
          {:else if items.length === 0}
            <div class="p-8 text-center">
              <Icon name="bell-off" size={32} class="mx-auto text-ink-300 mb-2" />
              <p class="text-xs text-ink-500">Belum ada notifikasi</p>
            </div>
          {:else}
            {#each items.slice(0, 8) as n}
              <button type="button" on:click={() => pick(n)} class="w-full text-left px-4 py-3 hover:bg-ink-50 border-b border-ink-100 last:border-0 flex gap-3">
                <div class="w-8 h-8 rounded-full {sevDot(n.severity)} grid place-items-center text-white shrink-0">
                  <Icon name={sevIcon(n.severity)} size={14} />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-sm line-clamp-1">{n.title}</div>
                  <div class="text-xs text-ink-500 line-clamp-2">{n.message}</div>
                  <div class="text-[10px] text-ink-400 mt-1">{new Date(n.created_at).toLocaleString('id-ID', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' })}</div>
                </div>
              </button>
            {/each}
          {/if}
        </div>
      </div>
    {/if}
  </div>
{/if}
