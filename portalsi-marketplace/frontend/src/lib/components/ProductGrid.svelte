<script lang="ts">
  import ProductCard from './ProductCard.svelte';
  import Icon from './Icon.svelte';
  let { products = [] } = $props<{ products: any[] }>();
</script>

{#if products.length === 0}
  <div class="col-span-full text-center py-20">
    <Icon name="package-x" size={56} class="mx-auto text-ink-300 mb-4" />
    <h3 class="text-lg font-semibold text-ink-900 mb-1">Tidak ada produk</h3>
    <p class="text-sm text-ink-500">Coba pilih tag atau kata kunci lain.</p>
  </div>
{:else}
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-x-3 gap-y-6 sm:gap-x-5 sm:gap-y-8">
    {#each products as p (p.id)}
      <ProductCard product={p} />
    {/each}
  </div>
{/if}
