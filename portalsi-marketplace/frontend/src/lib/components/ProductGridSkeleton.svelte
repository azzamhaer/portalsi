<script lang="ts">
  import ProductCardSkeleton from './ProductCardSkeleton.svelte';
  let { count = 12 } = $props<{ count?: number }>();
</script>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
  {#each Array(count) as _}
    <ProductCardSkeleton />
  {/each}
</div>
