<script lang="ts">
  import { onMount } from 'svelte';
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';
  import { fmtRp } from '$lib/utils';

  let data = $state<any>(null);
  let loading = $state(true);
  let amount = $state(0);
  let submitting = $state(false);

  async function load() {
    try {
      data = await apiEndpoints.sellerWithdraw();
      if (!amount) amount = data.available;
    } catch (e: any) { toast.error(e.message); }
  }

  onMount(async () => { await load(); loading = false; });

  async function submit(e: Event) {
    e.preventDefault();
    if (amount < 10_000) { toast.error('Minimum penarikan Rp 10.000'); return; }
    if (amount > data.available) { toast.error('Saldo tidak cukup'); return; }
    submitting = true;
    try {
      await apiEndpoints.sellerRequestWithdraw(amount);
      toast.success('Permintaan penarikan diajukan');
      amount = 0;
      await load();
    } catch (e: any) { toast.error(e.message); } finally { submitting = false; }
  }

  async function cancel(id: number) {
    if (!confirm('Batalkan permintaan ini?')) return;
    try {
      await apiEndpoints.sellerCancelWithdraw(id);
      toast.success('Dibatalkan');
      await load();
    } catch (e: any) { toast.error(e.message); }
  }

  function statusPill(s: string) {
    return s === 'PENDING'   ? 'bg-amber-100 text-amber-700'
         : s === 'APPROVED'  ? 'bg-sky-100 text-sky-700'
         : s === 'PAID'      ? 'bg-emerald-100 text-emerald-700'
         : s === 'REJECTED'  ? 'bg-red-100 text-red-700' : 'bg-ink-100 text-ink-700';
  }
  const statusLabel: Record<string, string> = {
    PENDING: 'Menunggu', APPROVED: 'Disetujui', PAID: 'Dibayar', REJECTED: 'Ditolak'
  };
</script>

