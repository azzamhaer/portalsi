<script lang="ts">
  import { onMount } from 'svelte';
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp } from '$lib/utils';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';

  let products = $state<any[]>([]);
  let loading = $state(true);

  async function load() {
    loading = true;
    try { products = await apiEndpoints.sellerProducts(); } catch {} finally { loading = false; }
  }
  onMount(load);

  async function del(id: number, name: string) {
    if (!confirm(`Hapus produk "${name}"?`)) return;
    try { await apiEndpoints.sellerDeleteProduct(id); toast.success('Produk dihapus'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
</script>

<svelte:head><title>Produk Saya — MPSI Seller</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">Seller Center</h1>
  <div class="grid lg:grid-cols-[230px_1fr] gap-6">
    <SellerSidebar />
    <div class="space-y-5">
      <div class="card flex items-center justify-between flex-wrap gap-3">
        <h3 class="font-semibold">Produk Saya ({products.length})</h3>
        <a href="/seller/products/new" class="btn-primary btn-md"><Icon name="plus" size={14} /> Tambah Produk</a>
      </div>
      {#if loading}<div class="card text-center text-ink-500 py-12">Memuat…</div>
      {:else if products.length === 0}
        <div class="card text-center py-12 text-ink-500">
          <p class="mb-4">Belum ada produk.</p>
          <a href="/seller/products/new" class="btn-primary btn-md"><Icon name="plus" size={14} /> Tambah Produk</a>
        </div>
      {:else}
        <div class="card overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-ink-500 border-b border-ink-100">
              <tr>
                <th class="text-left py-2 font-medium">Foto</th>
                <th class="text-left py-2 font-medium">Nama</th>
                <th class="text-left py-2 font-medium">Kategori</th>
                <th class="text-left py-2 font-medium">Harga</th>
                <th class="text-left py-2 font-medium">Stok</th>
                <th class="text-left py-2 font-medium">Terjual</th>
                <th class="text-left py-2 font-medium">Status</th>
                <th class="text-left py-2 font-medium">Aksi</th>
              </tr>
            </thead>
            <tbody>
              {#each products as p (p.id)}
                <tr class="border-b border-ink-100 last:border-0">
                  <td class="py-2"><img src={p.image} alt="" class="w-12 h-12 rounded-lg object-cover" /></td>
                  <td class="py-2"><a href={`/product/${p.id}`} class="font-medium hover:text-ink-950 line-clamp-2 max-w-xs">{p.name}</a></td>
                  <td class="py-2 text-ink-500">{p.category?.name ?? '-'}</td>
                  <td class="py-2 font-semibold">{fmtRp(p.price)}</td>
                  <td class="py-2">{p.stock}</td>
                  <td class="py-2">{p.sold}</td>
                  <td class="py-2"><span class="pill {p.is_active ? 'pill-green' : 'pill-ink'}">{p.is_active ? 'Aktif' : 'Non-aktif'}</span></td>
                  <td class="py-2">
                    <div class="flex gap-1">
                      <a href={`/seller/products/${p.id}/edit`} class="btn-outline btn-sm"><Icon name="pencil" size={12} /></a>
                      <button on:click={() => del(p.id, p.name)} class="btn-sm btn bg-red-50 text-red-700 hover:bg-red-100"><Icon name="trash-2" size={12} /></button>
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </div>
  </div>
</div>
