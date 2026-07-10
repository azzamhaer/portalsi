<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { fmtRp } from '$lib/utils';
  import { toast } from '$lib/stores.svelte';

  let stats = $state<any>(null);
  let loading = $state(true);
  onMount(async () => {
    try { stats = await apiEndpoints.adminStats(); }
    finally { loading = false; }
  });

  const cards = $derived(stats ? [
    { i:'users',          l:'Total Users',        v: stats.users,          h:'/admin/users' },
    { i:'store',          l:'Total Vendor',       v: stats.vendors,        h:'/admin/vendors' },
    { i:'shopping-cart',  l:'Total Pesanan',      v: stats.orders,         h:'/admin/orders' },
    { i:'trending-up',    l:'Pesanan Hari Ini',   v: stats.orders_today,   h:'/admin/orders' },
    { i:'wallet',         l:'Total Revenue',      v: fmtRp(stats.revenue), h:'/admin/orders' },
    { i:'shield-alert',   l:'Vendor Pending',     v: stats.pending_vendors,h:'/admin/vendors' },
    { i:'undo-2',         l:'Return Pending',     v: stats.pending_returns,h:'/admin/returns' }
  ] : []);
</script>

<svelte:head><title>Admin Dashboard</title></svelte:head>

{#if loading}<div class="card text-center text-ink-500 py-12">Memuat…</div>
{:else}
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
    {#each cards as c}
      <a href={c.h} class="card-hover group">
        <div class="w-10 h-10 rounded-xl bg-ink-100 grid place-items-center mb-3 group-hover:bg-ink-950 transition">
          <Icon name={c.i} size={18} class="text-ink-700 group-hover:text-white transition" />
        </div>
        <div class="text-[11px] uppercase tracking-widest text-ink-500">{c.l}</div>
        <div class="font-display text-2xl font-bold tracking-tightest mt-1">{c.v}</div>
      </a>
    {/each}
  </div>

  <div class="card mt-6">
    <h3 class="font-semibold mb-2">Aksi Cepat</h3>
    <div class="grid sm:grid-cols-3 gap-3">
      <a href="/admin/vendors?status=PENDING" class="btn-outline btn-md">Verifikasi vendor pending</a>
      <a href="/admin/settings" class="btn-outline btn-md">Edit branding & Tripay</a>
      <a href="/admin/returns" class="btn-outline btn-md">Tinjau permintaan return</a>
    </div>
  </div>
{/if}
