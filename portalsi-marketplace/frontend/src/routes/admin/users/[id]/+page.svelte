<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';
  import { page } from '$app/stores';
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp, statusPill, ORDER_STATUS_LABEL } from '$lib/utils';

  let data = $state<any>(null);
  let loading = $state(true);
  let tab = $state<'orders' | 'products' | 'incoming'>('orders');

  onMount(async () => {
    const id = $page.params.id ?? '0';
    try {
      data = await apiEndpoints.adminUser(+id);
      if (data.user.vendor && data.orders.length === 0) tab = 'products';
    } catch (e: any) { toast.error(e.message); } finally { loading = false; }
  });
</script>

<a href="/admin/users" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-950 mb-4">
  <Icon name="arrow-left" size={14} /> Kembali ke daftar Users
</a>

{#if loading}
  <div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else if data}
  <div class="card mb-5">
    <div class="flex items-start gap-4 flex-wrap">
      <div class="w-14 h-14 rounded-full bg-app-primary text-app-pfg grid place-items-center font-bold text-xl shrink-0">
        {data.user.name?.[0]?.toUpperCase() ?? '?'}
      </div>
      <div class="flex-1 min-w-0">
        <h2 class="font-display text-xl font-bold tracking-tightest">{data.user.name}</h2>
        <div class="text-sm text-ink-500">{data.user.email}</div>
        <div class="text-xs text-ink-500 mt-1">
          Daftar {new Date(data.user.created_at).toLocaleDateString('id-ID', { dateStyle: 'medium' })}
          {#if data.user.phone}· {data.user.phone}{/if}
          {#if data.user.email_verified_at}· <span class="text-emerald-600">Email terverifikasi</span>{:else}· <span class="text-amber-600">Email belum verif</span>{/if}
        </div>
      </div>
      <span class="pill-{data.user.role === 'ADMIN' ? 'red' : data.user.role === 'SELLER' ? 'blue' : 'ink'}">{data.user.role}</span>
    </div>

    {#if data.user.vendor}
      <div class="mt-4 pt-4 border-t border-ink-100 flex items-center gap-3 flex-wrap">
        <img src={data.user.vendor.avatar} alt="" class="w-10 h-10 rounded-full object-cover" />
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-sm">{data.user.vendor.name}</div>
          <div class="text-xs text-ink-500">@{data.user.vendor.username} · {data.user.vendor.city}</div>
        </div>
        <span class="pill-{data.user.vendor.verification_status === 'APPROVED' ? 'green' : data.user.vendor.verification_status === 'PENDING' ? 'amber' : 'red'}">{data.user.vendor.verification_status}</span>
        <a href={data.user.vendor.username ? `/${data.user.vendor.username}` : `/vendors/${data.user.vendor.id}`} class="btn-outline btn-sm">Lihat Toko</a>
      </div>
    {/if}
  </div>

  <!-- Tabs -->
  <div class="card !p-2 mb-5 flex gap-1">
    <button on:click={() => tab = 'orders'} class="flex-1 px-3 py-2 rounded-lg text-sm transition" class:bg-app-primary={tab === 'orders'} class:text-app-pfg={tab === 'orders'} class:hover:bg-ink-50={tab !== 'orders'}>
      Pesanan Pembelian ({data.orders.length})
    </button>
    {#if data.user.vendor}
      <button on:click={() => tab = 'products'} class="flex-1 px-3 py-2 rounded-lg text-sm transition" class:bg-app-primary={tab === 'products'} class:text-app-pfg={tab === 'products'} class:hover:bg-ink-50={tab !== 'products'}>
        Produk Toko ({data.vendor_products.length})
      </button>
      <button on:click={() => tab = 'incoming'} class="flex-1 px-3 py-2 rounded-lg text-sm transition" class:bg-app-primary={tab === 'incoming'} class:text-app-pfg={tab === 'incoming'} class:hover:bg-ink-50={tab !== 'incoming'}>
        Pesanan Masuk ({data.vendor_orders.length})
      </button>
    {/if}
  </div>

  {#if tab === 'orders'}
    {#if data.orders.length === 0}
      <div class="card text-center text-ink-500 py-10">Belum ada pesanan pembelian.</div>
    {:else}
      <div class="card overflow-x-auto">
        <table class="w-full text-sm min-w-[600px]">
          <thead class="text-xs text-ink-500 border-b border-ink-100">
            <tr><th class="text-left py-2">Order</th><th class="text-left py-2">Item</th><th class="text-left py-2">Total</th><th class="text-left py-2">Status</th><th class="text-left py-2">Tanggal</th></tr>
          </thead>
          <tbody>
            {#each data.orders as o}
              <tr class="border-b border-ink-100 last:border-0">
                <td class="py-2 font-mono text-xs">{o.order_number}</td>
                <td class="py-2 text-xs">{o.items?.length ?? 0} item</td>
                <td class="py-2 font-semibold">{fmtRp(o.total)}</td>
                <td class="py-2"><span class="pill {statusPill(o.status)}">{ORDER_STATUS_LABEL[o.status] ?? o.status}</span></td>
                <td class="py-2 text-xs text-ink-500">{new Date(o.created_at).toLocaleDateString('id-ID')}</td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  {:else if tab === 'products' && data.user.vendor}
    {#if data.vendor_products.length === 0}
      <div class="card text-center text-ink-500 py-10">Toko belum punya produk.</div>
    {:else}
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {#each data.vendor_products as p}
          <a href={`/product/${p.slug || p.id}`} class="card-hover flex gap-3">
            <img src={p.image} alt="" class="w-16 h-16 rounded-xl object-cover shrink-0" />
            <div class="flex-1 min-w-0">
              <div class="font-medium text-sm line-clamp-2">{p.name}</div>
              <div class="text-sm font-semibold mt-1">{fmtRp(p.price)}</div>
              <div class="text-xs text-ink-500 mt-1">{p.sold} terjual · {p.reviews_count} ulasan {#if !p.is_active}· <span class="text-red-600">Nonaktif</span>{/if}</div>
            </div>
          </a>
        {/each}
      </div>
    {/if}
  {:else if tab === 'incoming' && data.user.vendor}
    {#if data.vendor_orders.length === 0}
      <div class="card text-center text-ink-500 py-10">Belum ada pesanan masuk.</div>
    {:else}
      <div class="card overflow-x-auto">
        <table class="w-full text-sm min-w-[600px]">
          <thead class="text-xs text-ink-500 border-b border-ink-100">
            <tr><th class="text-left py-2">Order</th><th class="text-left py-2">Produk</th><th class="text-left py-2">Qty</th><th class="text-left py-2">Subtotal</th><th class="text-left py-2">Status</th></tr>
          </thead>
          <tbody>
            {#each data.vendor_orders as it}
              <tr class="border-b border-ink-100 last:border-0">
                <td class="py-2 font-mono text-xs">{it.order?.order_number ?? '-'}</td>
                <td class="py-2 text-xs">{it.product_name}{#if it.variant_selection}<div class="text-[10px] text-ink-500">{it.variant_selection}</div>{/if}</td>
                <td class="py-2 text-xs">{it.quantity}</td>
                <td class="py-2 font-semibold">{fmtRp(it.price * it.quantity)}</td>
                <td class="py-2"><span class="pill {statusPill(it.order?.status ?? '')}">{ORDER_STATUS_LABEL[it.order?.status] ?? it.order?.status}</span></td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  {/if}
{/if}
