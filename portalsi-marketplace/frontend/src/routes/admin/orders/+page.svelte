<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { fmtRp, statusPill, ORDER_STATUS_LABEL } from '$lib/utils';
  import { toast } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let orders = $state<any[]>([]);
  let meta = $state<any>({ current_page: 1, last_page: 1, total: 0 });
  let loading = $state(true);
  let status = $state('');
  let search = $state('');
  let page = $state(1);
  let searchTimer: any;

  async function load() {
    loading = true;
    try {
      const params = new URLSearchParams();
      if (status) params.set('status', status);
      if (search.trim()) params.set('search', search.trim());
      params.set('page', String(page));
      const r: any = await apiEndpoints.adminOrders(params.toString());
      orders = r.data ?? [];
      meta = { current_page: r.current_page, last_page: r.last_page, total: r.total };
    } finally { loading = false; }
  }
  onMount(load);

  function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { page = 1; load(); }, 300);
  }
  function setPage(p: number) { page = p; load(); }

  async function changeStatus(o: any, newStatus: string) {
    try { await apiEndpoints.adminUpdateOrder(o.id, { status: newStatus }); toast.success('Status diubah'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
</script>

<div class="card flex items-center gap-3 mb-4 flex-wrap">
  <h3 class="font-semibold shrink-0">Semua Pesanan</h3>
  <div class="flex items-center gap-2 flex-1 min-w-[200px] bg-ink-50 rounded-full px-3">
    <Icon name="search" size={14} class="text-ink-400" />
    <input bind:value={search} on:input={onSearchInput} class="flex-1 bg-transparent text-sm py-2 outline-none" placeholder="Cari no order, no resi (JNE/JNT/dll), nama, email pembeli" />
    {#if search}
      <button on:click={() => { search = ''; page = 1; load(); }} class="text-ink-400 hover:text-ink-700"><Icon name="x" size={14} /></button>
    {/if}
  </div>
  <select bind:value={status} on:change={() => { page = 1; load(); }} class="input-sm input w-48">
    <option value="">Semua status</option>
    {#each Object.entries(ORDER_STATUS_LABEL) as [k, v]}<option value={k}>{v}</option>{/each}
  </select>
</div>

{#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <div class="card overflow-x-auto">
    <p class="text-xs text-ink-500 mb-3">{meta.total} pesanan · halaman {meta.current_page} dari {meta.last_page}</p>
    <table class="w-full text-sm min-w-[700px]">
      <thead class="text-xs text-ink-500 border-b border-ink-100">
        <tr><th class="text-left py-2">Order</th><th class="text-left py-2">Resi</th><th class="text-left py-2">Pembeli</th><th class="text-left py-2">Total</th><th class="text-left py-2">Status</th><th class="text-left py-2">Aksi</th></tr>
      </thead>
      <tbody>
        {#each orders as o (o.id)}
          <tr class="border-b border-ink-100 last:border-0">
            <td class="py-2"><b class="font-mono text-xs">{o.order_number}</b><div class="text-[11px] text-ink-500">{new Date(o.created_at).toLocaleDateString('id-ID')}</div></td>
            <td class="py-2 font-mono text-xs">{o.tracking_no ?? '—'}</td>
            <td class="py-2">{o.user?.name}<div class="text-[11px] text-ink-500">{o.address?.city}</div></td>
            <td class="py-2 font-semibold">{fmtRp(o.total)}</td>
            <td class="py-2"><span class="pill {statusPill(o.status)}">{ORDER_STATUS_LABEL[o.status]}</span></td>
            <td class="py-2">
              <div class="flex items-center gap-2">
                <select on:change={(e: any) => changeStatus(o, e.target.value)} value={o.status} class="text-xs border border-ink-200 rounded px-2 py-1">
                  {#each Object.entries(ORDER_STATUS_LABEL) as [k, v]}<option value={k}>{v}</option>{/each}
                </select>
                <a href={`/admin/orders/${o.id}`} class="text-xs px-2.5 py-1 rounded-full bg-ink-100 hover:bg-ink-200 inline-flex items-center gap-1"><Icon name="eye" size={12} /> Detail</a>
              </div>
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>
  {#if meta.last_page > 1}
    <div class="mt-4 flex justify-center gap-1">
      <button on:click={() => setPage(Math.max(1, page - 1))} disabled={page === 1} class="px-3 py-1.5 rounded-full text-sm bg-ink-100 hover:bg-ink-200 disabled:opacity-40">‹</button>
      <span class="px-3 py-1.5 text-sm">{page} / {meta.last_page}</span>
      <button on:click={() => setPage(Math.min(meta.last_page, page + 1))} disabled={page >= meta.last_page} class="px-3 py-1.5 rounded-full text-sm bg-ink-100 hover:bg-ink-200 disabled:opacity-40">›</button>
    </div>
  {/if}
{/if}
