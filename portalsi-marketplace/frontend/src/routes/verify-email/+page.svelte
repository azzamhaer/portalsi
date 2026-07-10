<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import { apiEndpoints, getToken } from '$lib/api';
  import { auth } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let status = $state<'pending' | 'ok' | 'fail'>('pending');
  let message = $state('');

  onMount(async () => {
    const token = $page.url.searchParams.get('token');
    if (!token) { status = 'fail'; message = 'Token tidak ditemukan di URL.'; return; }
    try {
      await apiEndpoints.verifyEmail(token);
      // PENTING: refresh auth.user supaya gate verification hilang
      if (getToken()) {
        try { auth.set(await apiEndpoints.me()); } catch {}
      }
      status = 'ok';
    } catch (e: any) { status = 'fail'; message = e.message; }
  });
</script>

<svelte:head><title>Verifikasi Email</title></svelte:head>

<div class="container-x py-16 grid place-items-center min-h-[60vh]">
  <div class="w-full max-w-md card text-center">
    {#if status === 'pending'}
      <Icon name="loader" size={48} class="mx-auto text-ink-400 mb-4 animate-spin" />
      <p>Memverifikasi email…</p>
    {:else if status === 'ok'}
      <div class="w-16 h-16 rounded-full bg-emerald-100 grid place-items-center mx-auto mb-4">
        <Icon name="check" size={28} class="text-emerald-600" />
      </div>
      <h1 class="font-display text-xl font-bold tracking-tightest mb-2">Email terverifikasi</h1>
      <p class="text-sm text-ink-500 mb-6">Terima kasih, akun Anda sudah aktif sepenuhnya.</p>
      <a href="/" class="btn-primary btn-md">Ke Beranda</a>
    {:else}
      <div class="w-16 h-16 rounded-full bg-red-100 grid place-items-center mx-auto mb-4">
        <Icon name="x" size={28} class="text-red-600" />
      </div>
      <h1 class="font-display text-xl font-bold tracking-tightest mb-2">Verifikasi gagal</h1>
      <p class="text-sm text-ink-500 mb-6">{message}</p>
      <a href="/" class="btn-outline btn-md">Ke Beranda</a>
    {/if}
  </div>
</div>
