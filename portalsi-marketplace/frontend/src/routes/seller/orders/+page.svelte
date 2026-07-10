<script lang="ts">
  import { onMount } from 'svelte';
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp, statusPill, ORDER_STATUS_LABEL } from '$lib/utils';
  import { apiEndpoints } from '$lib/api';
  import { toast, confirmDialog } from '$lib/stores.svelte';

  let items = $state<any[]>([]);
  let loading = $state(true);
  let search = $state('');
  let searchTimer: any;

  async function load() {
    loading = true;
    try {
      const params = new URLSearchParams();
      if (search.trim()) params.set('search', search.trim());
      const r: any = await apiEndpoints.sellerOrders(params.toString());
      items = r.data ?? [];
    } catch (e: any) {
      toast.error(e.message || 'Gagal memuat pesanan');
    } finally {
      loading = false;
    }
  }

  onMount(load);

  function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 300);
  }

  function addressText(address: any) {
    if (!address) return '-';
    return [
      address.full_address,
      address.village,
      address.district,
      address.city,
      address.province,
      address.postal_code,
    ].filter(Boolean).join(', ');
  }

  function productHref(it: any) {
    return `/product/${it.product?.slug || it.product_id}`;
  }

  function statusNote(status: string) {
    if (status === 'PAID') return 'Pembayaran sudah diterima. Klik Proses untuk menyiapkan pesanan.';
    if (status === 'PROCESSING') return 'Pembayaran sudah diterima. Pesanan siap dikirim.';
    if (status === 'IN_TRANSIT') return 'Pesanan dalam perjalanan. Tandai telah sampai jika barang sudah diterima di tujuan.';
    if (status === 'ARRIVED') return 'Pesanan telah sampai. Jika buyer tidak konfirmasi diterima atau mengajukan pengembalian, saldo otomatis masuk setelah 7 hari.';
    if (status === 'DONE') return 'Pesanan selesai. Dana pesanan ini sudah masuk saldo yang bisa ditarik.';
    if (status === 'RETURN_REQUESTED') return 'Buyer mengajukan pengembalian. Dana ditahan sampai admin meninjau.';
    if (status === 'PENDING_PAYMENT') return 'Menunggu pembayaran buyer. Jangan kirim barang dulu.';
    return ORDER_STATUS_LABEL[status] ?? status;
  }

  async function ship(id: number) {
    const ok = await confirmDialog.ask({
      title: 'Kirim pesanan?',
      message: 'Status akan berubah dari Diproses ke Dikirim. Setelah itu pesanan hanya bisa selesai saat buyer konfirmasi diterima.',
      confirmText: 'Kirim',
    });
    if (!ok) return;
    try {
      const r: any = await apiEndpoints.sellerShipOrder(id);
      toast.success('Pesanan dikirim. Resi: ' + r.tracking_no);
      load();
    } catch (e: any) {
      toast.error(e.message || 'Gagal mengubah status pesanan');
    }
  }

  async function processOrder(id: number) {
    const ok = await confirmDialog.ask({
      title: 'Proses pesanan?',
      message: 'Status pesanan akan berubah menjadi Diproses. Setelah itu pesanan bisa dikirim.',
      confirmText: 'Proses',
    });
    if (!ok) return;
    try {
      await apiEndpoints.sellerProcessOrder(id);
      toast.success('Pesanan siap diproses');
      load();
    } catch (e: any) {
      toast.error(e.message || 'Gagal memproses pesanan');
    }
  }

  async function arrive(id: number) {
    const ok = await confirmDialog.ask({
      title: 'Pesanan telah sampai?',
      message: 'Buyer baru bisa mengonfirmasi diterima setelah status ini diubah menjadi Telah Sampai.',
      confirmText: 'Tandai sampai',
    });
    if (!ok) return;
    try {
      await apiEndpoints.sellerArriveOrder(id);
      toast.success('Pesanan ditandai telah sampai');
      load();
    } catch (e: any) {
      toast.error(e.message || 'Gagal mengubah status pesanan');
    }
  }
</script>

