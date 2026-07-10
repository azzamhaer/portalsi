<script lang="ts">
  import { goto } from '$app/navigation';
  import { apiEndpoints } from '$lib/api';
  import Icon from './Icon.svelte';

  let {
    placeholder = 'Cari produk, brand, atau toko',
    autofocus = false,
    initialQuery = '',
  } = $props<{ placeholder?: string; autofocus?: boolean; initialQuery?: string }>();

  let q = $state(initialQuery);
  let open = $state(false);
  let loading = $state(false);
  let suggestions = $state<{ products: any[]; vendors: any[]; tags: any[] }>({ products: [], vendors: [], tags: [] });
  let timer: any;

  function fetchSuggest() {
    clearTimeout(timer);
    if (!q.trim()) { open = false; suggestions = { products: [], vendors: [], tags: [] }; return; }
    timer = setTimeout(async () => {
      loading = true;
      try {
        suggestions = await apiEndpoints.searchSuggest(q.trim());
        open = true;
      } catch {
        suggestions = { products: [], vendors: [], tags: [] };
      } finally {
        loading = false;
      }
    }, 220);
  }

  function submit(e?: Event) {
    e?.preventDefault();
    if (!q.trim()) return;
    open = false;
    goto('/search?q=' + encodeURIComponent(q.trim()));
  }

  function pick(href: string) {
    open = false;
    goto(href);
  }
</script>

<div class="relative">
  <form on:submit={submit} class="flex items-center gap-2 rounded-[24px] border border-ink-100 bg-white p-2 shadow-soft">
    <div class="grid h-10 w-10 place-items-center rounded-2xl bg-ink-100 text-ink-500">
      <Icon name="search" size={18} />
    </div>
    <input
      bind:value={q}
      on:input={fetchSuggest}
      on:focus={() => { if (q.trim()) open = true; }}
      {autofocus}
      class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-ink-400"
      placeholder={placeholder}
    />
    <button type="submit" class="btn-primary btn-sm h-10 px-4">Cari</button>
  </form>

  {#if open}
    <div class="absolute inset-x-0 top-full z-40 mt-2 max-h-[420px] overflow-y-auto rounded-2xl border border-ink-100 bg-white p-2 shadow-elevated">
      {#if loading}
        <div class="p-4 text-center text-xs text-ink-500">Mencari...</div>
      {:else}
        {#if q.trim()}
          <button type="button" on:click={submit} class="mb-1 flex w-full items-center gap-2 rounded-xl px-2 py-2 text-left hover:bg-ink-50">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-app-primary/10 text-app-primary">
              <Icon name="search" size={16} />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold">Cari kata kunci "{q.trim()}"</span>
              <span class="block text-xs text-ink-500">Lihat semua hasil yang cocok</span>
            </span>
            <Icon name="arrow-right" size={15} class="text-ink-400" />
          </button>
        {/if}
        {#if suggestions.products.length}
          <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-widest text-ink-400">Produk</div>
          {#each suggestions.products.slice(0, 5) as p}
            <button type="button" on:click={() => pick(`/product/${p.slug || p.id}`)} class="flex w-full items-center gap-2 rounded-xl px-2 py-2 text-left hover:bg-ink-50">
              <img src={p.image} alt="" class="h-10 w-10 rounded-xl object-cover" />
              <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium">{p.name}</div>
                <div class="text-xs text-ink-500">Rp {p.price.toLocaleString('id-ID')}</div>
              </div>
            </button>
          {/each}
        {/if}
        {#if suggestions.vendors.length}
          <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-widest text-ink-400">Toko</div>
          {#each suggestions.vendors.slice(0, 4) as v}
            <button type="button" on:click={() => pick(v.username ? `/${v.username}` : `/vendors/${v.id}`)} class="flex w-full items-center gap-2 rounded-xl px-2 py-2 text-left hover:bg-ink-50">
              <img src={v.avatar} alt="" class="h-10 w-10 rounded-full object-cover" />
              <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium">{v.name}</div>
                <div class="text-xs text-ink-500">{v.city}</div>
              </div>
            </button>
          {/each}
        {/if}
        {#if suggestions.tags.length}
          <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-widest text-ink-400">Tag</div>
          <div class="flex flex-wrap gap-1.5 px-2 pb-2">
            {#each suggestions.tags.slice(0, 6) as t}
              <button type="button" on:click={() => pick(`/products?tag=${t.slug}`)} class="rounded-full bg-ink-100 px-2.5 py-1 text-xs hover:bg-app-primary hover:text-app-pfg">#{t.slug}</button>
            {/each}
          </div>
        {/if}
      {/if}
    </div>
  {/if}
</div>
