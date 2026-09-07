<script lang="ts">
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
  import { t } from '$lib/i18n';
  import ProductForm from '$lib/components/ProductForm.svelte';
</script>

<svelte:head><title>{$t('lf.addProductTitle')}</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">{$t('sn.addProduct')}</h1>
  <div class="grid lg:grid-cols-[230px_1fr] gap-6">
    <SellerSidebar />
    <div class="card max-w-3xl">
      <ProductForm mode="create" />
    </div>
  </div>
</div>