<svelte:head><title>Pesanan Masuk - MPSI Seller</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">Pesanan Masuk</h1>
  <div class="grid lg:grid-cols-[230px_1fr] gap-6">
    <SellerSidebar />
    <div class="space-y-5">
      <div class="card !p-3">
        <div class="flex items-center gap-2 rounded-2xl bg-ink-50 px-3">
          <Icon name="search" size={14} class="text-ink-400" />
          <input bind:value={search} on:input={onSearchInput} class="flex-1 bg-transparent py-2 text-sm outline-none" placeholder="Cari pesanan, buyer, produk, resi" />
          {#if search}
            <button type="button" on:click={() => { search = ''; load(); }} class="text-ink-400 hover:text-ink-700"><Icon name="x" size={14} /></button>
          {/if}
        </div>
      </div>
      {#if loading}
        <div class="card text-center text-ink-500 py-12">Memuat...</div>
      {:else if items.length === 0}
        <div class="card text-center py-12 text-ink-500">
          <Icon name="package-open" size={48} class="mx-auto text-ink-300 mb-3" />
          <p>Belum ada pesanan.</p>
        </div>
      {:else}
        <div class="space-y-4">
          {#each items as it (it.id)}
            {@const order = it.order}
            {@const address = order?.address}
            {@const buyer = order?.user}
            {@const status = order?.status ?? ''}
            <article class="card">
              <div class="flex flex-wrap items-start justify-between gap-4 border-b border-ink-100 pb-4">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <b class="font-mono text-sm">{order?.order_number}</b>
                    <span class="pill {statusPill(status)}">{ORDER_STATUS_LABEL[status] ?? status}</span>
                  </div>
                  <div class="mt-1 text-xs text-ink-500">
                    {order?.created_at ? new Date(order.created_at).toLocaleString('id-ID') : '-'}
                  </div>
                  <p class="mt-2 text-xs text-ink-600">{statusNote(status)}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                  {#if status === 'PAID'}
                    <button on:click={() => processOrder(order.id)} class="btn-primary btn-sm">
                      <Icon name="package-check" size={12} /> Proses
                    </button>
                  {:else if status === 'PROCESSING'}
                    <button on:click={() => ship(order.id)} class="btn-primary btn-sm">
                      <Icon name="truck" size={12} /> Kirim
                    </button>
                  {:else if status === 'IN_TRANSIT'}
                    <button on:click={() => arrive(order.id)} class="btn-primary btn-sm">
                      <Icon name="map-pin-check" size={12} /> Telah sampai
                    </button>
                  {:else if status === 'ARRIVED'}
                    <span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700">
                      Menunggu konfirmasi buyer
                    </span>
                  {:else if status === 'DONE'}
                    <a href="/seller/withdraw" class="btn-outline btn-sm">
                      <Icon name="wallet" size={12} /> Tarik dana
                    </a>
                  {/if}
                </div>
              </div>

              {#if order?.notes}
                <div class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">
                  <b>Catatan buyer:</b> {order.notes}
                </div>
              {/if}

              <details class="mt-4 md:hidden">
                <summary class="cursor-pointer rounded-xl bg-ink-50 px-3 py-2 text-sm font-medium">Detail buyer, alamat, dan pengiriman</summary>
                <div class="grid gap-4 py-4">
                  <section>
                    <div class="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-400">Akun pembeli</div>
                    <div class="text-sm font-semibold">{buyer?.name ?? address?.recipient ?? '-'}</div>
                    <div class="mt-1 text-xs text-ink-500">{buyer?.email ?? '-'}</div>
                    <div class="text-xs text-ink-500">{buyer?.phone || address?.phone || '-'}</div>
                  </section>
                  <section>
                    <div class="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-400">Alamat pengiriman</div>
                    <div class="text-sm font-semibold">{address?.recipient ?? '-'}</div>
                    <div class="mt-1 text-xs leading-relaxed text-ink-600">{addressText(address)}</div>
                  </section>
                  <section>
                    <div class="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-400">Pembayaran & ekspedisi</div>
                    <div class="text-sm">{order?.payment?.method_name ?? '-'}</div>
                    <div class="mt-1 text-xs text-ink-500">Payment: {order?.payment?.status ?? '-'}</div>
                    <div class="text-xs text-ink-500">{order?.courier_name ?? '-'} {order?.courier_service ? `- ${order.courier_service}` : ''}</div>
                  </section>
                </div>
              </details>

              <div class="hidden gap-4 py-4 md:grid md:grid-cols-3">
                <section>
                  <div class="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-400">Akun pembeli</div>
                  <div class="text-sm font-semibold">{buyer?.name ?? address?.recipient ?? '-'}</div>
                  <div class="mt-1 text-xs text-ink-500">{buyer?.email ?? '-'}</div>
                  <div class="text-xs text-ink-500">{buyer?.phone || address?.phone || '-'}</div>
                </section>

                <section>
                  <div class="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-400">Alamat pengiriman</div>
                  <div class="text-sm font-semibold">{address?.recipient ?? '-'}</div>
                  <div class="mt-1 text-xs leading-relaxed text-ink-600">{addressText(address)}</div>
                  {#if address?.address_note}
                    <div class="mt-1 text-xs text-ink-500">Catatan: {address.address_note}</div>
                  {/if}
                </section>

                <section>
                  <div class="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-400">Pembayaran & ekspedisi</div>
                  <div class="text-sm">{order?.payment?.method_name ?? '-'}</div>
                  <div class="mt-1 text-xs text-ink-500">Payment: {order?.payment?.status ?? '-'}</div>
                  <div class="text-xs text-ink-500">{order?.courier_name ?? '-'} {order?.courier_service ? `- ${order.courier_service}` : ''}</div>
                  {#if order?.tracking_no}
                    <div class="mt-1 text-xs">Resi: <b class="font-mono">{order.tracking_no}</b></div>
                  {/if}
                </section>
              </div>

              <div class="flex flex-col gap-3 rounded-xl bg-ink-50 p-3 sm:flex-row sm:items-center">
                <a href={productHref(it)} class="shrink-0">
                  <img src={it.product_image || it.product?.image} alt="" class="h-16 w-16 rounded-xl object-cover" />
                </a>
                <div class="min-w-0 flex-1">
                  <a href={productHref(it)} class="font-semibold text-sm hover:text-ink-950 line-clamp-2">{it.product_name}</a>
                  {#if it.variant_selection}
                    <div class="mt-0.5 text-xs text-ink-500">{it.variant_selection}</div>
                  {/if}
                  <div class="mt-1 text-xs text-ink-500">{it.quantity} pcs x {fmtRp(it.price)}</div>
                </div>
                <div class="text-left sm:text-right">
                  <div class="text-xs text-ink-500">Subtotal seller</div>
                  <div class="font-semibold">{fmtRp(it.price * it.quantity)}</div>
                </div>
              </div>
            </article>
          {/each}
        </div>
      {/if}
    </div>
  </div>
</div>
