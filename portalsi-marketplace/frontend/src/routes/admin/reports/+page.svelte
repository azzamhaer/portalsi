<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let groups = $state<any[]>([]);
  let loading = $state(true);
  let mounted = $state(false);
  let loadToken = 0;
  let status = $state<'OPEN' | 'ALL' | 'RESOLVED' | 'REJECTED' | 'REVIEWING'>('OPEN');
  let cats = $state<Record<string, string>>({});

  // Modal state
  let active = $state<any>(null);
  let actAction = $state<'NONE'|'DELETE_PRODUCT'|'DEACTIVATE_PRODUCT'|'DISABLE_VENDOR'|'BAN_VENDOR'>('NONE');
  let actReason = $state('');
  let actResponse = $state('');
  let actStatus = $state<'RESOLVED'|'REJECTED'|'REVIEWING'>('RESOLVED');

  async function load() {
    const token = ++loadToken;
    loading = true;
    try {
      groups = await apiEndpoints.adminReports(`status=${status}`);
    } catch (e: any) {
      groups = [];
      toast.error(e.message || 'Gagal memuat laporan');
    } finally {
      if (token === loadToken) loading = false;
    }
  }
  onMount(async () => {
    try {
      cats = await apiEndpoints.reportCategories();
    } catch {
      cats = {};
    }
    mounted = true;
    await load();
  });
  $effect(() => { void status; if (mounted) load(); });

  function openGroup(g: any) {
    active = g;
    actAction = 'NONE';
    actReason = '';
    actResponse = '';
    actStatus = 'RESOLVED';
  }

  async function resolveAll() {
    if (!active) return;
    try {
      for (const r of active.reports) {
        await apiEndpoints.adminResolveReport(r.id, {
          status: actStatus,
          admin_response: actResponse || null,
          action: actAction,
          action_reason: actReason || null,
        });
      }
      toast.success(`${active.reports.length} laporan diproses`);
      active = null;
      load();
    } catch (e: any) { toast.error(e.message); }
  }

  const validActions = $derived.by(() => {
    if (!active) return [];
    return active.target_type === 'PRODUCT'
      ? ['NONE','DEACTIVATE_PRODUCT','DELETE_PRODUCT']
      : ['NONE','DISABLE_VENDOR','BAN_VENDOR'];
  });
  const actionLabel: Record<string, string> = {
    NONE: 'Tidak ada tindakan',
    DEACTIVATE_PRODUCT: 'Nonaktifkan produk',
    DELETE_PRODUCT: 'Hapus produk permanen',
    DISABLE_VENDOR: 'Nonaktifkan toko (DISABLED mode)',
    BAN_VENDOR: 'Ban permanen akun toko',
  };
</script>

<div class="card flex items-center gap-3 mb-4 flex-wrap">
  <h3 class="font-semibold shrink-0">Laporan</h3>
  <p class="text-xs text-ink-500 flex-1">Diurutkan berdasarkan jumlah laporan terbanyak.</p>
  <select bind:value={status} class="input-sm input w-40">
    <option value="OPEN">Belum diproses</option>
    <option value="REVIEWING">Sedang ditinjau</option>
    <option value="RESOLVED">Selesai</option>
    <option value="REJECTED">Ditolak</option>
    <option value="ALL">Semua</option>
  </select>
</div>

