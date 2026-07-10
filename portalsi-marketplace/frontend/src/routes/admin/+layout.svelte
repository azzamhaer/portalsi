<script lang="ts">
  import AdminSidebar from '$lib/components/AdminSidebar.svelte';
  import { auth } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { goto } from '$app/navigation';
  import { onMount, onDestroy } from 'svelte';
  import { page } from '$app/stores';

  let { children } = $props();

  const access = $derived(!!auth.user && auth.user.role === 'ADMIN');
  let mounted = $state(false);
  let pendingVendors = $state(0);
  let pollTimer: any;

  async function refreshPending() {
    try {
      const stats: any = await apiEndpoints.adminStats();
      pendingVendors = stats.pending_vendors ?? 0;
    } catch {}
  }

  onMount(async () => {
    mounted = true;
    if (!auth.user) { goto('/login?next=' + ($page.url.pathname || '/admin')); return; }
    if (auth.user.role !== 'ADMIN') goto('/');
    await refreshPending();
    pollTimer = setInterval(refreshPending, 10000);
    window.addEventListener('admin:pending-vendors-changed', refreshPending);
  });

  onDestroy(() => {
    clearInterval(pollTimer);
    if (typeof window !== 'undefined') window.removeEventListener('admin:pending-vendors-changed', refreshPending);
  });
</script>

{#if access}
  <div class="container-x py-6 sm:py-8">
    <h1 class="section-title mb-6 sm:mb-8">Admin Center</h1>
    {#if pendingVendors > 0}
      <a href="/admin/vendors?status=PENDING" class="mb-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 hover:bg-amber-100 transition">
        <span class="w-9 h-9 rounded-full bg-amber-200/70 grid place-items-center shrink-0">
          <span class="text-sm font-bold">{pendingVendors > 99 ? '99+' : pendingVendors}</span>
        </span>
        <span class="flex-1 min-w-0">
          <span class="block font-semibold text-sm">Ada {pendingVendors} vendor menunggu approval</span>
          <span class="block text-xs text-amber-800/80 mt-0.5">Klik untuk membuka daftar verifikasi KTP vendor.</span>
        </span>
      </a>
    {/if}
    <div class="grid lg:grid-cols-[230px_1fr] gap-6">
      <AdminSidebar />
      <div>{@render children()}</div>
    </div>
  </div>
{:else}
  <div class="container-x py-16">
    <div class="card text-center text-ink-500">
      {mounted ? 'Mengalihkan…' : 'Memeriksa akses…'}
    </div>
  </div>
{/if}
