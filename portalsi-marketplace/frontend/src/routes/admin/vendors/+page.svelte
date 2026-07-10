<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, confirmDialog } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import VendorBadge from '$lib/components/VendorBadge.svelte';
  import { page as pageStore } from '$app/stores';

  let vendors = $state<any[]>([]);
  let meta = $state<any>({ current_page: 1, last_page: 1, total: 0 });
  let loading = $state(true);
  let status = $state($pageStore.url.searchParams.get('status') ?? '');
  let search = $state('');
  let page = $state(1);
  let searchTimer: any;
  let active = $state<any>(null);

  async function load() {
    loading = true;
    try {
      const params = new URLSearchParams();
      if (status) params.set('status', status);
      if (search.trim()) params.set('search', search.trim());
      params.set('page', String(page));
      const r: any = await apiEndpoints.adminVendors(params.toString());
      vendors = r.data ?? [];
      meta = { current_page: r.current_page, last_page: r.last_page, total: r.total };
    } finally { loading = false; }
  }
  onMount(load);

  function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { page = 1; load(); }, 300);
  }
  function setPage(p: number) { page = p; load(); }

  async function verify(v: any, st: 'APPROVED'|'REJECTED') {
    const note = st === 'REJECTED' ? prompt('Alasan penolakan:') : '';
    if (st === 'REJECTED' && !note) return;
    const ok = await confirmDialog.ask({
      title: st === 'APPROVED' ? 'Approve vendor?' : 'Tolak vendor?',
      message: st === 'APPROVED' ? `Toko "${v.name}" akan bisa mengakses semua fitur seller.` : `Pendaftaran toko "${v.name}" akan ditolak.`,
      confirmText: st === 'APPROVED' ? 'Approve' : 'Tolak',
      tone: st === 'APPROVED' ? 'default' : 'danger'
    });
    if (!ok) return;
    try {
      await apiEndpoints.adminVerifyVendor(v.id, st, note ?? '');
      toast.success('Status diperbarui');
      active = null;
      window.dispatchEvent(new CustomEvent('admin:pending-vendors-changed'));
      load();
    }
    catch (e: any) { toast.error(e.message); }
  }
  async function del(v: any) {
    const ok = await confirmDialog.ask({ title: 'Hapus toko?', message: `Toko "${v.name}" akan dihapus.`, confirmText: 'Hapus', tone: 'danger' });
    if (!ok) return;
    try { await apiEndpoints.adminDeleteVendor(v.id); toast.success('Dihapus'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
  async function setBadge(v: any, badge: string | null) {
    try {
      await apiEndpoints.adminSetVendorBadge(v.id, badge);
      v.badge = badge;
      toast.success(badge ? `Badge "${badge}" diberikan` : 'Badge dilepas');
      load();
    } catch (e: any) { toast.error(e.message); }
  }
  let modVendor = $state<any>(null);
  let modMode = $state<'NONE'|'LIMITED'|'DISABLED'>('NONE');
  let modWarning = $state('');
  function openModerationModal(v: any) {
    modVendor = v;
    modMode = (v.moderation_mode ?? 'NONE') as any;
    modWarning = v.admin_warning ?? '';
  }
  async function saveModeration() {
    try {
      await apiEndpoints.adminSetVendorModeration(modVendor.id, modMode, modWarning.trim() || undefined);
      toast.success('Moderasi diperbarui');
      modVendor = null;
      load();
    } catch (e: any) { toast.error(e.message); }
  }
</script>

<div class="card flex items-center gap-3 mb-4 flex-wrap">
  <h3 class="font-semibold shrink-0">Vendor, Verifikasi & Badge</h3>
  <div class="flex items-center gap-2 flex-1 min-w-[200px] bg-ink-50 rounded-full px-3">
    <Icon name="search" size={14} class="text-ink-400" />
    <input bind:value={search} on:input={onSearchInput} class="flex-1 bg-transparent text-sm py-2 outline-none" placeholder="Cari nama toko, username, kota, email/HP pemilik" />
    {#if search}
      <button on:click={() => { search = ''; page = 1; load(); }} class="text-ink-400 hover:text-ink-700"><Icon name="x" size={14} /></button>
    {/if}
  </div>
  <select bind:value={status} on:change={() => { page = 1; load(); }} class="input-sm input w-40">
    <option value="">Semua status</option>
    <option value="PENDING">Pending</option>
    <option value="APPROVED">Approved</option>
    <option value="REJECTED">Rejected</option>
  </select>
</div>

{#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <p class="text-xs text-ink-500 mb-3">{meta.total} vendor · halaman {meta.current_page} dari {meta.last_page}</p>
  <div class="grid sm:grid-cols-2 gap-4">
    {#each vendors as v (v.id)}
      <div class="card">
        <div class="flex items-center gap-3 mb-3">
          <img src={v.avatar} alt="" class="w-12 h-12 rounded-full object-cover" />
          <div class="flex-1 min-w-0">
            <div class="font-semibold truncate flex items-center gap-1.5">
              {v.name}
              {#if v.badge}<VendorBadge badge={v.badge} size={14} />{/if}
            </div>
            <div class="text-xs text-ink-500 truncate">@{v.username} · {v.user?.email}</div>
          </div>
          <span class="pill-{v.verification_status === 'APPROVED' ? 'green' : v.verification_status === 'PENDING' ? 'amber' : 'red'}">{v.verification_status}</span>
        </div>
        <div class="text-xs text-ink-500 mb-2 flex items-center gap-1 flex-wrap">
          <Icon name="map-pin" size={11} /> {v.city}
          <span class="mx-1">·</span>
          {#if v.rating > 0}<Icon name="star" size={11} class="text-amber-400" fill="currentColor" /> {v.rating}{:else}<span class="text-ink-400">No rating</span>{/if}
          <span class="mx-1">·</span>
          {v.total_sold} terjual
          <span class="mx-1">·</span>
          <Icon name="users" size={11} /> {v.followers ?? 0}
        </div>

        {#if v.verification_status === 'APPROVED'}
          <div class="mt-2 mb-2 flex items-center gap-1.5 flex-wrap text-xs">
            <span class="text-ink-500">Badge:</span>
            <button on:click={() => setBadge(v, null)}      class="px-2 py-0.5 rounded-full border {!v.badge ? 'bg-app-primary text-app-pfg' : 'bg-ink-50 border-ink-200'}">Tidak ada</button>
            <button on:click={() => setBadge(v, 'VERIFIED')} class="px-2 py-0.5 rounded-full border {v.badge==='VERIFIED' ? 'bg-sky-500 text-white border-sky-500' : 'bg-sky-50 text-sky-700 border-sky-200'}">Verified</button>
            <button on:click={() => setBadge(v, 'MALL')}     class="px-2 py-0.5 rounded-full border {v.badge==='MALL' ? 'bg-purple-600 text-white border-purple-600' : 'bg-purple-50 text-purple-700 border-purple-200'}">Mall</button>
            <button on:click={() => setBadge(v, 'STAR')}     class="px-2 py-0.5 rounded-full border {v.badge==='STAR' ? 'bg-amber-500 text-white border-amber-500' : 'bg-amber-50 text-amber-700 border-amber-200'}">Star</button>
          </div>
        {/if}

        {#if v.moderation_mode && v.moderation_mode !== 'NONE'}
          <div class="mt-2 text-xs flex items-center gap-1.5 flex-wrap">
            <span class="pill-{v.moderation_mode === 'DISABLED' ? 'red' : 'amber'}">
              <Icon name="shield-alert" size={10} /> {v.moderation_mode === 'DISABLED' ? 'Tersembunyi total' : 'Toko dibatasi'}
            </span>
          </div>
        {/if}

        <div class="flex gap-2 mt-3 flex-wrap">
          <button on:click={() => active = v} class="btn-outline btn-sm"><Icon name="id-card" size={12} /> Lihat KTP</button>
          {#if v.verification_status !== 'APPROVED'}<button on:click={() => verify(v, 'APPROVED')} class="btn-primary btn-sm"><Icon name="check" size={12} /> Approve</button>{/if}
          {#if v.verification_status !== 'REJECTED'}<button on:click={() => verify(v, 'REJECTED')} class="btn-sm btn bg-amber-100 text-amber-800 hover:bg-amber-200"><Icon name="x" size={12} /> Tolak</button>{/if}
          {#if v.verification_status === 'APPROVED'}
            <button on:click={() => openModerationModal(v)} class="btn-sm btn bg-purple-50 text-purple-700 hover:bg-purple-100"><Icon name="shield-alert" size={12} /> Moderasi</button>
          {/if}
          <button on:click={() => del(v)} class="btn-sm btn bg-red-50 text-red-700 hover:bg-red-100"><Icon name="trash-2" size={12} /></button>
        </div>
      </div>
    {/each}
  </div>
  {#if meta.last_page > 1}
    <div class="mt-4 flex justify-center gap-1">
      <button on:click={() => setPage(Math.max(1, page - 1))} disabled={page === 1} class="px-3 py-1.5 rounded-full text-sm bg-ink-100 hover:bg-ink-200 disabled:opacity-40">‹</button>
      <span class="px-3 py-1.5 text-sm">{page} / {meta.last_page}</span>
      <button on:click={() => setPage(Math.min(meta.last_page, page + 1))} disabled={page >= meta.last_page} class="px-3 py-1.5 rounded-full text-sm bg-ink-100 hover:bg-ink-200 disabled:opacity-40">›</button>
    </div>
  {/if}
{/if}

{#if active}
  <div class="fixed inset-0 z-50 bg-black/60 grid place-items-center p-4 animate-fadeIn" on:click={() => active = null} role="dialog">
    <div class="bg-white rounded-2xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto" on:click|stopPropagation>
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold">Verifikasi KTP — {active.name}</h3>
        <button on:click={() => active = null} class="w-8 h-8 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="x" size={16} /></button>
      </div>
      {#if active.ktp_image}
        <img src={active.ktp_image} alt="KTP" class="w-full rounded-xl border border-ink-200 max-h-96 object-contain bg-ink-50" />
      {:else}
        <div class="card text-center py-10 text-ink-500">KTP belum diupload.</div>
      {/if}
      <div class="mt-4 text-sm space-y-1">
        <div><b>Nama Toko:</b> {active.name}</div>
        <div><b>Pemilik:</b> {active.user?.name} ({active.user?.email})</div>
        <div><b>Bank:</b> {active.bank_name} — {active.bank_account} ({active.bank_holder})</div>
        <div><b>Deskripsi:</b> {active.description}</div>
      </div>
      <div class="flex gap-2 mt-5">
        <button on:click={() => verify(active, 'APPROVED')} class="btn-primary btn-md flex-1"><Icon name="check" size={14} /> Approve</button>
        <button on:click={() => verify(active, 'REJECTED')} class="btn-outline btn-md flex-1"><Icon name="x" size={14} /> Tolak</button>
      </div>
    </div>
  </div>
{/if}

{#if modVendor}
  <div class="fixed inset-0 z-50 bg-black/60 grid place-items-center p-4 animate-fadeIn" on:click={() => modVendor = null} role="dialog">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full" on:click|stopPropagation>
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold">Moderasi Toko — {modVendor.name}</h3>
        <button on:click={() => modVendor = null} class="w-8 h-8 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="x" size={16} /></button>
      </div>
      <div class="space-y-3">
        <div>
          <label class="label">Mode Moderasi</label>
          <div class="grid gap-2">
            <label class="flex items-start gap-2 p-3 rounded-xl border border-ink-200 cursor-pointer" class:bg-ink-50={modMode === 'NONE'}>
              <input type="radio" bind:group={modMode} value="NONE" class="mt-1" />
              <div>
                <div class="font-semibold text-sm">Normal</div>
                <div class="text-xs text-ink-500">Toko aktif tanpa pembatasan.</div>
              </div>
            </label>
            <label class="flex items-start gap-2 p-3 rounded-xl border border-amber-200 cursor-pointer" class:bg-amber-50={modMode === 'LIMITED'}>
              <input type="radio" bind:group={modMode} value="LIMITED" class="mt-1" />
              <div>
                <div class="font-semibold text-sm text-amber-700">Dibatasi (penalti ringan)</div>
                <div class="text-xs text-ink-500">Toko & produk masih bisa dilihat, tapi tidak bisa dipesan & chat ditolak. Penjelasan pelanggaran tampil sebagai badge.</div>
              </div>
            </label>
            <label class="flex items-start gap-2 p-3 rounded-xl border border-red-200 cursor-pointer" class:bg-red-50={modMode === 'DISABLED'}>
              <input type="radio" bind:group={modMode} value="DISABLED" class="mt-1" />
              <div>
                <div class="font-semibold text-sm text-red-700">Tersembunyi total (penalti berat)</div>
                <div class="text-xs text-ink-500">Toko & produk tidak tampil di listing, search, atau detail page. Vendor tetap bisa login & lihat dashboardnya.</div>
              </div>
            </label>
          </div>
        </div>
        <div>
          <label class="label">Pesan peringatan untuk vendor</label>
          <textarea bind:value={modWarning} class="input" rows={4} placeholder="Contoh: Toko Anda terdeteksi menjual produk yang melanggar kebijakan kategori X. Mohon segera periksa dan revisi listing Anda dalam 3×24 jam..."></textarea>
          <p class="helper">Pesan ini akan tampil sebagai popup di dashboard vendor saat login. Kosongkan untuk hapus peringatan.</p>
        </div>
        <div class="flex gap-2 pt-2">
          <button on:click={() => modVendor = null} class="btn-outline btn-md flex-1">Batal</button>
          <button on:click={saveModeration} class="btn-primary btn-md flex-1">Simpan</button>
        </div>
      </div>
    </div>
  </div>
{/if}
