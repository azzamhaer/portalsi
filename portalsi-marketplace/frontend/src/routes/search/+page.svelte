<script lang="ts">
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import ProductFilterPanel from '$lib/components/ProductFilterPanel.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import SmartSearch from '$lib/components/SmartSearch.svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import Icon from '$lib/components/Icon.svelte';

  let { data } = $props();
  let filtersOpen = $state(false);

  const sorts = [
    ['popular', 'Terpopuler', 'flame'],
    ['cheap', 'Termurah', 'arrow-down'],
    ['exp', 'Termahal', 'arrow-up'],
    ['rating', 'Rating', 'star'],
  ];

  function setSort(v: string) {
    const u = new URL($page.url);
    u.searchParams.set('sort', v);
    u.searchParams.set('page', '1');
    goto(u.pathname + '?' + u.searchParams.toString());
  }
</script>

<svelte:head><title>"{data.q}" - MPSI</title></svelte:head>

<div class="container-x py-8">
  <div class="mb-6 max-w-3xl">
    <SmartSearch placeholder="Cari kata kunci lain" initialQuery={data.q} />
  </div>

  <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
    <div>
      <div class="section-eyebrow mb-2 flex items-center gap-2"><Icon name="search" size={12} /> Pencarian</div>
      <h1 class="section-title">Hasil untuk "<span class="italic">{data.q}</span>"</h1>
      <p class="mt-1 text-sm text-ink-500">{data.meta?.total ?? data.products.length} produk ditemukan</p>
    </div>
  </div>

  {#if data.products.length}
    <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-ink-100 bg-white p-2 shadow-soft sm:flex-row sm:items-center sm:justify-between">
      <div class="flex gap-1 overflow-x-auto pb-1 sm:pb-0">
        {#each sorts as [value, label, icon]}
          <button
            type="button"
            on:click={() => setSort(value)}
            class="flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition"
            class:bg-app-primary={data.sort === value}
            class:text-app-pfg={data.sort === value}
            class:bg-ink-50={data.sort !== value}
            class:text-ink-600={data.sort !== value}
            class:hover:bg-ink-100={data.sort !== value}
          >
            <Icon name={icon} size={13} /> {label}
          </button>
        {/each}
      </div>
      <button type="button" on:click={() => filtersOpen = !filtersOpen} class="flex items-center justify-center gap-2 rounded-xl border border-ink-100 px-4 py-2 text-sm font-semibold transition hover:bg-ink-50 lg:hidden">
        <Icon name="sliders-horizontal" size={16} />
        Filter produk
        <Icon name={filtersOpen ? 'chevron-up' : 'chevron-down'} size={16} />
      </button>
    </div>
  {/if}

  <div class="grid gap-8 lg:grid-cols-[270px_1fr]">
    <div class="{filtersOpen ? 'block' : 'hidden'} lg:block">
      <ProductFilterPanel tags={data.tags} categories={data.categories} title="Filter pencarian" />
    </div>
    <div>
      <ProductGrid products={data.products} />
      <Pagination current={data.meta?.current_page ?? 1} last={data.meta?.last_page ?? 1} />
    </div>
  </div>
</div>
