<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, confirmDialog } from '$lib/stores.svelte';
  import { fmtRp } from '$lib/utils';

  let returns = $state<any[]>([]);
  let loading = $state(true);

  async function load() {
    loading = true;
    try { const r: any = await apiEndpoints.adminReturns(); returns = r.data ?? []; }
    finally { loading = false; }
  }
  onMount(load);

  async function approve(id: number, status: string) {
    const ok = await confirmDialog.ask({
      title: status === 'REFUNDED' ? 'Refund ke saldo buyer?' : 'Tolak laporan?',
      message: status === 'REFUNDED'
        ? 'Dana pesanan akan masuk ke saldo profil buyer setelah disetujui.'
        : 'Status pesanan akan kembali ke Telah Sampai dan buyer bisa konfirmasi diterima.',
      confirmText: status === 'REFUNDED' ? 'Refund' : 'Tolak',
      tone: status === 'REFUNDED' ? 'default' : 'danger',
    });
    if (!ok) return;
    try { await apiEndpoints.adminApproveReturn(id, status); toast.success('Status diperbarui'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
</script>

<div class="card mb-4"><h3 class="font-semibold">Permintaan Pengembalian</h3></div>

{#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else if returns.length === 0}
  <div class="card text-center py-12 text-ink-500">Tidak ada permintaan return.</div>
{:else}
  <div class="space-y-3">
    {#each returns as r (r.id)}
      <div class="card">
        <div class="flex items-center justify-between mb-3 pb-3 border-b border-ink-100 flex-wrap gap-2">
          <div>
            <div class="font-semibold">Order #{r.order?.order_number}</div>
            <div class="text-xs text-ink-500">{r.user?.name} · {new Date(r.created_at).toLocaleDateString('id-ID')}</div>
          </div>
          <span class="pill-{r.status === 'PENDING' ? 'amber' : r.status === 'APPROVED' ? 'green' : r.status === 'REJECTED' ? 'red' : 'blue'}">{r.status}</span>
        </div>
        <p class="text-sm mb-3"><b>Alasan:</b> {r.reason}</p>
        <p class="text-xs text-ink-500">Total order: {fmtRp(r.order?.total ?? 0)}</p>
        {#if r.status === 'PENDING'}
          <div class="flex gap-2 mt-3">
            <button on:click={() => approve(r.id, 'REFUNDED')} class="btn-primary btn-sm">Refund ke Saldo</button>
            <button on:click={() => approve(r.id, 'REJECTED')} class="btn-outline btn-sm">Tolak</button>
          </div>
        {/if}
      </div>
    {/each}
  </div>
{/if}
