<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { auth, toast } from '$lib/stores.svelte';
  import LoginRequired from '$lib/components/LoginRequired.svelte';
  import AdminBlock from '$lib/components/AdminBlock.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp } from '$lib/utils';

  let items = $state<any[]>([]);
  let summary = $state<any>({ count: 0, total_refunded: 0, total_pending: 0 });
  let loading = $state(true);
  let expanded = $state<string | null>(null);
  let filter = $state<'ALL' | 'REFUNDED' | 'PENDING' | 'REJECTED'>('ALL');

  async function load() {
    loading = true;
    try {
      const r: any = await apiEndpoints.refunds();
      items = r.data ?? [];
      summary = r.summary ?? {};
    } catch (e: any) { toast.error(e.message); } finally { loading = false; }
  }
  onMount(() => { if (auth.user && auth.user.role !== 'ADMIN') load(); else loading = false; });

  const filtered = $derived.by(() => {
    if (filter === 'ALL') return items;
    if (filter === 'PENDING') return items.filter((x) => ['PENDING', 'APPROVED'].includes(x.status));
    return items.filter((x) => x.status === filter);
  });

  function statusPill(status: string) {
    return status === 'REFUNDED' ? 'bg-emerald-100 text-emerald-700'
         : status === 'APPROVED' ? 'bg-sky-100 text-sky-700'
         : status === 'PENDING'  ? 'bg-amber-100 text-amber-700'
         : status === 'REJECTED' ? 'bg-red-100 text-red-700'
         : 'bg-ink-100 text-ink-700';
  }
  function typeIcon(type: string) {
    return type === 'RETURN' ? 'undo-2' : 'x-circle';
  }
</script>

<svelte:head><title>Riwayat Refund</title></svelte:head>

