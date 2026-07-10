<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import VendorBadge from '$lib/components/VendorBadge.svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  let { data } = $props();
  let search = $state(data.search ?? '');
  let city = $state(data.city ?? '');
  let minRating = $state(data.minRating ?? '');
  let official = $state(data.official === '1');
  const filters = [
    { v:'all',      l:'Semua' },
    { v:'official', l:'Resmi' },
    { v:'rating',   l:'Rating Tertinggi' },
    { v:'sold',     l:'Penjualan Terbanyak' }
  ];

  function applyFilters() {
    const u = new URL($page.url);
    if (search) u.searchParams.set('search', search); else u.searchParams.delete('search');
    if (city) u.searchParams.set('city', city); else u.searchParams.delete('city');
    if (minRating) u.searchParams.set('min_rating', minRating); else u.searchParams.delete('min_rating');
    if (official) u.searchParams.set('official', '1'); else u.searchParams.delete('official');
    goto(u.pathname + '?' + u.searchParams.toString());
  }
</script>

<svelte:head><title>Daftar Toko — MPSI</title></svelte:head>

<div class="container-x py-8">
  <div class="section-eyebrow mb-2">Toko</div>
  <h1 class="section-title mb-2">Penjual tepercaya kami</h1>
  <p class="text-ink-500 mb-8">{data.vendors.length} toko terdaftar</p>

  <div class="mb-8 grid gap-3 rounded-2xl border border-ink-100 bg-white p-4 lg:grid-cols-[1fr_auto]">
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
      <input bind:value={search} class="input input-sm" placeholder="Cari toko" />
      <input bind:value={city} class="input input-sm" placeholder="Kota" />
      <select bind:value={minRating} class="input input-sm">
        <option value="">Semua rating</option>
        <option value="4">4 ke atas</option>
        <option value="3">3 ke atas</option>
      </select>
      <label class="flex items-center gap-2 rounded-xl border border-ink-100 px-3 py-2 text-sm">
        <input type="checkbox" bind:checked={official} />
        Toko resmi
      </label>
    </div>
    <button type="button" on:click={applyFilters} class="btn-primary btn-sm">Terapkan</button>
  </div>

  <div class="flex gap-2 mb-8 flex-wrap">
    {#each filters as f}
      <a href={`/vendors?f=${f.v}`} class="btn-sm" class:btn-primary={f.v===data.filter} class:btn-outline={f.v!==data.filter}>{f.l}</a>
    {/each}
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
    {#each data.vendors as v (v.id)}
      <a href={v.username ? `/${v.username}` : `/vendors/${v.id}`} class="card-hover group">
        <div class="flex items-center gap-4">
          <img src={v.avatar} alt="" class="w-16 h-16 rounded-full object-cover" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5">
              <h3 class="font-semibold truncate">{v.name}</h3>
              {#if v.badge}<VendorBadge badge={v.badge} size={14} />{/if}
              {#if v.is_official}<span class="pill-ink text-[10px]">RESMI</span>{/if}
            </div>
            <div class="text-xs text-ink-500 mt-0.5">{v.city}</div>
            <div class="text-xs text-ink-700 mt-1 flex items-center gap-3">
              <span class="flex items-center gap-1"><Icon name="star" size={11} class="text-amber-400" fill="currentColor" /> {v.rating}</span>
              <span>{v.total_sold.toLocaleString('id-ID')} terjual</span>
            </div>
          </div>
        </div>
      </a>
    {/each}
  </div>
</div>
