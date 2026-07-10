<script lang="ts">
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
  import ProductForm from '$lib/components/ProductForm.svelte';
  let { data } = $props();
</script>

<svelte:head><title>Edit Produk — MPSI Seller</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">Edit Produk</h1>
  <div class="grid lg:grid-cols-[230px_1fr] gap-6">
    <SellerSidebar />
    <div class="card max-w-3xl">
      <ProductForm mode="edit" product={data.product} />
    </div>
  </div>
</div>