{#if loading}
  <div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else if groups.length === 0}
  <div class="card text-center py-16">
    <Icon name="flag" size={48} class="mx-auto text-ink-300 mb-3" />
    <h3 class="font-semibold mb-1">Tidak ada laporan</h3>
    <p class="text-sm text-ink-500">Belum ada laporan untuk filter ini.</p>
  </div>
{:else}
  <div class="space-y-3">
    {#each groups as g (g.target_type + g.target_id)}
      <div class="card flex items-center gap-4 flex-wrap">
        <div class="w-12 h-12 rounded-xl bg-red-100 text-red-700 grid place-items-center font-bold text-lg shrink-0">
          {g.count}
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="pill-{g.target_type === 'PRODUCT' ? 'blue' : 'amber'}">{g.target_type}</span>
            <span class="font-semibold">{g.target?.name ?? 'Target dihapus'}</span>
            {#if g.target_type === 'PRODUCT' && g.target?.vendor}
              <span class="text-xs text-ink-500">oleh @{g.target.vendor.username}</span>
            {/if}
          </div>
          <div class="text-xs text-ink-500 mt-1 flex flex-wrap gap-1.5">
            {#each Object.entries(g.categories) as [cat, count]}
              <span class="pill-ink !text-[10px]">{cats[cat] ?? cat}: {count}×</span>
            {/each}
          </div>
        </div>
        <button on:click={() => openGroup(g)} class="btn-primary btn-sm">Tinjau ({g.count})</button>
      </div>
    {/each}
  </div>
{/if}

{#if active}
  <div class="fixed inset-0 z-50 bg-black/60 grid place-items-center p-4 animate-fadeIn" on:click={() => active = null} role="dialog" aria-modal="true">
    <div class="bg-white rounded-2xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto" on:click|stopPropagation>
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="font-semibold text-lg">{active.target?.name ?? 'Target'}</h3>
          <p class="text-xs text-ink-500">{active.count} laporan masuk · {active.target_type}</p>
        </div>
        <button on:click={() => active = null} class="w-8 h-8 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="x" size={16} /></button>
      </div>

      <h4 class="font-semibold text-sm mb-2">Detail Pelapor</h4>
      <div class="border border-ink-100 rounded-xl divide-y divide-ink-100 mb-5 max-h-60 overflow-y-auto">
        {#each active.reports as r}
          <div class="p-3">
            <div class="flex items-start justify-between gap-2 mb-1 flex-wrap">
              <div class="text-sm">
                <a href={`/admin/users/${r.reporter?.id}`} class="font-medium hover:underline">{r.reporter?.name}</a>
                <span class="text-xs text-ink-500"> · {r.reporter?.email}</span>
              </div>
              <span class="text-[10px] text-ink-400">{new Date(r.created_at).toLocaleString('id-ID', { dateStyle:'short', timeStyle:'short' })}</span>
            </div>
            <div class="text-xs text-ink-700"><b>Kategori:</b> {cats[r.category] ?? r.category}</div>
            <div class="text-xs text-ink-600 mt-1 whitespace-pre-line">{r.description}</div>
          </div>
        {/each}
      </div>

      <div class="space-y-3 pt-3 border-t border-ink-100">
        <h4 class="font-semibold text-sm">Tindakan</h4>
        <div>
          <label class="label">Tindakan</label>
          <select bind:value={actAction} class="input">
            {#each validActions as a}<option value={a}>{actionLabel[a]}</option>{/each}
          </select>
        </div>
        {#if actAction !== 'NONE'}
          <div>
            <label class="label">Alasan tindakan (akan dilihat seller)</label>
            <textarea bind:value={actReason} class="input" rows={2} placeholder="Contoh: Produk melanggar Pasal X UU Y…"></textarea>
          </div>
        {/if}
        <div>
          <label class="label">Status laporan</label>
          <select bind:value={actStatus} class="input">
            <option value="RESOLVED">Selesai</option>
            <option value="REJECTED">Ditolak (laporan tidak valid)</option>
            <option value="REVIEWING">Masih ditinjau</option>
          </select>
        </div>
        <div>
          <label class="label">Respon untuk pelapor (opsional)</label>
          <textarea bind:value={actResponse} class="input" rows={2} placeholder="Terima kasih atas laporan Anda..."></textarea>
        </div>
        <div class="flex gap-2 pt-2">
          <button on:click={() => active = null} class="btn-outline btn-md flex-1">Batal</button>
          <button on:click={resolveAll} class="btn-primary btn-md flex-1">Proses {active.count} Laporan</button>
        </div>
      </div>
    </div>
  </div>
{/if}