<svelte:head><title>Penarikan Dana — Seller</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <h1 class="section-title mb-6 sm:mb-8">Seller Center</h1>
  <div class="grid lg:grid-cols-[230px_1fr] gap-6">
    <SellerSidebar />
    <div class="space-y-5">
      {#if loading}
        <div class="card text-center text-ink-500 py-10">Memuat…</div>
      {:else if data}
        <!-- Saldo cards -->
        <div class="grid sm:grid-cols-3 gap-3">
          <div class="card">
            <div class="text-xs uppercase tracking-widest text-ink-500">Pendapatan Kotor</div>
            <div class="font-display text-xl font-bold tracking-tightest mt-2">{fmtRp(data.gross_earning)}</div>
            <div class="text-xs text-ink-500 mt-1">Dari pesanan selesai atau otomatis cair setelah 7 hari sejak sampai</div>
          </div>
          <div class="card">
            <div class="text-xs uppercase tracking-widest text-ink-500">Komisi Platform ({data.commission_percent}%)</div>
            <div class="font-display text-xl font-bold tracking-tightest mt-2 text-red-600">−{fmtRp(data.commission)}</div>
            <div class="text-xs text-ink-500 mt-1">Biaya layanan</div>
          </div>
          <div class="card bg-app-primary text-app-pfg">
            <div class="text-xs uppercase tracking-widest text-white/60">Saldo Tersedia</div>
            <div class="font-display text-xl font-bold tracking-tightest mt-2">{fmtRp(data.available)}</div>
            <div class="text-xs text-white/60 mt-1">Sudah ditarik: {fmtRp(data.withdrawn)}</div>
          </div>
        </div>

        <!-- Form withdraw -->
        <div class="card">
          <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="wallet" size={18} /> Ajukan Penarikan</h3>
          <div class="mb-4 rounded-xl bg-blue-50 px-3 py-2 text-xs text-blue-800">
            Jika buyer tidak menandai pesanan diterima dan tidak mengajukan pengembalian, saldo otomatis masuk setelah 7 hari dari status Telah Sampai, dipotong komisi platform sesuai pengaturan admin.
          </div>
          {#if !data.bank.name || !data.bank.account || !data.bank.holder}
            <div class="bg-amber-50 text-amber-800 text-sm p-3 rounded-xl mb-4">
              Lengkapi data bank Anda dulu di <a href="/seller/profile" class="underline font-semibold">Profil Toko</a>.
            </div>
          {:else}
            <div class="bg-ink-50 p-3 rounded-xl mb-4 text-sm">
              <div class="text-xs text-ink-500 mb-1">Dana akan ditransfer ke:</div>
              <div class="font-semibold">{data.bank.name} • {data.bank.account}</div>
              <div class="text-ink-600">a.n. {data.bank.holder}</div>
            </div>
            <form on:submit={submit} class="space-y-3">
              <div>
                <label class="label">Jumlah penarikan (Rp)</label>
                <input bind:value={amount} type="number" min="10000" max={data.available} step="10000" class="input" />
                <div class="text-xs text-ink-500 mt-1">
                  Min Rp 10.000 — Max {fmtRp(data.available)}
                </div>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" on:click={() => amount = Math.min(100_000, data.available)} class="text-xs px-3 py-1.5 rounded-full bg-ink-100 hover:bg-ink-200">Rp 100rb</button>
                <button type="button" on:click={() => amount = Math.min(500_000, data.available)} class="text-xs px-3 py-1.5 rounded-full bg-ink-100 hover:bg-ink-200">Rp 500rb</button>
                <button type="button" on:click={() => amount = Math.min(1_000_000, data.available)} class="text-xs px-3 py-1.5 rounded-full bg-ink-100 hover:bg-ink-200">Rp 1jt</button>
                <button type="button" on:click={() => amount = data.available} class="text-xs px-3 py-1.5 rounded-full bg-app-primary text-app-pfg">Semua</button>
              </div>
              <button disabled={submitting || data.available < 10000} class="btn-primary btn-md w-full sm:w-auto">
                {submitting ? 'Mengajukan…' : 'Tarik Sekarang'}
              </button>
            </form>
          {/if}
        </div>

        <!-- History -->
        <div class="card">
          <h3 class="font-semibold mb-4">Riwayat Penarikan</h3>
          {#if data.history.length === 0}
            <p class="text-sm text-ink-500 text-center py-6">Belum ada riwayat.</p>
          {:else}
            <div class="overflow-x-auto -mx-4 sm:mx-0">
              <table class="w-full text-sm min-w-[500px]">
                <thead class="text-xs text-ink-500 border-b border-ink-100">
                  <tr>
                    <th class="text-left py-2 font-medium px-4 sm:px-0">Tanggal</th>
                    <th class="text-left py-2 font-medium">Jumlah</th>
                    <th class="text-left py-2 font-medium">Status</th>
                    <th class="text-left py-2 font-medium">Catatan</th>
                    <th class="text-right py-2 font-medium px-4 sm:px-0"></th>
                  </tr>
                </thead>
                <tbody>
                  {#each data.history as h}
                    <tr class="border-b border-ink-100 last:border-0">
                      <td class="py-2.5 text-xs px-4 sm:px-0">{new Date(h.created_at).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })}</td>
                      <td class="py-2.5 font-semibold">{fmtRp(h.amount)}</td>
                      <td class="py-2.5"><span class="pill {statusPill(h.status)}">{statusLabel[h.status] ?? h.status}</span></td>
                      <td class="py-2.5 text-xs text-ink-500">{h.admin_note ?? '—'}</td>
                      <td class="py-2.5 text-right px-4 sm:px-0">
                        {#if h.status === 'PENDING'}
                          <button on:click={() => cancel(h.id)} class="text-xs text-red-600 hover:underline">Batal</button>
                        {/if}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </div>
      {/if}
    </div>
  </div>
</div>
