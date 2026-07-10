<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  let { data } = $props();

  function setSort(v: string) {
    const u = new URL($page.url);
    u.searchParams.set('sort', v);
    goto(u.pathname + '?' + u.searchParams.toString());
  }
</script>

<svelte:head><title>{data.category?.name ?? 'Kategori'} — MPSI</title></svelte:head>

<div class="container-x py-8">
  <nav class="flex items-center gap-1 text-xs text-ink-500 mb-5">
    <a href="/" class="hover:text-ink-900">Beranda</a><Icon name="chevron-right" size={12} />
    <span>{data.category?.name}</span>
  </nav>

  <div class="grid lg:grid-cols-[260px_1fr] gap-10">
    <aside>
      <h3 class="text-xs font-semibold uppercase tracking-widest text-ink-500 mb-3">Kategori</h3>
      <ul class="space-y-1 text-sm">
        {#each data.allCats as c (c.id)}
          <li>
            <a href={`/category/${c.slug}`} class="block px-3 py-2 rounded-lg transition" class:bg-app-primary={c.id===data.category?.id} class:text-app-pfg={c.id===data.category?.id} class:hover:bg-ink-50={c.id!==data.category?.id}>
              {c.name}
            </a>
            {#if c.children?.length}
              <div class="mt-1 space-y-1 pl-3">
                {#each c.children as child}
                  <a href={`/category/${child.slug}`} class="block rounded-lg px-3 py-1.5 text-xs transition" class:bg-app-primary={child.id===data.category?.id} class:text-app-pfg={child.id===data.category?.id} class:hover:bg-ink-50={child.id!==data.category?.id}>
                    #{child.tag_slug || child.slug}
                  </a>
                {/each}
              </div>
            {/if}
          </li>
        {/each}
        <li><a href="/products" class="block px-3 py-2 rounded-lg hover:bg-ink-50">Semua produk</a></li>
      </ul>
    </aside>

    <div>
      <div class="flex items-end justify-between mb-8 gap-4 flex-wrap">
        <div>
          <div class="section-eyebrow mb-2">Kategori</div>
          <h1 class="section-title">{data.category?.name}</h1>
          <p class="text-sm text-ink-500 mt-1">{data.products.length} produk</p>
        </div>
        <select on:change={(e: any) => setSort(e.target.value)} value={data.sort} class="input-sm input w-full sm:w-56">
          <option value="popular">Terpopuler</option>
          <option value="newest">Terbaru</option>
          <option value="cheap">Termurah</option>
          <option value="exp">Termahal</option>
          <option value="rating">Rating Tertinggi</option>
        </select>
      </div>
      <ProductGrid products={data.products} />
    </div>
  </div>
</div>
