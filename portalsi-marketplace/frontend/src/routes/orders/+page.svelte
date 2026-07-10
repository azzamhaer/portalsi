<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp, statusPill, ORDER_STATUS_LABEL } from '$lib/utils';
  let { data } = $props();
  const tabs = [
    { v:'',                  l:'Semua' },
    { v:'PENDING_PAYMENT',   l:'Belum Bayar' },
    { v:'PROCESSING',        l:'Diproses' },
    { v:'IN_TRANSIT',        l:'Dalam Perjalanan' },
    { v:'ARRIVED',           l:'Telah Sampai' },
    { v:'DONE',              l:'Selesai' },
    { v:'RETURN_REQUESTED',  l:'Pengembalian' },
    { v:'CANCELLED',         l:'Dibatalkan' }
  ];
</script>

<svelte:head><title>Pesanan Saya — MPSI</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">Pesanan Saya</h1>

  <div class="flex gap-1 overflow-x-auto no-scrollbar border-b border-ink-100 mb-6">
    {#each tabs as t}
      <a href={t.v ? `/orders?st=${t.v}` : '/orders'} class="px-4 py-3 text-sm whitespace-nowrap border-b-2 -mb-px transition"
         class:border-ink-950={(t.v===data.status)} class:font-semibold={(t.v===data.status)} class:border-transparent={(t.v!==data.status)} class:text-ink-500={(t.v!==data.status)}>
        {t.l}
      </a>
    {/each}
  </div>

  {#if data.orders.length === 0}
    <div class="text-center py-20">
      <Icon name="package" size={56} class="mx-auto text-ink-300 mb-4" />
      <h3 class="text-lg font-semibold mb-1">Belum ada pesanan</h3>
      <p class="text-sm text-ink-500 mb-5">Yuk mulai belanja sekarang.</p>
      <a href="/products" class="btn-primary btn-md">Mulai Belanja</a>
    </div>
  {:else}
    <div class="space-y-4">
      {#each data.orders as o (o.id)}
        <a href={`/orders/${o.id}`} class="card-hover block">
          <div class="flex items-center justify-between mb-3 pb-3 border-b border-ink-100">
            <div>
              <div class="font-mono text-sm font-semibold">{o.order_number}</div>
              <div class="text-xs text-ink-500">{new Date(o.created_at).toLocaleString('id-ID')}</div>
            </div>
            <span class="pill {statusPill(o.status)}">{ORDER_STATUS_LABEL[o.status] ?? o.status}</span>
          </div>
          {#each o.items.slice(0,2) as it (it.id)}
            <div class="flex items-center gap-3 py-2">
              <img src={it.product_image} alt="" class="w-12 h-12 rounded-lg object-cover" />
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium line-clamp-1">{it.product_name}</div>
                <div class="text-xs text-ink-500">{it.quantity} × {fmtRp(it.price)}</div>
              </div>
            </div>
          {/each}
          {#if o.items.length > 2}
            <div class="text-xs text-ink-500 my-1">+{o.items.length - 2} produk lainnya</div>
          {/if}
          <div class="flex items-center justify-between pt-3 border-t border-ink-100 mt-3">
            <small class="text-ink-500">{o.items.length} produk · {o.payment?.method_name ?? '-'}</small>
            <div class="flex items-center gap-3">
              <span class="font-bold">{fmtRp(o.total)}</span>
              <span class="text-xs text-ink-500 flex items-center gap-1">Detail <Icon name="arrow-right" size={12} /></span>
            </div>
          </div>
        </a>
      {/each}
    </div>
  {/if}
</div>
