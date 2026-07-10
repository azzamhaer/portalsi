<script lang="ts">
  import { onMount } from 'svelte';
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, confirmDialog } from '$lib/stores.svelte';
  import { fmtRp } from '$lib/utils';

  let vouchers = $state<any[]>([]);
  let products = $state<any[]>([]);
  let loading = $state(true);
  let saving = $state(false);
  let editingId = $state<number | null>(null);
  let formError = $state('');
  let form = $state<any>({
    code: '',
    type: 'FIXED',
    value: 10000,
    min_subtotal: 0,
    max_discount: '',
    usage_limit: '',
    starts_at: '',
    ends_at: '',
    is_active: true,
    product_ids: [],
  });

  const activeCount = $derived(vouchers.filter((v) => v.is_active).length);
  const usedCount = $derived(vouchers.reduce((sum, v) => sum + Number(v.used_count || 0), 0));

  async function load() {
    loading = true;
    try {
      const [vs, ps]: any[] = await Promise.all([apiEndpoints.sellerVouchers(), apiEndpoints.sellerProducts()]);
      vouchers = vs;
      products = ps;
    } catch (e: any) {
      toast.error(e.message || 'Gagal memuat voucher');
    } finally {
      loading = false;
    }
  }

  onMount(load);

  function emptyForm() {
    return {
      code: '',
      type: 'FIXED',
      value: 10000,
      min_subtotal: 0,
      max_discount: '',
      usage_limit: '',
      starts_at: '',
      ends_at: '',
      is_active: true,
      product_ids: [],
    };
  }

  function reset() {
    editingId = null;
    formError = '';
    form = emptyForm();
  }

  function toLocalInput(value: any) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function edit(v: any) {
    editingId = v.id;
    formError = '';
    form = {
      code: v.code,
      type: v.type,
      value: v.value,
      min_subtotal: v.min_subtotal ?? 0,
      max_discount: v.max_discount ?? '',
      usage_limit: v.usage_limit ?? '',
      starts_at: toLocalInput(v.starts_at),
      ends_at: toLocalInput(v.ends_at),
      is_active: Boolean(v.is_active),
      product_ids: (v.products ?? []).map((p: any) => p.id),
    };
  }

  function toggleProduct(id: number) {
    form.product_ids = form.product_ids.includes(id)
      ? form.product_ids.filter((x: number) => x !== id)
      : [...form.product_ids, id];
  }

  function voucherLabel(v: any) {
    if (v.type === 'PERCENT') return `${v.value}%`;
    return fmtRp(v.value);
  }

  function cleanOptionalNumber(value: any) {
    if (value === '' || value === null || value === undefined) return null;
    const n = Number(value);
    return Number.isFinite(n) ? Math.max(0, Math.floor(n)) : null;
  }

  function buildPayload() {
    const code = form.code.trim().toUpperCase().replace(/\s+/g, '-');
    const value = Number(form.value);
    const minSubtotal = Number(form.min_subtotal || 0);
    const usageLimit = cleanOptionalNumber(form.usage_limit);

    if (!code) throw new Error('Kode voucher wajib diisi');
    if (!/^[A-Z0-9_-]+$/.test(code)) throw new Error('Kode hanya boleh berisi huruf, angka, strip, dan underscore');
    if (!Number.isFinite(value) || value < 1) throw new Error('Nilai voucher minimal 1');
    if (form.type === 'PERCENT' && value > 100) throw new Error('Voucher persen maksimal 100%');
    if (!Number.isFinite(minSubtotal) || minSubtotal < 0) throw new Error('Minimum subtotal tidak valid');
    if (usageLimit !== null && usageLimit < 1) throw new Error('Limit pakai minimal 1');

    return {
      code,
      type: form.type,
      value: Math.floor(value),
      min_subtotal: Math.floor(minSubtotal),
      max_discount: cleanOptionalNumber(form.max_discount),
      usage_limit: usageLimit,
      starts_at: form.starts_at || null,
      ends_at: form.ends_at || null,
      is_active: Boolean(form.is_active),
      product_ids: form.product_ids,
    };
  }

  async function save() {
    formError = '';
    let body: any;
    try {
      body = buildPayload();
    } catch (e: any) {
      formError = e.message;
      toast.warn(e.message);
      return;
    }

    saving = true;
    try {
      if (editingId) await apiEndpoints.sellerUpdateVoucher(editingId, body);
      else await apiEndpoints.sellerCreateVoucher(body);
      toast.success(editingId ? 'Voucher diperbarui' : 'Voucher dibuat');
      reset();
      await load();
    } catch (e: any) {
      formError = e.message || 'Voucher gagal disimpan';
      toast.error(formError);
    } finally {
      saving = false;
    }
  }

  async function remove(id: number) {
    const ok = await confirmDialog.ask({ title: 'Hapus voucher?', message: 'Voucher ini tidak bisa dipakai lagi setelah dihapus.', confirmText: 'Hapus', tone: 'danger' });
    if (!ok) return;
    try {
      await apiEndpoints.sellerDeleteVoucher(id);
      toast.success('Voucher dihapus');
      await load();
    } catch (e: any) {
      toast.error(e.message || 'Voucher gagal dihapus');
    }
  }
