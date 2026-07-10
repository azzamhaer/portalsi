<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import Icon from './Icon.svelte';

  let {
    tags = [],
    categories = [],
    title = 'Filter produk',
  } = $props<{ tags?: any[]; categories?: any[]; title?: string }>();

  let search = $state($page.url.searchParams.get('search') || $page.url.searchParams.get('q') || '');
  let tag = $state($page.url.searchParams.get('tag') || '');
  let category = $state($page.url.searchParams.get('category') || '');
  let minPrice = $state($page.url.searchParams.get('min_price') || '');
  let maxPrice = $state($page.url.searchParams.get('max_price') || '');
  let minRating = $state($page.url.searchParams.get('min_rating') || '');
  let city = $state($page.url.searchParams.get('city') || '');
  let stock = $state($page.url.searchParams.get('stock') || '');
  let official = $state($page.url.searchParams.get('official') || '');
  let flash = $state($page.url.searchParams.get('flash') || '');

  $effect(() => {
    search = $page.url.searchParams.get('search') || $page.url.searchParams.get('q') || '';
    tag = $page.url.searchParams.get('tag') || '';
    category = $page.url.searchParams.get('category') || '';
    minPrice = $page.url.searchParams.get('min_price') || '';
    maxPrice = $page.url.searchParams.get('max_price') || '';
    minRating = $page.url.searchParams.get('min_rating') || '';
    city = $page.url.searchParams.get('city') || '';
    stock = $page.url.searchParams.get('stock') || '';
    official = $page.url.searchParams.get('official') || '';
    flash = $page.url.searchParams.get('flash') || '';
  });

  function apply() {
    const u = new URL($page.url);
    const values: Record<string, string> = {
      search,
      tag,
      category,
      min_price: minPrice,
      max_price: maxPrice,
      min_rating: minRating,
      city,
      stock,
      official,
      flash,
    };
    for (const [key, value] of Object.entries(values)) {
      if (value) u.searchParams.set(key, value);
      else u.searchParams.delete(key);
    }
    u.searchParams.set('page', '1');
    u.searchParams.delete('q');
    goto(u.pathname + '?' + u.searchParams.toString());
  }

  function reset() {
    goto($page.url.pathname);
  }
</script>

<aside class="rounded-2xl border border-ink-100 bg-white p-4">
  <div class="mb-4 flex items-center justify-between gap-3">
    <h2 class="text-sm font-semibold">{title}</h2>
    <Icon name="sliders-horizontal" size={16} class="text-ink-400" />
  </div>

  <div class="space-y-3">
    <div>
      <label class="label">Kata kunci</label>
      <input bind:value={search} class="input input-sm" placeholder="Nama produk, brand, deskripsi" />
    </div>
    <div>
      <label class="label">Kategori</label>
      <select bind:value={category} class="input input-sm">
        <option value="">Semua kategori</option>
        {#each categories as c}
          <option value={c.slug}>{c.name}</option>
          {#each c.children ?? [] as child}
            <option value={child.slug}>- {child.name}</option>
          {/each}
        {/each}
      </select>
    </div>
    <div>
      <label class="label">Tag</label>
      <select bind:value={tag} class="input input-sm">
        <option value="">Semua tag</option>
        {#each tags.slice(0, 80) as t}
          <option value={t.slug}>#{t.slug}</option>
        {/each}
      </select>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <div><label class="label">Harga min</label><input type="number" min="0" bind:value={minPrice} class="input input-sm" /></div>
      <div><label class="label">Harga max</label><input type="number" min="0" bind:value={maxPrice} class="input input-sm" /></div>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="label">Rating</label>
        <select bind:value={minRating} class="input input-sm">
          <option value="">Semua</option>
          <option value="4">4 ke atas</option>
          <option value="3">3 ke atas</option>
          <option value="2">2 ke atas</option>
        </select>
      </div>
      <div>
        <label class="label">Stok</label>
        <select bind:value={stock} class="input input-sm">
          <option value="">Semua</option>
          <option value="available">Tersedia</option>
          <option value="empty">Kosong</option>
        </select>
      </div>
    </div>
    <div>
      <label class="label">Kota toko</label>
      <input bind:value={city} class="input input-sm" placeholder="Jakarta, Bandung, ..." />
    </div>
    <div class="grid grid-cols-2 gap-2">
      <label class="flex items-center gap-2 rounded-xl border border-ink-100 px-3 py-2 text-sm">
        <input type="checkbox" checked={official === '1'} on:change={(e: any) => official = e.target.checked ? '1' : ''} />
        Toko resmi
      </label>
      <label class="flex items-center gap-2 rounded-xl border border-ink-100 px-3 py-2 text-sm">
        <input type="checkbox" checked={flash === '1'} on:change={(e: any) => flash = e.target.checked ? '1' : ''} />
        Promo
      </label>
    </div>
  </div>

  <div class="mt-4 grid grid-cols-2 gap-2">
    <button type="button" on:click={reset} class="btn-outline btn-sm">Reset</button>
    <button type="button" on:click={apply} class="btn-primary btn-sm">Terapkan</button>
  </div>
</aside>
