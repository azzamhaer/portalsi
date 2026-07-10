<script lang="ts">
  import { onMount } from 'svelte';
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
  import VendorBadge from '$lib/components/VendorBadge.svelte';
  import VendorWarningPopup from '$lib/components/VendorWarningPopup.svelte';
  import SellerTour from '$lib/components/SellerTour.svelte';
  import { fmtRp, statusPill, ORDER_STATUS_LABEL } from '$lib/utils';
  import { apiEndpoints } from '$lib/api';
  import { goto } from '$app/navigation';
  import { auth } from '$lib/stores.svelte';

  let data = $state<any>(null);
  let loading = $state(true);
  let showTour = $state(false);

  onMount(async () => {
    if (!auth.user) { goto('/login?next=/seller/dashboard'); return; }
    try {
      data = await apiEndpoints.sellerDashboard();
      auth.set({
        ...auth.user,
        vendor_id: data.vendor.id,
        vendor_status: data.vendor.verification_status,
        vendor_username: data.vendor.username,
        vendor_tour_done: data.vendor.tour_completed_at !== null
      });
      if (data?.vendor?.verification_status !== 'APPROVED') {
        goto('/seller/pending');
        return;
      }
      // Tour kalau toko APPROVED tapi tour_completed_at masih null
      if (data?.vendor?.verification_status === 'APPROVED' && !data.vendor.tour_completed_at) {
        // Beri waktu DOM render dulu
        setTimeout(() => { showTour = true; }, 600);
      }
    }
    catch (e: any) { if (e.status === 404) goto('/seller/register'); }
    loading = false;
  });

  function dismissWarning() {
    if (data?.vendor) data.vendor.warning_dismissed_at = new Date().toISOString();
  }
  function finishTour() {
    showTour = false;
    if (data?.vendor) data.vendor.tour_completed_at = new Date().toISOString();
  }
</script>

{#if data?.vendor}
  <VendorWarningPopup vendor={data.vendor} onClose={dismissWarning} />
{/if}
{#if showTour}
  <SellerTour onFinish={finishTour} />
{/if}

<svelte:head><title>Seller Dashboard — MPSI</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">Seller Center</h1>
  <div class="grid lg:grid-cols-[230px_1fr] gap-6">
    <SellerSidebar />
    <div class="space-y-5">
      {#if loading}<div class="card text-center text-ink-500 py-12">Memuat…</div>
      {:else if data}
        <div class="card flex items-center gap-4 flex-wrap">
          <img src={data.vendor.avatar} alt="" class="w-14 h-14 rounded-full object-cover" />
          <div class="flex-1 min-w-[200px]">
            <h2 class="font-display text-xl font-bold tracking-tightest flex items-center gap-2">
              {data.vendor.name}
              {#if data.vendor.badge}<VendorBadge badge={data.vendor.badge} size={16} />{/if}
            </h2>
            <p class="text-sm text-ink-500">{data.vendor.city} · {(data.vendor.followers ?? 0).toLocaleString('id-ID')} pengikut</p>
          </div>
          <a href={data.vendor.username ? `/${data.vendor.username}` : `/vendors/${data.vendor.id}`} class="btn-outline btn-md">Lihat Toko Publik</a>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {#each [
            { l:'Pesanan 24 Jam', v: data.stats.orders_24h },
            { l:'Pendapatan 30 Hari', v: fmtRp(data.stats.revenue_30d ?? 0) },
            { l:'Produk Aktif', v: data.stats.active_products },
            { l:'Rating Toko', v: `${data.stats.rating} ★` }
          ] as s}
            <div class="card">
              <div class="text-xs uppercase tracking-widest text-ink-500">{s.l}</div>
              <div class="font-display text-2xl font-bold tracking-tightest mt-2">{s.v}</div>
            </div>
          {/each}
        </div>

        <div class="card">
          <h3 class="font-semibold mb-4">Pesanan Terbaru</h3>
          {#if data.recent_orders.length === 0}
            <p class="text-sm text-ink-500 text-center py-6">Belum ada pesanan masuk.</p>
          {:else}
            <table class="w-full text-sm">
              <thead class="text-xs text-ink-500 border-b border-ink-100">
                <tr><th class="text-left py-2 font-medium">Order</th><th class="text-left py-2 font-medium">Produk</th><th class="text-left py-2 font-medium">Qty</th><th class="text-left py-2 font-medium">Total</th><th class="text-left py-2 font-medium">Status</th></tr>
              </thead>
              <tbody>
                {#each data.recent_orders as it}
                  <tr class="border-b border-ink-100 last:border-0">
                    <td class="py-2 font-mono text-xs">{it.order?.order_number?.slice(-8) ?? '-'}</td>
                    <td class="py-2">{it.product_name}</td>
                    <td class="py-2">{it.quantity}</td>
                    <td class="py-2">{fmtRp(it.price * it.quantity)}</td>
                    <td class="py-2"><span class="pill {statusPill(it.order?.status ?? '')}">{ORDER_STATUS_LABEL[it.order?.status] ?? it.order?.status}</span></td>
                  </tr>
                {/each}
              </tbody>
            </table>
          {/if}
        </div>
      {/if}
    </div>
  </div>
</div>
