<script lang="ts">
  import Icon from './Icon.svelte';
  import { fmtRp, calcDiscount } from '$lib/utils';
  import { wishlist } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { auth, toast, loginHref } from '$lib/stores.svelte';
  import { goto } from '$app/navigation';
  let { product } = $props<{ product: any }>();
  const disc = $derived(calcDiscount(product.price, product.original_price));
  const inWishlist = $derived(wishlist.has(product.id));
  const isOutOfStock = $derived((product.stock ?? 0) <= 0);

  async function toggleWish(e: Event) {
    e.preventDefault(); e.stopPropagation();
    if (!auth.user) { goto(loginHref(`/product/${product.slug || product.id}`, 'wishlist')); return; }
    const wasIn = inWishlist;
    wishlist.toggle(product.id);
    try { await apiEndpoints.toggleWishlist(product.id); }
    catch (err: any) { wishlist.toggle(product.id); toast.error(err.message); }
    toast.success(wasIn ? 'Dihapus dari wishlist' : 'Ditambahkan ke wishlist');
  }
</script>

<a href={`/product/${product.slug || product.id}`} class="group block">
  <div class="relative aspect-square overflow-hidden rounded-2xl bg-ink-50">
    <img src={product.image} alt={product.name} loading="lazy"
         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
    {#if disc > 0}
      <span class="absolute top-2 left-2 sm:top-3 sm:left-3 bg-app-primary text-app-pfg text-[10px] font-semibold px-2 py-1 rounded-full">−{disc}%</span>
    {/if}
    {#if isOutOfStock}
      <span class="absolute bottom-2 left-2 sm:bottom-3 sm:left-3 rounded-full bg-red-600 px-2.5 py-1 text-[10px] font-semibold text-white">Stok habis</span>
    {/if}
    <button on:click={toggleWish}
            class="absolute top-2 right-2 sm:top-3 sm:right-3 w-8 h-8 grid place-items-center rounded-full backdrop-blur-md hover:scale-110 transition shadow-soft
                   {inWishlist ? 'bg-red-500 text-white' : 'bg-white/90 text-ink-700'}"
            aria-label="Wishlist">
      <Icon name="heart" size={16} fill={inWishlist ? 'currentColor' : 'none'} />
    </button>
  </div>
  <div class="pt-3 space-y-1">
    <h3 class="text-sm font-medium text-ink-900 line-clamp-2 leading-snug min-h-[2.5rem] group-hover:text-ink-950">{product.name}</h3>
    {#if product.tags && product.tags.length}
      <div class="flex flex-wrap gap-1">
        {#each product.tags.slice(0,2) as t}
          <span class="text-[10px] px-1.5 py-0.5 rounded bg-ink-100 text-ink-600">#{t}</span>
        {/each}
      </div>
    {/if}
    <div class="flex items-baseline gap-2">
      <span class="text-base font-semibold text-ink-950">{fmtRp(product.price)}</span>
      {#if product.original_price && product.original_price > product.price}
        <span class="text-xs text-ink-400 line-through">{fmtRp(product.original_price)}</span>
      {/if}
    </div>
    <div class="flex items-center justify-between text-xs text-ink-500">
      {#if (product.reviews_count ?? 0) > 0}
        <span class="flex items-center gap-1">
          <Icon name="star" size={12} class="text-amber-400" fill="currentColor" />
          {Number(product.rating ?? 0).toFixed(1)}
          <span class="text-ink-400">({product.reviews_count})</span>
        </span>
      {:else}
        <span class="text-ink-400">Belum ada ulasan</span>
      {/if}
      <span>{(product.sold ?? 0).toLocaleString('id-ID')} terjual</span>
    </div>
  </div>
</a>