{#if !auth.user}
  <LoginRequired icon="wallet" title="Login untuk melihat refund" description="Riwayat pengembalian dana akan muncul setelah Anda login." />
{:else if auth.user.role === 'ADMIN'}
  <AdminBlock title="Admin tidak punya riwayat refund" description="Buka /admin/returns untuk mengelola pengajuan return." />
{:else}
  <div class="container-x py-6 sm:py-8 max-w-4xl">
    <div class="mb-6 flex items-end justify-between gap-3 flex-wrap">
      <div>
        <h1 class="section-title">Riwayat Refund</h1>
        <p class="text-sm text-ink-500 mt-1">Daftar pengembalian dana dari pesanan yang dibatalkan atau di-return.</p>
      </div>
      <a href="/profile" class="text-xs text-ink-500 hover:text-ink-950 flex items-center gap-1"><Icon name="arrow-left" size={12} /> Kembali ke profil</a>
    </div>

    <!-- Summary cards -->
    <div class="grid sm:grid-cols-3 gap-3 mb-5">
      <div class="card">
        <div class="text-xs uppercase tracking-widest text-ink-500">Total kasus</div>
        <div class="font-display text-xl font-bold tracking-tightest mt-2">{summary.count ?? 0}</div>
      </div>
      <div class="card bg-emerald-50 border-emerald-100">
        <div class="text-xs uppercase tracking-widest text-emerald-700">Dana dikembalikan</div>
        <div class="font-display text-xl font-bold tracking-tightest mt-2 text-emerald-700">{fmtRp(summary.total_refunded ?? 0)}</div>
      </div>
      <div class="card bg-amber-50 border-amber-100">
        <div class="text-xs uppercase tracking-widest text-amber-700">Sedang diproses</div>
        <div class="font-display text-xl font-bold tracking-tightest mt-2 text-amber-700">{fmtRp(summary.total_pending ?? 0)}</div>
      </div>
    </div>

    <!-- Filter chips -->
    <div class="flex gap-2 mb-4 flex-wrap">
      {#each [['ALL','Semua'],['REFUNDED','Sudah dikembalikan'],['PENDING','Diproses'],['REJECTED','Ditolak']] as [k, l]}
        <button on:click={() => filter = k as any}
                class="text-xs px-3 py-1.5 rounded-full transition
                       {filter === k ? 'bg-app-primary text-app-pfg' : 'bg-ink-100 hover:bg-ink-200'}">
          {l}
        </button>
      {/each}
    </div>

    {#if loading}
      <div class="card text-center text-ink-500 py-10">Memuat…</div>
    {:else if filtered.length === 0}
      <div class="card text-center py-16">
        <Icon name="wallet" size={48} class="mx-auto text-ink-300 mb-3" />
        <h3 class="font-semibold mb-1">Belum ada refund</h3>
        <p class="text-sm text-ink-500">Refund akan muncul di sini jika ada pesanan yang dibatalkan setelah dibayar atau di-return.</p>
      </div>
    {:else}
      <div class="space-y-3">
        {#each filtered as it (it.id)}
          <div class="card">
            <div class="flex items-start gap-3 flex-wrap">
              <div class="w-10 h-10 rounded-xl bg-ink-100 grid place-items-center shrink-0">
                <Icon name={typeIcon(it.type)} size={18} class="text-ink-700" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-sm">{it.type_label}</span>
                  <span class="pill {statusPill(it.status)}">{it.status_label}</span>
                </div>
                <div class="text-xs text-ink-500 mt-1">
                  <a href={`/orders/${it.order_id}`} class="font-mono hover:text-ink-950 hover:underline">{it.order_number}</a>
                  · {new Date(it.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}
                </div>
                {#if it.reason}
                  <p class="text-xs text-ink-600 mt-2 whitespace-pre-line">{it.reason}</p>
                {/if}
              </div>
              <div class="text-right shrink-0">
                <div class="font-display text-lg font-bold tracking-tightest">{fmtRp(it.amount)}</div>
                {#if it.payment_method}<div class="text-[10px] text-ink-500">via {it.payment_method}</div>{/if}
              </div>
            </div>

            {#if it.items?.length || it.admin_note}
              <button type="button" on:click={() => expanded = expanded === it.id ? null : it.id}
                      class="mt-3 text-xs text-ink-500 hover:text-ink-950 inline-flex items-center gap-1">
                <Icon name={expanded === it.id ? 'chevron-up' : 'chevron-down'} size={12} />
                {expanded === it.id ? 'Sembunyikan' : 'Lihat detail'}
              </button>

              {#if expanded === it.id}
                <div class="mt-3 pt-3 border-t border-ink-100 space-y-3">
                  {#if it.items?.length}
                    <div>
                      <h4 class="text-xs uppercase tracking-widest text-ink-500 mb-2">Item</h4>
                      <ul class="space-y-1.5">
                        {#each it.items as x}
                          <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-ink-700">{x.name} <span class="text-ink-400">× {x.quantity}</span></span>
                            <span class="font-medium">{fmtRp(x.subtotal)}</span>
                          </li>
                        {/each}
                      </ul>
                    </div>
                  {/if}
                  {#if it.admin_note}
                    <div class="bg-ink-50 p-3 rounded-xl">
                      <div class="text-xs text-ink-500 mb-1">Catatan admin</div>
                      <div class="text-sm text-ink-700 whitespace-pre-line">{it.admin_note}</div>
                    </div>
                  {/if}
                </div>
              {/if}
            {/if}
          </div>
        {/each}
      </div>
    {/if}

    <div class="mt-6 text-xs text-ink-500 bg-ink-50 rounded-2xl p-4">
      <div class="flex items-start gap-2">
        <Icon name="info" size={14} class="mt-0.5 shrink-0" />
        <div>
          Refund untuk pesanan yang dibayar via Virtual Account/e-wallet biasanya kembali ke rekening/dompet asal dalam <b>3–14 hari kerja</b>.
          Untuk QRIS/kartu kredit, mengikuti kebijakan bank/penerbit. Hubungi tim CS jika dana belum diterima setelah 14 hari.
        </div>
      </div>
    </div>
  </div>
{/if}
