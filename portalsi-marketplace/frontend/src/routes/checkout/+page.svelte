<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { cart, auth, toast, confirmDialog } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { fmtRp } from '$lib/utils';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';

  let { data } = $props();

  const COURIERS = [
    { name: 'JNE Reguler', eta: '2-4 hari', cost: 12000 },
    { name: 'J&T Express', eta: '2-3 hari', cost: 14000 },
    { name: 'SiCepat REG', eta: '2-4 hari', cost: 11000 },
    { name: 'AnterAja', eta: '1-3 hari', cost: 13000 },
    { name: 'GoSend Sameday', eta: 'Hari Ini', cost: 25000 },
    { name: 'Pos Indonesia', eta: '3-5 hari', cost: 9000 },
  ];

  let ship = $state<any>({ recipient: '', phone: '', country: 'Indonesia', province: '', city: '', district: '', village: '', postal_code: '', full_address: '', address_note: '', latitude: null, longitude: null });
  let addresses = $state<any[]>([]);
  let selectedAddressId = $state('');
  let notes = $state('');
  let couriers = $state<any[]>(COURIERS);
  let courier = $state<any>(COURIERS[0]);
  let shippingLoading = $state(false);
  let shippingError = $state('');
  let rateTimer: any;
  let pay = $state<string | null>(null);
  let loading = $state(false);
  let checkoutError = $state('');
  let voucherCodes = $state<Record<number, string>>({});
  let voucherLoading = $state<Record<number, boolean>>({});
  let voucherErrors = $state<Record<number, string>>({});
  let appliedVouchers = $state<Record<number, any>>({});

  const items = $derived(cart.checkedItems);
  const subtotal = $derived(cart.subtotal);
  const discountTotal = $derived(Object.values(appliedVouchers).reduce((sum: number, v: any) => sum + (v?.discount ?? 0), 0));
  const discountedSubtotal = $derived(Math.max(0, subtotal - discountTotal));
  const insurance = $derived(discountedSubtotal > 500000 ? Math.round(discountedSubtotal * 0.002) : 0);
  const baseTotal = $derived(discountedSubtotal + (courier?.cost ?? 0) + insurance);
  const method = $derived(pay ? data.methods.find((m: any) => m.code === pay) : null);
  const fee = $derived(method ? Math.round(method.fee_flat + (baseTotal * method.fee_pct / 100)) : 0);
  const total = $derived(baseTotal + fee);
  const selectedAddress = $derived(addresses.find((a) => String(a.id) === selectedAddressId));

  const grouped = $derived.by(() => {
    const g: Record<string, any[]> = {};
    data.methods.forEach((m: any) => { (g[m.group] = g[m.group] || []).push(m); });
    return g;
  });

  onMount(async () => {
    if (!auth.user) { goto('/login?next=/checkout'); return; }
    if (items.length === 0) { goto('/cart'); return; }
    ship = { ...ship, recipient: auth.user.name || '', phone: auth.user.phone || '' };
    try {
      addresses = await apiEndpoints.addresses();
      const def = addresses.find((a) => a.is_default) || addresses[0];
      if (def) chooseAddress(String(def.id));
    } catch (e: any) {
      toast.error(e.message || 'Gagal memuat alamat pengiriman');
    }
  });

  function chooseAddress(id: string) {
    selectedAddressId = id;
    const a = addresses.find((x) => String(x.id) === id);
    if (!a) return;
    ship = { country: 'Indonesia', ...a };
  }

  function addressSubtitle(a: any) {
    return [a.full_address, a.village, a.district, a.city, a.province, a.postal_code].filter(Boolean).join(', ');
  }

  function hasCoords(a: any) {
    return a?.latitude != null && a?.longitude != null;
  }

  async function loadShippingRates() {
    if (!items.length || !ship.city || !ship.full_address || !hasCoords(ship)) return;
    shippingLoading = true;
    shippingError = '';
    try {
      const r: any = await apiEndpoints.shippingRates({
        items: items.map(i => ({ product_id: i.product_id, qty: i.qty })),
        destination: ship,
      });
      couriers = r.options?.length ? r.options : COURIERS;
      courier = couriers[0] ?? null;
      if (r.message) shippingError = r.message;
      else if (r.source === 'manual' || !r.configured) shippingError = 'Menampilkan opsi pengiriman manual dari admin.';
    } catch (e: any) {
      await loadManualShippingOptions();
      shippingError = e.message || 'Gagal memuat tarif ekspedisi otomatis. Menampilkan opsi pengiriman manual.';
    } finally {
      shippingLoading = false;
    }
  }

  async function loadManualShippingOptions() {
    try {
      const opts: any = await apiEndpoints.shippingOptions();
      couriers = opts?.length ? opts : COURIERS;
    } catch {
      couriers = COURIERS;
    }
    courier = couriers[0] ?? null;
  }

  $effect(() => {
    const key = [
      items.map((i) => `${i.product_id}:${i.qty}`).join(','),
      ship.city, ship.district, ship.village, ship.postal_code, ship.latitude, ship.longitude
    ].join('|');
    if (!auth.user || !items.length || !ship.city || !ship.full_address || !hasCoords(ship)) return;
    clearTimeout(rateTimer);
    rateTimer = setTimeout(loadShippingRates, 500);
    return () => clearTimeout(rateTimer);
  });

  function clearVoucher(productId: number) {
    delete appliedVouchers[productId];
    delete voucherErrors[productId];
  }

  async function applyVoucher(it: any) {
    const code = voucherCodes[it.product_id]?.trim();
    clearVoucher(it.product_id);
    if (!code) { toast.warn('Masukkan kode voucher'); return; }
    voucherLoading[it.product_id] = true;
    try {
      const res: any = await apiEndpoints.applyVoucher({
        product_id: it.product_id,
        qty: it.qty,
        voucher_code: code,
      });
      appliedVouchers[it.product_id] = res;
      voucherCodes[it.product_id] = res.code;
      toast.success(`Voucher ${res.code} diterapkan`);
    } catch (e: any) {
      voucherErrors[it.product_id] = e.message || 'Voucher tidak valid';
      toast.error(voucherErrors[it.product_id]);
    } finally {
      voucherLoading[it.product_id] = false;
    }
  }

  async function submit() {
    if (!ship.recipient || !ship.phone || !ship.province || !ship.city || !ship.district || !ship.village || !ship.full_address) {
      toast.warn('Pilih alamat pengiriman dari profil');
      return;
    }
    if (!hasCoords(ship)) {
      toast.warn('Alamat pengiriman belum punya pin lokasi. Lengkapi di halaman profil terlebih dahulu.');
      return;
    }
    if (!pay) { toast.warn('Pilih metode pembayaran'); return; }
    if (!courier) { toast.warn('Pilih ekspedisi pengiriman'); return; }
    const ok = await confirmDialog.ask({
      title: 'Buat pesanan?',
      message: 'Pesanan akan dibuat dengan status menunggu pembayaran. Stok baru dikurangi setelah pembayaran berhasil.',
      confirmText: 'Buat pesanan',
    });
    if (!ok) return;
    loading = true;
    checkoutError = '';
    try {
      const res: any = await apiEndpoints.checkout({
        items: items.map(i => ({
          product_id: i.product_id,
          qty: i.qty,
          variant_selection: i.variant_selection ?? null,
          voucher_code: appliedVouchers[i.product_id]?.code ?? null,
        })),
        address_id: selectedAddressId ? Number(selectedAddressId) : null,
        recipient: ship.recipient,
        phone: ship.phone,
        country: 'Indonesia',
        province: ship.province,
        province_id: ship.province_id,
        city: ship.city,
        city_id: ship.city_id,
        district: ship.district,
        district_id: ship.district_id,
        village: ship.village,
        village_id: ship.village_id,
        postal_code: ship.postal_code,
        rajaongkir_destination_id: ship.rajaongkir_destination_id,
        latitude: ship.latitude,
        longitude: ship.longitude,
        full_address: ship.full_address,
        address_note: ship.address_note,
        notes,
        courier_name: courier.courier_name || courier.name,
        courier_code: courier.courier_code,
        courier_service: courier.service,
        shipping_type: courier.type,
        courier_eta: courier.eta,
        courier_cost: courier.cost,
        shipping_cashback: courier.cashback || 0,
        shipping_service_fee: courier.service_fee || 0,
        shipping_payload: courier.raw ? courier : null,
        payment_method: pay,
      });
      cart.clearChecked();
      toast.success('Pesanan dibuat, silakan lanjutkan pembayaran');
      goto('/orders/' + res.order_id);
    } catch (e: any) {
      checkoutError = e.message || 'Checkout gagal diproses';
      toast.error(checkoutError);
      loading = false;
    }
  }
