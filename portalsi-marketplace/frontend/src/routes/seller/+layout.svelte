<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import { auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import AdminBlock from '$lib/components/AdminBlock.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let { children } = $props();
  let mounted = $state(false);
  let serverChecked = $state(false);
  let checking = $state(true);

  const isAdmin = $derived(auth.user?.role === 'ADMIN');
  const vendorStatus = $derived(auth.user?.vendor_status);
  const hasVendor = $derived(!!auth.user?.vendor_id);

  const path = $derived($page.url.pathname);
  const exempt = ['/seller/register', '/seller/pending'];
  const isExempt = $derived(exempt.includes(path));

  onMount(async () => {
    mounted = true;
    if (!getToken() || !auth.user) {
      goto('/login?next=' + path);
      return;
    }
    if (auth.user.role === 'ADMIN') {
      checking = false;
      return;
    }

    try {
      const me: any = await apiEndpoints.me();
      auth.set(me);

      if (isExempt) {
        serverChecked = true;
        return;
      }

      if (!me.vendor_id) {
        goto('/seller/register');
        return;
      }

      if (me.vendor_status !== 'APPROVED') {
        goto('/seller/pending');
        return;
      }

      serverChecked = true;
    } catch (e: any) {
      toast.error(e.message);
      goto('/');
    } finally {
      checking = false;
    }
  });
</script>

{#if isAdmin}
  <AdminBlock title="Admin tidak bisa menjadi seller" description="Akun admin dipakai untuk mengelola platform, bukan untuk berjualan." />
{:else if isExempt && mounted && !checking}
  {@render children()}
{:else if serverChecked}
  {@render children()}
{:else if hasVendor && vendorStatus && vendorStatus !== 'APPROVED'}
  <div class="container-x py-16">
    <div class="max-w-md mx-auto card text-center">
      {#if vendorStatus === 'PENDING'}
        <div class="w-16 h-16 rounded-full bg-amber-100 grid place-items-center mx-auto mb-4">
          <Icon name="clock" size={28} class="text-amber-600" />
        </div>
        <h2 class="font-display text-xl font-bold tracking-tightest mb-2">Menunggu verifikasi admin</h2>
        <p class="text-sm text-ink-500 mb-5">Toko Anda sedang diverifikasi oleh admin. Anda belum bisa mengakses Seller Center.</p>
      {:else}
        <div class="w-16 h-16 rounded-full bg-red-100 grid place-items-center mx-auto mb-4">
          <Icon name="x-circle" size={28} class="text-red-600" />
        </div>
        <h2 class="font-display text-xl font-bold tracking-tightest mb-2">Verifikasi ditolak</h2>
        <p class="text-sm text-ink-500 mb-5">Pengajuan toko Anda ditolak. Buka detail untuk melihat alasan & perbarui profil.</p>
      {/if}
      <div class="flex gap-2 justify-center">
        <a href="/seller/pending" class="btn-primary btn-md">Lihat detail</a>
      </div>
    </div>
  </div>
{:else}
  <div class="container-x py-16">
    <div class="card text-center text-ink-500">{mounted ? 'Memuat...' : 'Memuat...'}</div>
  </div>
{/if}