</script>

<svelte:head><title>Voucher Seller - MPSI Seller</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">Seller Center</h1>
  <div class="grid gap-6 lg:grid-cols-[230px_1fr]">
    <SellerSidebar />

    <div class="space-y-5">
      <div class="card flex flex-wrap items-center justify-between gap-4">
        <div>
          <div class="section-eyebrow mb-1">Promo</div>
          <h2 class="text-xl font-semibold">Voucher Toko</h2>
          <p class="mt-1 text-sm text-ink-500">Kelola kode diskon untuk semua produk atau produk tertentu.</p>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center text-xs">
          <div class="rounded-xl bg-ink-50 px-4 py-3">
            <div class="text-lg font-bold text-ink-950">{vouchers.length}</div>
            <div class="text-ink-500">Voucher</div>
          </div>
          <div class="rounded-xl bg-emerald-50 px-4 py-3">
            <div class="text-lg font-bold text-emerald-700">{activeCount}</div>
            <div class="text-emerald-700">Aktif</div>
          </div>
          <div class="rounded-xl bg-blue-50 px-4 py-3">
            <div class="text-lg font-bold text-blue-700">{usedCount}</div>
            <div class="text-blue-700">Dipakai</div>
          </div>
        </div>
      </div>

      {#if loading}
        <div class="card py-12 text-center text-ink-500">Memuat...</div>
      {:else}
        <div class="grid gap-5 xl:grid-cols-[380px_1fr]">
          <div class="card h-fit">
            <div class="mb-4 flex items-center justify-between gap-3">
              <h3 class="font-semibold">{editingId ? 'Edit Voucher' : 'Buat Voucher'}</h3>
              {#if editingId}
                <button type="button" on:click={reset} class="btn-outline btn-sm">Batal</button>
              {/if}
            </div>

            {#if formError}
              <div class="mb-4 rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</div>
            {/if}

            <div class="space-y-3">
              <div>
                <label class="label">Kode</label>
                <input bind:value={form.code} class="input uppercase" maxlength="30" placeholder="HEMAT10" />
                <p class="helper mt-1">Spasi otomatis diganti menjadi strip saat disimpan.</p>
              </div>

              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="label">Tipe</label>
                  <select bind:value={form.type} class="input">
                    <option value="FIXED">Potongan Rp</option>
                    <option value="PERCENT">Persen</option>
                  </select>
                </div>
                <div>
                  <label class="label">{form.type === 'PERCENT' ? 'Persen' : 'Nominal'}</label>
                  <input type="number" min="1" max={form.type === 'PERCENT' ? 100 : undefined} bind:value={form.value} class="input" />
                </div>
              </div>

              <div>
                <label class="label">Minimum subtotal item</label>
                <input type="number" min="0" bind:value={form.min_subtotal} class="input" />
              </div>

              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="label">Maks diskon</label>
                  <input type="number" min="0" bind:value={form.max_discount} class="input" placeholder="Opsional" />
                </div>
                <div>
                  <label class="label">Limit pakai</label>
                  <input type="number" min="1" bind:value={form.usage_limit} class="input" placeholder="Opsional" />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="label">Mulai</label>
                  <input type="datetime-local" bind:value={form.starts_at} class="input" />
                </div>
                <div>
                  <label class="label">Berakhir</label>
                  <input type="datetime-local" bind:value={form.ends_at} class="input" />
                </div>
              </div>

              <label class="flex items-center gap-2 rounded-xl border border-ink-100 px-3 py-2 text-sm">
                <input type="checkbox" bind:checked={form.is_active} />
                <span>Voucher aktif dan bisa dipakai pembeli</span>
              </label>

              <div>
                <label class="label">Produk berlaku</label>
                <p class="helper mb-2">Kosongkan pilihan untuk berlaku di semua produk toko.</p>
                <div class="max-h-64 space-y-1 overflow-y-auto rounded-2xl border border-ink-100 p-2">
                  {#if products.length === 0}
                    <div class="px-2 py-4 text-center text-sm text-ink-500">Belum ada produk toko.</div>
                  {:else}
                    {#each products as p (p.id)}
                      <label class="flex cursor-pointer items-center gap-2 rounded-xl p-2 text-sm hover:bg-ink-50">
                        <input type="checkbox" checked={form.product_ids.includes(p.id)} on:change={() => toggleProduct(p.id)} />
                        <img src={p.image} alt="" class="h-9 w-9 rounded-lg object-cover" />
                        <span class="line-clamp-1 flex-1">{p.name}</span>
                        <span class="text-xs text-ink-400">{fmtRp(p.price)}</span>
                      </label>
                    {/each}
                  {/if}
                </div>
              </div>

              <button type="button" on:click={save} disabled={saving} class="btn-primary btn-md w-full">
                <Icon name="save" size={14} />
                {saving ? 'Menyimpan...' : editingId ? 'Simpan Perubahan' : 'Tambah Voucher'}
              </button>
            </div>
          </div>

          <div class="space-y-3">
            {#if vouchers.length === 0}
              <div class="card py-12 text-center text-ink-500">
                <Icon name="ticket-percent" size={24} class="mx-auto mb-2 text-ink-300" />
                <p>Belum ada voucher.</p>
              </div>
            {:else}
              {#each vouchers as v (v.id)}
                <div class="card">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-xl bg-app-primary px-3 py-1.5 text-sm font-bold text-app-pfg">{v.code}</span>
                        <span class={v.is_active ? 'pill-green' : 'pill-red'}>{v.is_active ? 'Aktif' : 'Nonaktif'}</span>
                        <span class="rounded-full bg-ink-100 px-2 py-1 text-xs text-ink-600">{voucherLabel(v)}</span>
                      </div>
                      <div class="mt-2 text-sm text-ink-700">
                        {#if v.max_discount}Maks {fmtRp(v.max_discount)}{/if}
                        {#if v.max_discount && v.min_subtotal} - {/if}
                        {#if v.min_subtotal}Min belanja {fmtRp(v.min_subtotal)}{/if}
                        {#if !v.max_discount && !v.min_subtotal}Tanpa batas minimum{/if}
                      </div>
                      <div class="mt-1 text-xs text-ink-500">Dipakai {v.used_count ?? 0}{#if v.usage_limit} / {v.usage_limit}{/if} kali</div>
                      <div class="mt-3 flex flex-wrap gap-1.5">
                        {#if v.products?.length}
                          {#each v.products.slice(0, 6) as p (p.id)}
                            <span class="rounded-full bg-ink-100 px-2 py-1 text-[11px]">#{p.name}</span>
                          {/each}
                          {#if v.products.length > 6}<span class="rounded-full bg-ink-100 px-2 py-1 text-[11px]">+{v.products.length - 6}</span>{/if}
                        {:else}
                          <span class="rounded-full bg-ink-100 px-2 py-1 text-[11px]">Semua produk</span>
                        {/if}
                      </div>
                    </div>
                    <div class="flex gap-1">
                      <button type="button" on:click={() => edit(v)} class="btn-outline btn-sm" aria-label="Edit voucher"><Icon name="pencil" size={12} /></button>
                      <button type="button" on:click={() => remove(v.id)} class="btn-sm bg-red-50 text-red-700 hover:bg-red-100" aria-label="Hapus voucher"><Icon name="trash-2" size={12} /></button>
                    </div>
                  </div>
                </div>
              {/each}
            {/if}
          </div>
        </div>
      {/if}
    </div>
  </div>
</div>