</script>

<svelte:head><title>Checkout - MPSI</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">Checkout</h1>

  <div class="grid lg:grid-cols-[1fr_400px] gap-8 items-start">
    <div class="space-y-6">
      <div class="card">
        <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="truck" size={16} class="text-ink-500" /> Alamat Pengiriman</h3>
        {#if addresses.length}
          <div class="grid gap-3">
            {#each addresses as a (a.id)}
              <button
                type="button"
                on:click={() => chooseAddress(String(a.id))}
                class="text-left rounded-2xl border p-4 transition hover:bg-ink-50"
                class:border-ink-950={String(a.id) === selectedAddressId}
                class:bg-ink-50={String(a.id) === selectedAddressId}
                class:border-ink-200={String(a.id) !== selectedAddressId}
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <b class="text-sm">{a.recipient}</b>
                      {#if a.is_default}<span class="pill-green">Utama</span>{/if}
                    </div>
                    <div class="mt-1 text-xs text-ink-500">{a.phone}</div>
                    <div class="mt-2 text-sm text-ink-700">{addressSubtitle(a)}</div>
                    {#if !hasCoords(a)}
                      <div class="mt-2 inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-medium text-red-700">
                        <Icon name="map-pin-off" size={11} /> Pin lokasi belum lengkap
                      </div>
                    {/if}
                    {#if a.address_note}<div class="mt-1 text-xs text-ink-500">{a.address_note}</div>{/if}
                  </div>
                  <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full border {String(a.id) === selectedAddressId ? 'border-ink-950 bg-ink-950 text-white' : 'border-ink-200 text-transparent'}">
                    <Icon name="check" size={14} />
                  </span>
                </div>
              </button>
            {/each}
          </div>
          {#if selectedAddress}
            {#if hasCoords(selectedAddress)}
              <div class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                Ongkir dihitung ke alamat: <b>{selectedAddress.village || selectedAddress.city}</b>.
              </div>
            {:else}
              <div class="mt-4 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-700">
                Alamat ini belum punya pin lokasi. Edit alamat di profil sebelum checkout.
              </div>
            {/if}
          {/if}
          <a href="/profile#addresses" class="btn-outline btn-sm mt-4"><Icon name="map-pin-plus" size={14} /> Kelola alamat</a>
        {:else}
          <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5">
            <div class="flex items-start gap-3">
              <Icon name="map-pin" size={18} class="mt-0.5 text-amber-700" />
              <div>
                <h4 class="font-semibold text-amber-900">Belum ada alamat pengiriman</h4>
                <p class="mt-1 text-sm text-amber-800">Tambahkan alamat di profil dulu agar ongkir dan checkout bisa diproses dengan benar.</p>
                <a href="/profile#addresses" class="btn-primary btn-sm mt-4"><Icon name="plus" size={14} /> Tambah alamat di profil</a>
              </div>
            </div>
          </div>
        {/if}
        <div class="mt-4"><label class="label">Catatan pesanan (opsional)</label><textarea class="input" rows={2} bind:value={notes}></textarea></div>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-4">Pengiriman</h3>
        {#if shippingLoading}
          <div class="rounded-xl bg-ink-50 px-3 py-2 text-xs text-ink-600 mb-3">Memuat tarif ekspedisi...</div>
        {/if}
        {#if shippingError}
          <div class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 mb-3">{shippingError}</div>
        {/if}
        <div class="grid sm:grid-cols-2 gap-3">
          {#each couriers as c}
            <button on:click={() => courier = c} class="text-left p-4 border rounded-2xl transition" class:border-ink-950={courier?.name === c.name} class:bg-ink-50={courier?.name === c.name} class:border-ink-200={courier?.name !== c.name}>
              <div class="flex justify-between items-center gap-3"><b>{c.name}</b><span class="text-sm font-semibold">{fmtRp(c.cost)}</span></div>
              <div class="text-xs text-ink-500 mt-0.5">Estimasi {c.eta}{#if c.type} · {c.type}{/if}</div>
            </button>
          {/each}
        </div>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-4">Metode Pembayaran</h3>
        {#each Object.entries(grouped) as [g, ms]}
          <div class="mb-5">
            <h4 class="text-xs font-semibold uppercase tracking-widest text-ink-500 mb-2">{g}</h4>
            <div class="grid sm:grid-cols-2 gap-2">
              {#each ms as m}
                <button on:click={() => pay = m.code} class="text-left flex items-center gap-3 p-3 border rounded-2xl transition" class:border-ink-950={pay === m.code} class:bg-ink-50={pay === m.code} class:border-ink-200={pay !== m.code}>
                  <div class="w-10 h-10 rounded-lg grid place-items-center text-white text-xs font-bold" style:background={m.color}>{m.code.slice(0, 3)}</div>
                  <div class="flex-1 min-w-0">
                    <b class="text-sm block truncate">{m.name}</b>
                    <small class="text-xs text-ink-500">Biaya: {m.fee_pct ? m.fee_pct + '%' : fmtRp(m.fee_flat)}</small>
                  </div>
                </button>
              {/each}
            </div>
          </div>
        {/each}
      </div>

      <div class="card">
        <h3 class="font-semibold mb-3">Item ({items.length})</h3>
        {#each items as it (it.cart_key || it.product_id)}
          {@const applied = appliedVouchers[it.product_id]}
          {@const lineSubtotal = it.price * it.qty}
          <div class="flex items-start gap-3 py-3 border-b border-ink-100 last:border-0">
            <img src={it.image} alt="" class="w-12 h-12 rounded-lg object-cover" />
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium line-clamp-1">{it.name}</div>
              <div class="text-xs text-ink-500">{it.qty} x {fmtRp(it.price)}</div>
              {#if it.variant_selection}<div class="text-[11px] text-ink-500 mt-0.5">{it.variant_selection}</div>{/if}
              <div class="mt-2 flex max-w-md gap-2">
                <input
                  class="input input-sm min-w-0 flex-1 !py-1.5 uppercase"
                  placeholder="Kode voucher seller"
                  bind:value={voucherCodes[it.product_id]}
                  on:input={() => clearVoucher(it.product_id)}
                />
                <button type="button" on:click={() => applyVoucher(it)} disabled={voucherLoading[it.product_id]} class="btn-outline btn-sm shrink-0">
                  {voucherLoading[it.product_id] ? 'Cek...' : applied ? 'Ubah' : 'Apply'}
                </button>
              </div>
              {#if voucherErrors[it.product_id]}
                <div class="mt-1 text-xs text-red-600">{voucherErrors[it.product_id]}</div>
              {/if}
              {#if applied}
                <div class="mt-2 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                  <div class="flex items-center justify-between gap-2 font-semibold">
                    <span>{applied.code} - {applied.label}</span>
                    <span>-{fmtRp(applied.discount)}</span>
                  </div>
                  <div class="mt-1 text-emerald-700">
                    {fmtRp(lineSubtotal)} menjadi {fmtRp(applied.line_total)}
                    {#if applied.max_discount}<span> - maks diskon {fmtRp(applied.max_discount)}</span>{/if}
                  </div>
                </div>
              {/if}
            </div>
            <div class="text-right">
              {#if applied}
                <div class="text-xs text-ink-400 line-through">{fmtRp(lineSubtotal)}</div>
                <b class="text-sm text-emerald-700">{fmtRp(applied.line_total)}</b>
              {:else}
                <b class="text-sm">{fmtRp(lineSubtotal)}</b>
              {/if}
            </div>
          </div>
        {/each}
      </div>
    </div>

    <aside class="lg:sticky lg:top-24">
      <div class="card">
        <h3 class="font-semibold mb-4">Ringkasan Pembayaran</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-ink-500">Subtotal</span><span>{fmtRp(subtotal)}</span></div>
          {#if discountTotal > 0}
            <div class="flex justify-between text-emerald-700"><span>Promo voucher</span><span>-{fmtRp(discountTotal)}</span></div>
            <div class="flex justify-between"><span class="text-ink-500">Subtotal setelah promo</span><span>{fmtRp(discountedSubtotal)}</span></div>
          {/if}
          <div class="flex justify-between"><span class="text-ink-500">Ongkir</span><span>{courier ? fmtRp(courier.cost) : '-'}</span></div>
          {#if insurance > 0}<div class="flex justify-between"><span class="text-ink-500">Asuransi</span><span>{fmtRp(insurance)}</span></div>{/if}
          <div class="flex justify-between"><span class="text-ink-500">Biaya admin{#if method}<span class="text-ink-400"> ({method.name})</span>{/if}</span><span>{method ? fmtRp(fee) : '-'}</span></div>
          <div class="flex justify-between text-base font-bold pt-3 border-t border-ink-100 mt-3">
            <span>Total</span><span>{fmtRp(total)}</span>
          </div>
        </div>
        <button on:click={submit} disabled={loading || !pay || !selectedAddressId || !hasCoords(selectedAddress)} class="btn-primary btn-lg w-full mt-5">
          <Icon name="lock" size={14} /> {loading ? 'Memproses...' : !selectedAddressId ? 'Pilih alamat pengiriman' : !hasCoords(selectedAddress) ? 'Lengkapi pin alamat' : pay ? `Bayar ${fmtRp(total)}` : 'Pilih metode pembayaran'}
        </button>
        {#if checkoutError}
          <div class="mt-4 rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
            {checkoutError}
          </div>
        {/if}
        <div class="flex items-start gap-2 mt-4 text-xs text-ink-500">
          <Icon name="shield-check" size={14} class="text-emerald-600 mt-0.5 shrink-0" />
          <span>Transaksi diproses dengan enkripsi end-to-end.</span>
        </div>
      </div>
    </aside>
  </div>
</div>
