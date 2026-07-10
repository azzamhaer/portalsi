<script lang="ts">
  import { onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';
  import { fmtRp } from '$lib/utils';

  let list = $state<any>({ data: [] });
  let loading = $state(true);
  let filter = $state('');
  let selected = $state<any>(null);
  let actionStatus = $state<'APPROVED' | 'REJECTED' | 'PAID'>('PAID');
  let actionNote = $state('');

  async function load() {
    try {
      list = await apiEndpoints.adminWithdrawals(filter ? 'status='+filter : '');
    } catch (e: any) { toast.error(e.message); }
  }
  onMount(async () => { await load(); loading = false; });

  $effect(() => { void filter; if (!loading) load(); });

  async function process() {
    try {
      await apiEndpoints.adminProcessWithdraw(selected.display_id ?? selected.id, actionStatus, actionNote || undefined);
      toast.success('Status diperbarui');
      selected = null; actionNote = '';
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

<svelte:head><title>Penarikan — Admin</title></svelte:head>

<div class="space-y-5">
  <div class="card">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <h3 class="font-semibold">Permintaan Penarikan Dana</h3>
      <select bind:value={filter} class="input !py-1.5 !text-xs max-w-[150px]">
        <option value="">Semua status</option>
        <option value="PENDING">Menunggu</option>
        <option value="APPROVED">Disetujui</option>
        <option value="PAID">Dibayar</option>
        <option value="REJECTED">Ditolak</option>
      </select>
    </div>

    {#if loading}<div class="text-center py-10 text-ink-500">Memuat…</div>
    {:else if list.data.length === 0}
      <div class="text-center py-10 text-ink-500">Tidak ada permintaan.</div>
    {:else}
      <div class="overflow-x-auto -mx-4 sm:mx-0">
        <table class="w-full text-sm min-w-[700px]">
          <thead class="text-xs text-ink-500 border-b border-ink-100">
            <tr>
              <th class="text-left py-2 font-medium px-4 sm:px-0">Tanggal</th>
              <th class="text-left py-2 font-medium">Pemohon</th>
              <th class="text-left py-2 font-medium">Jumlah</th>
              <th class="text-left py-2 font-medium">Rekening Tujuan</th>
              <th class="text-left py-2 font-medium">Status</th>
              <th class="text-right py-2 font-medium px-4 sm:px-0">Aksi</th>
            </tr>
          </thead>
          <tbody>
            {#each list.data as w}
              <tr class="border-b border-ink-100 last:border-0">
                <td class="py-2.5 text-xs px-4 sm:px-0">{new Date(w.created_at).toLocaleDateString('id-ID')}</td>
                <td class="py-2.5">
                  <div class="font-medium">{w.withdrawer_type === 'USER' ? (w.user?.name ?? 'User') : (w.vendor?.name ?? 'Toko')}</div>
                  <div class="text-[11px] text-ink-500">{w.withdrawer_type === 'USER' ? 'User biasa' : 'Seller'}</div>
                </td>
                <td class="py-2.5 font-semibold">{fmtRp(w.amount)}</td>
                <td class="py-2.5 text-xs">
                  <div>{w.bank_name} • {w.bank_account}</div>
                  <div class="text-ink-500">a.n. {w.bank_holder}</div>
                </td>
                <td class="py-2.5"><span class="pill {statusPill(w.status)}">{statusLabel[w.status] ?? w.status}</span></td>
                <td class="py-2.5 text-right px-4 sm:px-0">
                  {#if w.status === 'PENDING' || w.status === 'APPROVED'}
                    <button on:click={() => { selected = w; actionStatus = w.status === 'PENDING' ? 'APPROVED' : 'PAID'; }}
                            class="text-xs px-3 py-1.5 rounded-full bg-app-primary text-app-pfg">Proses</button>
                  {/if}
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  </div>
</div>

{#if selected}
  <div class="fixed inset-0 bg-black/40 z-50 grid place-items-center p-4" on:click={() => selected = null} role="dialog" aria-modal="true">
    <div class="bg-white rounded-3xl p-6 max-w-md w-full" on:click|stopPropagation role="document">
      <h3 class="font-semibold text-lg mb-4">Proses Penarikan</h3>
      <div class="bg-ink-50 p-3 rounded-xl mb-4 text-sm">
        <div class="font-semibold">{selected.withdrawer_type === 'USER' ? (selected.user?.name ?? 'User') : (selected.vendor?.name ?? 'Toko')}</div>
        <div class="text-[11px] text-ink-500">{selected.withdrawer_type === 'USER' ? 'Penarikan saldo user' : 'Penarikan saldo seller'}</div>
        <div class="text-xs text-ink-500">{selected.bank_name} • {selected.bank_account} ({selected.bank_holder})</div>
        <div class="text-lg font-bold mt-2">{fmtRp(selected.amount)}</div>
      </div>
      <div class="space-y-3">
        <div>
          <label class="label">Status baru</label>
          <select bind:value={actionStatus} class="input">
            <option value="APPROVED">Disetujui (siap transfer)</option>
            <option value="PAID">Dibayar (sudah ditransfer)</option>
            <option value="REJECTED">Ditolak</option>
          </select>
        </div>
        <div>
          <label class="label">Catatan (opsional)</label>
          <textarea bind:value={actionNote} class="input" rows={2} placeholder="Contoh: ditransfer 14:30 via BCA"></textarea>
        </div>
        <div class="flex gap-2 pt-2">
          <button on:click={() => selected = null} class="btn-outline btn-md flex-1">Batal</button>
          <button on:click={process} class="btn-primary btn-md flex-1">Simpan</button>
        </div>
      </div>
    </div>
  </div>
{/if}
