<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Pagination from '$lib/components/Pagination.svelte';

  let users = $state<any[]>([]);
  let meta = $state<any>({ current_page: 1, last_page: 1, total: 0 });
  let loading = $state(true);
  let search = $state('');
  let role = $state('');
  let page = $state(1);
  let searchTimer: any;

  async function load() {
    loading = true;
    try {
      const params = new URLSearchParams();
      if (search.trim()) params.set('search', search.trim());
      if (role) params.set('role', role);
      params.set('page', String(page));
      const r: any = await apiEndpoints.adminUsers(params.toString());
      users = r.data ?? [];
      meta = { current_page: r.current_page, last_page: r.last_page, total: r.total };
    } finally { loading = false; }
  }
  onMount(load);

  function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { page = 1; load(); }, 300);
  }
  function setPage(p: number) { page = p; load(); }

  async function del(u: any) {
    if (!confirm(`Hapus user "${u.name}"?`)) return;
    try { await apiEndpoints.adminDeleteUser(u.id); toast.success('Dihapus'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
</script>

<div class="card flex items-center gap-3 mb-4 flex-wrap">
  <h3 class="font-semibold shrink-0">Users</h3>
  <div class="flex items-center gap-2 flex-1 min-w-[200px] bg-ink-50 rounded-full px-3">
    <Icon name="search" size={14} class="text-ink-400" />
    <input bind:value={search} on:input={onSearchInput} class="flex-1 bg-transparent text-sm py-2 outline-none" placeholder="Cari nama, email, no HP, atau username" />
    {#if search}
      <button on:click={() => { search = ''; page = 1; load(); }} class="text-ink-400 hover:text-ink-700"><Icon name="x" size={14} /></button>
    {/if}
  </div>
  <select bind:value={role} on:change={() => { page = 1; load(); }} class="input-sm input w-36">
    <option value="">Semua role</option>
    <option value="BUYER">Buyer</option>
    <option value="SELLER">Seller</option>
    <option value="ADMIN">Admin</option>
  </select>
</div>

<div class="card mb-4 bg-amber-50 text-amber-800 text-xs p-3 rounded-xl flex items-start gap-2">
  <Icon name="info" size={14} class="mt-0.5 shrink-0" />
  <div>
    Role pengguna ditentukan dari permintaan mereka — <b>SELLER</b> diberikan otomatis setelah Anda meng-approve pengajuan toko di tab <a href="/admin/vendors" class="underline font-semibold">Vendor & KTP</a>. Untuk membuat user lain menjadi <b>ADMIN</b>, ubah langsung di database (phpMyAdmin) demi keamanan.
  </div>
</div>

{#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <div class="card overflow-x-auto">
    <p class="text-xs text-ink-500 mb-3">{meta.total} user · halaman {meta.current_page} dari {meta.last_page}</p>
    <table class="w-full text-sm min-w-[600px]">
      <thead class="text-xs text-ink-500 border-b border-ink-100">
        <tr><th class="text-left py-2">User</th><th class="text-left py-2">Role</th><th class="text-left py-2">Toko</th><th class="text-left py-2">Daftar</th><th class="text-right py-2">Aksi</th></tr>
      </thead>
      <tbody>
        {#each users as u (u.id)}
          <tr class="border-b border-ink-100 last:border-0 hover:bg-ink-50">
            <td class="py-2">
              <a href={`/admin/users/${u.id}`} class="hover:underline">
                <div class="font-medium">{u.name}</div>
                <div class="text-xs text-ink-500">{u.email}{#if u.phone} · {u.phone}{/if}</div>
              </a>
            </td>
            <td class="py-2"><span class="pill-{u.role === 'ADMIN' ? 'red' : u.role === 'SELLER' ? 'blue' : 'ink'}">{u.role}</span></td>
            <td class="py-2 text-xs">
              {#if u.vendor}
                @{u.vendor.username} <span class="pill-{u.vendor.verification_status === 'APPROVED' ? 'green' : u.vendor.verification_status === 'PENDING' ? 'amber' : 'red'} text-[10px] ml-1">{u.vendor.verification_status}</span>
              {:else}<span class="text-ink-400">—</span>{/if}
            </td>
            <td class="py-2 text-xs text-ink-500">{new Date(u.created_at).toLocaleDateString('id-ID')}</td>
            <td class="py-2 text-right">
              <div class="flex gap-1 justify-end">
                <a href={`/admin/users/${u.id}`} class="text-xs px-2.5 py-1 rounded-full bg-ink-100 hover:bg-ink-200 inline-flex items-center gap-1"><Icon name="eye" size={12} /> Detail</a>
                {#if u.role !== 'ADMIN'}
                  <button on:click={() => del(u)} class="text-xs px-2 py-1 rounded bg-red-50 text-red-700 hover:bg-red-100"><Icon name="trash-2" size={12} /></button>
                {/if}
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
