<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { auth, toast, confirmDialog } from '$lib/stores.svelte';
  import LoginRequired from '$lib/components/LoginRequired.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { goto } from '$app/navigation';

  let items = $state<any[]>([]);
  let loading = $state(true);
  let filter = $state<'all'|'unread'>('all');
  let meta = $state<any>({});

  async function load() {
    loading = true;
    try {
      const r: any = await apiEndpoints.notifications(filter === 'unread' ? 'unread=1' : '');
      items = r.data ?? [];
      meta = r;
    } finally { loading = false; }
  }
  onMount(() => { if (auth.user) load(); else loading = false; });

  async function markAll() {
    const ok = await confirmDialog.ask({ title: 'Tandai semua dibaca?', message: 'Semua notifikasi yang belum dibaca akan ditandai sudah dibaca.' });
    if (!ok) return;
    try { await apiEndpoints.notificationsReadAll(); toast.success('Semua ditandai sudah dibaca'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
  async function del(id: number) {
    const ok = await confirmDialog.ask({ title: 'Hapus notifikasi?', message: 'Notifikasi ini akan dihapus dari daftar Anda.', confirmText: 'Hapus', tone: 'danger' });
    if (!ok) return;
    try { await apiEndpoints.notificationDelete(id); load(); }
    catch (e: any) { toast.error(e.message); }
  }
  async function pick(n: any) {
    if (!n.read_at) {
      const ok = await confirmDialog.ask({ title: 'Buka notifikasi?', message: 'Notifikasi akan ditandai sudah dibaca lalu membuka detail.' });
      if (!ok) return;
    }
    try { await apiEndpoints.notificationMarkRead(n.id); } catch {}
    goto(`/notifications/${n.id}`);
  }

  function sevColor(sev: string) {
    return sev === 'SUCCESS' ? 'bg-emerald-100 text-emerald-700' :
           sev === 'WARNING' ? 'bg-amber-100 text-amber-700' :
           sev === 'DANGER' ? 'bg-red-100 text-red-700' :
           'bg-sky-100 text-sky-700';
  }
  function sevIcon(sev: string) {
    return sev === 'SUCCESS' ? 'check-circle' : sev === 'WARNING' ? 'alert-triangle' : sev === 'DANGER' ? 'alert-octagon' : 'info';
  }
</script>

<svelte:head><title>Notifikasi</title></svelte:head>

{#if !auth.user}
  <LoginRequired icon="bell" title="Login untuk melihat notifikasi" description="Semua aktivitas akun Anda muncul di sini setelah login." />
{:else}
  <div class="container-x py-6 sm:py-8 max-w-3xl">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-6">
      <div>
        <h1 class="section-title">Notifikasi</h1>
        <p class="text-sm text-ink-500 mt-1">Semua aktivitas akun Anda</p>
      </div>
      <div class="flex gap-2">
        <button on:click={() => filter = 'all'} class="text-xs px-3 py-1.5 rounded-full {filter === 'all' ? 'bg-app-primary text-app-pfg' : 'bg-ink-100 hover:bg-ink-200'}">Semua</button>
        <button on:click={() => filter = 'unread'} class="text-xs px-3 py-1.5 rounded-full {filter === 'unread' ? 'bg-app-primary text-app-pfg' : 'bg-ink-100 hover:bg-ink-200'}">Belum dibaca</button>
        <button on:click={markAll} class="text-xs px-3 py-1.5 rounded-full bg-ink-100 hover:bg-ink-200">Tandai semua dibaca</button>
      </div>
    </div>

    {#if loading}
      <div class="card text-center py-10 text-ink-500">Memuat…</div>
    {:else if items.length === 0}
      <div class="card text-center py-16">
        <Icon name="bell-off" size={48} class="mx-auto text-ink-300 mb-3" />
        <h3 class="font-semibold mb-1">Belum ada notifikasi</h3>
        <p class="text-sm text-ink-500">Aktivitas akun Anda akan muncul di sini.</p>
      </div>
    {:else}
      <div class="space-y-2">
        {#each items as n (n.id)}
          <div class="card !p-4 flex gap-3 {!n.read_at ? 'border-app-primary/30 bg-app-primary/[0.02]' : ''}">
            <div class="w-10 h-10 rounded-full {sevColor(n.severity)} grid place-items-center shrink-0">
              <Icon name={sevIcon(n.severity)} size={16} />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start gap-2">
                <h3 class="font-semibold text-sm flex-1">{n.title}</h3>
                {#if !n.read_at}<span class="w-2 h-2 rounded-full bg-app-primary shrink-0 mt-2"></span>{/if}
              </div>
              <p class="text-sm text-ink-700 mt-1 whitespace-pre-line">{n.message}</p>
              <div class="flex items-center gap-3 mt-2">
                <span class="text-xs text-ink-500">{new Date(n.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}</span>
                <button on:click={() => pick(n)} class="text-xs text-ink-950 hover:underline">Buka detail</button>
                <button on:click={() => del(n.id)} class="text-xs text-red-600 hover:underline ml-auto">Hapus</button>
              </div>
            </div>
          </div>
        {/each}
      </div>
    {/if}
  </div>
{/if}
