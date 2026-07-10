<script lang="ts">
  import { onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import LoginRequired from '$lib/components/LoginRequired.svelte';
  import AdminBlock from '$lib/components/AdminBlock.svelte';
  import { auth } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import ProductGrid from '$lib/components/ProductGrid.svelte';

  let items = $state<any[]>([]);
  let loading = $state(true);

  onMount(async () => {
    if (!auth.user) { loading = false; return; }
    try {
      const list: any[] = await apiEndpoints.getWishlist();
      items = list.map(w => w.product);
    } finally { loading = false; }
  });
</script>

<svelte:head><title>Wishlist — MPSI</title></svelte:head>

{#if !auth.user}
  <LoginRequired
    icon="heart"
    title="Login untuk melihat wishlist"
    description="Simpan produk favorit Anda dan akses dari perangkat manapun setelah masuk."
  />
{:else if auth.user.role === 'ADMIN'}
  <AdminBlock title="Admin tidak punya wishlist" description="Akun admin tidak bisa menyimpan wishlist. Gunakan akun pembeli." />
{:else}
  <div class="container-x py-8">
    <h1 class="section-title mb-8">Wishlist</h1>
    {#if loading}
      <div class="text-center py-20 text-ink-500">Memuat…</div>
    {:else if items.length === 0}
      <div class="text-center py-20">
        <Icon name="heart" size={56} class="mx-auto text-ink-300 mb-4" />
        <h3 class="text-lg font-semibold mb-1">Wishlist masih kosong</h3>
        <p class="text-sm text-ink-500 mb-5">Tambahkan produk favorit Anda dari halaman produk.</p>
        <a href="/products" class="btn-primary btn-md">Mulai Belanja</a>
      </div>
    {:else}
      <ProductGrid products={items} />
    {/if}
  </div>
{/if}
