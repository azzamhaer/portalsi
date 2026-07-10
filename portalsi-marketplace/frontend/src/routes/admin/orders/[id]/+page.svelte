<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, confirmDialog } from '$lib/stores.svelte';
  import { page } from '$app/stores';
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp, statusPill, ORDER_STATUS_LABEL } from '$lib/utils';

  let order = $state<any>(null);
  let loading = $state(true);
  let saving = $state(false);
  let newStatus = $state('');
  let trackingNo = $state('');

  onMount(async () => {
    const id = $page.params.id ?? '0';
    try {
      order = await apiEndpoints.adminOrder(+id);
      newStatus = order.status;
      trackingNo = order.tracking_no ?? '';
    } catch (e: any) { toast.error(e.message); } finally { loading = false; }
  });

  async function save() {
    const ok = await confirmDialog.ask({ title: 'Simpan perubahan order?', message: `Status akan diubah menjadi ${newStatus}.`, confirmText: 'Simpan' });
    if (!ok) return;
    saving = true;
    try {
      await apiEndpoints.adminUpdateOrder(order.id, { status: newStatus, tracking_no: trackingNo || null });
      toast.success('Pesanan diperbarui');
      order.status = newStatus;
      order.tracking_no = trackingNo || null;
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }

  // Grouping items per vendor
  const grouped = $derived.by(() => {
    if (!order) return {};
    const g: Record<string, any[]> = {};
    for (const it of order.items ?? []) {
      const key = String(it.vendor?.id ?? it.vendor_id ?? '?');
      (g[key] = g[key] || []).push(it);
    }
    return g;
  });
</script>

<a href="/admin/orders" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-950 mb-4">
  <Icon name="arrow-left" size={14} /> Kembali ke daftar pesanan
</a>

{#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else if order}
  <div class="grid lg:grid-cols-[1fr_320px] gap-5">
    <div class="space-y-5">
      <!-- Header pesanan -->
      <div class="card">
        <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
          <div>
            <div class="text-xs text-ink-500">Order</div>
            <h2 class="font-display text-xl font-bold tracking-tightest font-mono">{order.order_number}</h2>
            <div class="text-xs text-ink-500 mt-1">Dibuat {new Date(order.created_at).toLocaleString('id-ID')}</div>
          </div>
          <span class="pill {statusPill(order.status)}">{ORDER_STATUS_LABEL[order.status]}</span>
        </div>
        {#if order.tracking_no}
          <div class="text-sm flex items-center gap-2 mt-2">
            <Icon name="truck" size={14} class="text-ink-500" />
            <span class="font-mono">{order.tracking_no}</span>
            <span class="text-xs text-ink-500">({order.courier_name})</span>
          </div>
        {/if}
        {#if order.notes}
          <div class="mt-3 bg-ink-50 p-3 rounded-xl text-sm">
            <div class="text-xs text-ink-500 mb-1">Catatan pembeli:</div>
            {order.notes}
          </div>
        {/if}
      </div>

      <!-- Pembeli -->
      <div class="card">
        <h3 class="font-semibold mb-3 flex items-center gap-2"><Icon name="user" size={16} /> Pembeli</h3>
        <div class="grid sm:grid-cols-2 gap-3 text-sm">
          <div>
            <div class="text-xs text-ink-500">Nama</div>
            <a href={`/admin/users/${order.user?.id}`} class="font-medium hover:underline">{order.user?.name}</a>
          </div>
          <div>
            <div class="text-xs text-ink-500">Email</div>
            <div>{order.user?.email}</div>
          </div>
          {#if order.user?.phone}
            <div>
              <div class="text-xs text-ink-500">HP</div>
              <div>{order.user.phone}</div>
            </div>
          {/if}
        </div>
        {#if order.address}
          <div class="mt-3 pt-3 border-t border-ink-100">
            <div class="text-xs text-ink-500 mb-1">Alamat Pengiriman</div>
            <div class="text-sm">
              <b>{order.address.recipient}</b> · {order.address.phone}<br />
              {order.address.full_address}, {order.address.city}
              {#if order.address.postal_code} {order.address.postal_code}{/if}
            </div>
            {#if order.address.latitude && order.address.longitude}
              <a href={`https://www.google.com/maps?q=${order.address.latitude},${order.address.longitude}`} target="_blank" class="text-xs text-blue-600 hover:underline mt-1 inline-flex items-center gap-1">Buka di Maps <Icon name="external-link" size={10} /></a>
            {/if}
          </div>
        {/if}
      </div>

      <!-- Items per vendor -->
      <div class="card">
        <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="package" size={16} /> Item Pesanan</h3>
        {#each Object.entries(grouped) as [vid, items]}
          <div class="border border-ink-100 rounded-2xl p-3 mb-3 last:mb-0">
            <div class="flex items-center gap-2 pb-2 mb-2 border-b border-ink-100">
              <img src={items[0].vendor?.avatar} alt="" class="w-8 h-8 rounded-full object-cover" />
              <div class="flex-1 min-w-0">
                <a href={items[0].vendor?.username ? `/${items[0].vendor.username}` : '#'} target="_blank" class="font-semibold text-sm hover:underline">{items[0].vendor?.name ?? 'Vendor #' + vid}</a>
                {#if items[0].vendor?.user}
                  <div class="text-xs text-ink-500">{items[0].vendor.user.name} · {items[0].vendor.user.email}</div>
                {/if}
              </div>
              {#if items[0].vendor?.id}<a href={`/admin/users/${items[0].vendor.user_id}`} class="text-xs px-2.5 py-1 rounded-full bg-ink-100 hover:bg-ink-200">Detail seller</a>{/if}
            </div>
            {#each items as it}
              <div class="flex items-start gap-3 py-2 last:pb-0">
                <a href={`/product/${it.product?.slug || it.product?.id}`} target="_blank">
                  <img src={it.product?.image ?? it.product_image} alt="" class="w-14 h-14 rounded-xl object-cover" />
                </a>
                <div class="flex-1 min-w-0">
                  <a href={`/product/${it.product?.slug || it.product?.id}`} target="_blank" class="text-sm font-medium hover:underline line-clamp-2">{it.product_name}</a>
                  {#if it.variant_selection}<div class="text-[11px] text-ink-500">{it.variant_selection}</div>{/if}
                  <div class="text-xs text-ink-500 mt-0.5">{fmtRp(it.price)} × {it.quantity}</div>
                </div>
                <div class="font-semibold text-sm">{fmtRp(it.price * it.quantity)}</div>
              </div>
            {/each}
          </div>
        {/each}
      </div>
    </div>

    <!-- Sidebar admin actions -->
    <aside class="space-y-5 lg:sticky lg:top-24 self-start">
      <div class="card">
        <h3 class="font-semibold mb-3">Ringkasan</h3>
        <div class="text-sm space-y-1.5">
          <div class="flex justify-between"><span class="text-ink-500">Subtotal</span><span>{fmtRp(order.subtotal)}</span></div>
          <div class="flex justify-between"><span class="text-ink-500">Ongkir</span><span>{fmtRp(order.shipping)}</span></div>
          {#if order.insurance > 0}<div class="flex justify-between"><span class="text-ink-500">Asuransi</span><span>{fmtRp(order.insurance)}</span></div>{/if}
          {#if order.payment_fee > 0}<div class="flex justify-between"><span class="text-ink-500">Fee bayar</span><span>{fmtRp(order.payment_fee)}</span></div>{/if}
          <div class="flex justify-between pt-2 border-t border-ink-100 font-semibold"><span>Total</span><span>{fmtRp(order.total)}</span></div>
        </div>
        {#if order.payment}
          <div class="mt-3 pt-3 border-t border-ink-100 text-xs space-y-1">
            <div class="text-ink-500">Pembayaran</div>
            <div><b>{order.payment.method_name}</b> · {order.payment.status}</div>
            {#if order.payment.pay_code}<div class="font-mono">{order.payment.pay_code}</div>{/if}
          </div>
        {/if}
      </div>

      <div class="card">
        <h3 class="font-semibold mb-3">Tindakan Admin</h3>
        <div class="space-y-3">
          <div>
            <label class="label">Ubah Status</label>
            <select bind:value={newStatus} class="input">
              {#each Object.entries(ORDER_STATUS_LABEL) as [k, v]}<option value={k}>{v}</option>{/each}
            </select>
          </div>
          <div>
            <label class="label">No. Resi</label>
            <input bind:value={trackingNo} class="input font-mono text-xs" placeholder="JNT0000000001" />
          </div>
          <button on:click={save} disabled={saving} class="btn-primary btn-md w-full">{saving ? 'Menyimpan…' : 'Simpan Perubahan'}</button>
        </div>
      </div>
    </aside>
  </div>
{/if}
