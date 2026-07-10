<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { goto } from '$app/navigation';

  let { data } = $props();
  const n = $derived(data.notification);

  function sevClass(sev: string) {
    return sev === 'SUCCESS' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' :
           sev === 'WARNING' ? 'bg-amber-100 text-amber-700 border-amber-200' :
           sev === 'DANGER' ? 'bg-red-100 text-red-700 border-red-200' :
           'bg-sky-100 text-sky-700 border-sky-200';
  }

  function sevIcon(sev: string) {
    return sev === 'SUCCESS' ? 'check-circle' :
           sev === 'WARNING' ? 'alert-triangle' :
           sev === 'DANGER' ? 'alert-octagon' : 'info';
  }

  function typeLabel(type: string) {
    const labels: Record<string, string> = {
      REPORT_RESPONSE: 'Laporan',
      PRODUCT_ACTION: 'Moderasi produk',
      VENDOR_ACTION: 'Moderasi toko',
      VENDOR_VERIFICATION: 'Verifikasi toko',
      VENDOR_PENDING_APPROVAL: 'Approval vendor',
      PASSWORD_CHANGED: 'Keamanan akun',
      EMAIL_CHANGED: 'Keamanan akun'
    };
    if (type.startsWith('ORDER_')) return 'Pesanan';
    if (type.startsWith('WITHDRAW_')) return 'Penarikan dana';
    return labels[type] || type.replaceAll('_', ' ');
  }

  function dateTime(value: string | null | undefined) {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
  }

  function go(url: string) {
    if (!url) return;
    goto(url);
  }
</script>

<svelte:head><title>{n.title}</title></svelte:head>

<div class="container-x py-6 sm:py-8 max-w-4xl">
  <button type="button" on:click={() => goto('/notifications')} class="mb-4 inline-flex items-center gap-2 text-sm text-ink-500 hover:text-ink-950">
    <Icon name="arrow-left" size={16} />
    Kembali ke notifikasi
  </button>

  <div class="card !p-0 overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-ink-100">
      <div class="flex items-start gap-4">
        <div class="h-12 w-12 rounded-2xl border {sevClass(n.severity)} grid place-items-center shrink-0">
          <Icon name={sevIcon(n.severity)} size={22} />
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2 mb-2">
            <span class="rounded-full bg-ink-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-ink-500">{typeLabel(n.type)}</span>
            <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-widest {sevClass(n.severity)}">{n.severity}</span>
          </div>
          <h1 class="font-display text-2xl sm:text-3xl font-bold tracking-tightest">{n.title}</h1>
          <p class="mt-2 text-sm text-ink-500">{dateTime(n.created_at)}</p>
        </div>
      </div>
    </div>

    <div class="p-5 sm:p-6 grid lg:grid-cols-[1fr_280px] gap-6">
      <div class="space-y-5 min-w-0">
        <section>
          <h2 class="text-sm font-semibold mb-2">Isi notifikasi</h2>
          <div class="rounded-2xl bg-ink-50 p-4 text-sm leading-relaxed text-ink-700 whitespace-pre-line">{n.message}</div>
        </section>

        {#if n.facts?.length}
          <section>
            <h2 class="text-sm font-semibold mb-2">Detail</h2>
            <div class="grid sm:grid-cols-2 gap-2">
              {#each n.facts as fact}
                <div class="rounded-2xl border border-ink-100 p-3">
                  <div class="text-[11px] uppercase tracking-widest text-ink-400">{fact.label}</div>
                  <div class="mt-1 text-sm font-medium text-ink-800 whitespace-pre-line break-words">{fact.value}</div>
                </div>
              {/each}
            </div>
          </section>
        {/if}

        {#if n.context?.report}
          <section>
            <h2 class="text-sm font-semibold mb-2">Rincian laporan</h2>
            <div class="rounded-2xl border border-ink-100 p-4 space-y-3">
              <div>
                <div class="text-[11px] uppercase tracking-widest text-ink-400">Laporan Anda</div>
                <p class="text-sm text-ink-700 whitespace-pre-line">{n.context.report.description}</p>
              </div>
              {#if n.context.report.admin_response}
                <div>
                  <div class="text-[11px] uppercase tracking-widest text-ink-400">Jawaban admin</div>
                  <p class="text-sm text-ink-700 whitespace-pre-line">{n.context.report.admin_response}</p>
                </div>
              {/if}
              {#if n.context.report.target}
                <div class="rounded-xl bg-ink-50 p-3">
                  <div class="text-[11px] uppercase tracking-widest text-ink-400">Objek laporan</div>
                  <div class="text-sm font-semibold">{n.context.report.target.name}</div>
                  <div class="text-xs text-ink-500">{n.context.report.target.type} #{n.context.report.target.id}</div>
                </div>
              {/if}
            </div>
          </section>
        {/if}
      </div>

      <aside class="space-y-3">
        <div class="rounded-2xl border border-ink-100 p-4">
          <h2 class="text-sm font-semibold mb-3">Aksi</h2>
          {#if n.actions?.length}
            <div class="space-y-2">
              {#each n.actions as action, i}
                <button type="button" on:click={() => go(action.url)} class="{i === 0 ? 'btn-primary' : 'btn-outline'} btn-md w-full justify-center">
                  <Icon name={action.icon || 'arrow-up-right'} size={15} />
                  {action.label}
                </button>
              {/each}
            </div>
          {:else}
            <div class="text-sm text-ink-500">Tidak ada aksi lanjutan untuk notifikasi ini.</div>
          {/if}
        </div>

        <div class="rounded-2xl border border-ink-100 p-4 text-sm">
          <div class="flex items-center justify-between py-2 border-b border-ink-100">
            <span class="text-ink-500">Status baca</span>
            <span class="font-medium">{n.read_at ? 'Sudah dibaca' : 'Belum dibaca'}</span>
          </div>
          <div class="flex items-center justify-between py-2">
            <span class="text-ink-500">ID notifikasi</span>
            <span class="font-mono text-xs">#{n.id}</span>
          </div>
        </div>
      </aside>
    </div>
  </div>
</div>
