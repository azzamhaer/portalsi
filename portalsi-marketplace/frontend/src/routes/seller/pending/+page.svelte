<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import { auth } from '$lib/stores.svelte';
  import { goto } from '$app/navigation';

  let vendor = $state<any>(null);
  let loading = $state(true);
  let pollTimer: ReturnType<typeof setInterval> | null = null;

  async function syncVendorStatus() {
    if (!getToken()) {
      goto('/login?next=/seller/pending');
      return;
    }

    try {
      const data: any = await apiEndpoints.sellerDashboard();
      vendor = data.vendor;
      auth.set({
        ...auth.user,
        vendor_id: data.vendor.id,
        vendor_status: data.vendor.verification_status,
        vendor_username: data.vendor.username,
        vendor_tour_done: data.vendor.tour_completed_at !== null
      });

      if (vendor.verification_status === 'APPROVED') {
        goto('/seller/dashboard');
      }
    } catch (e: any) {
      if (e.status === 404) goto('/seller/register');
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    syncVendorStatus();
    pollTimer = setInterval(syncVendorStatus, 8000);
  });

  onDestroy(() => {
    if (pollTimer) clearInterval(pollTimer);
  });
</script>

<svelte:head><title>Menunggu Verifikasi - Seller</title></svelte:head>

<div class="container-x py-10 sm:py-16">
  <div class="max-w-xl mx-auto">
    {#if loading}
      <div class="card text-center text-ink-500 py-12">Memuat...</div>
    {:else if vendor}
      <div class="card text-center">
        {#if vendor.verification_status === 'PENDING'}
          <div class="w-16 h-16 rounded-full bg-amber-100 grid place-items-center mx-auto mb-4">
            <Icon name="clock" size={32} class="text-amber-600" />
          </div>
          <h1 class="font-display text-2xl font-bold tracking-tightest mb-2">Menunggu Verifikasi</h1>
          <p class="text-sm text-ink-500 mb-6">
            Toko <span class="font-semibold text-ink-900">{vendor.name}</span> sedang dalam proses verifikasi admin.
            Kami akan memeriksa data dan KTP yang Anda kirim. Halaman ini akan otomatis masuk ke dashboard setelah disetujui.
          </p>
        {:else if vendor.verification_status === 'REJECTED'}
          <div class="w-16 h-16 rounded-full bg-red-100 grid place-items-center mx-auto mb-4">
            <Icon name="x-circle" size={32} class="text-red-600" />
          </div>
          <h1 class="font-display text-2xl font-bold tracking-tightest mb-2">Verifikasi Ditolak</h1>
          <p class="text-sm text-ink-500 mb-2">Verifikasi toko <span class="font-semibold text-ink-900">{vendor.name}</span> ditolak oleh admin.</p>
          {#if vendor.verification_note}
            <div class="bg-red-50 text-red-800 text-sm p-3 rounded-xl mb-6">
              <b>Alasan:</b> {vendor.verification_note}
            </div>
          {/if}
          <p class="text-sm text-ink-500 mb-6">Silakan perbarui profil toko Anda lalu hubungi admin untuk pengajuan ulang.</p>
        {/if}

        <div class="max-w-md mx-auto">
          <a href="/" class="btn-primary btn-md">Kembali ke Beranda</a>
        </div>

        <div class="mt-8 pt-6 border-t border-ink-100 text-left text-sm">
          <h3 class="font-semibold mb-3">Status akses seller:</h3>
          <ul class="space-y-2 text-ink-600">
            <li class="flex items-start gap-2"><Icon name="lock" size={14} class="mt-1 text-amber-600" /> Dashboard seller belum dapat dibuka.</li>
            <li class="flex items-start gap-2"><Icon name="lock" size={14} class="mt-1 text-amber-600" /> Produk, pesanan, penarikan, dan profil toko terkunci sampai admin menyetujui verifikasi.</li>
            <li class="flex items-start gap-2"><Icon name="refresh-cw" size={14} class="mt-1 text-ink-500" /> Halaman ini mengecek status otomatis setiap beberapa detik.</li>
          </ul>
        </div>
      </div>
    {/if}
  </div>
</div>
