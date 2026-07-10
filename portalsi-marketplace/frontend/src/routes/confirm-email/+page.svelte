<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import { apiEndpoints } from '$lib/api';
  import Icon from '$lib/components/Icon.svelte';

  let status = $state<'pending' | 'ok' | 'fail'>('pending');
  let message = $state('');
  let newEmail = $state('');

  onMount(async () => {
    const token = $page.url.searchParams.get('token');
    if (!token) { status = 'fail'; message = 'Token tidak ditemukan di URL.'; return; }
    try {
      const r: any = await apiEndpoints.confirmEmail(token);
      newEmail = r.email;
      status = 'ok';
    } catch (e: any) { status = 'fail'; message = e.message; }
  });
</script>

<svelte:head><title>Konfirmasi Email</title></svelte:head>

<div class="container-x py-16 grid place-items-center min-h-[60vh]">
  <div class="w-full max-w-md card text-center">
    {#if status === 'pending'}
      <Icon name="loader" size={48} class="mx-auto text-ink-400 mb-4 animate-spin" />
      <p>Memverifikasi…</p>
    {:else if status === 'ok'}
      <div class="w-16 h-16 rounded-full bg-emerald-100 grid place-items-center mx-auto mb-4">
        <Icon name="check" size={28} class="text-emerald-600" />
      </div>
      <h1 class="font-display text-xl font-bold tracking-tightest mb-2">Email berhasil diubah</h1>
      <p class="text-sm text-ink-500 mb-6">Email akun Anda sekarang: <b>{newEmail}</b>. Silakan login ulang.</p>
      <a href="/login" class="btn-primary btn-md">Masuk</a>
    {:else}
      <div class="w-16 h-16 rounded-full bg-red-100 grid place-items-center mx-auto mb-4">
        <Icon name="x" size={28} class="text-red-600" />
      </div>
      <h1 class="font-display text-xl font-bold tracking-tightest mb-2">Konfirmasi gagal</h1>
      <p class="text-sm text-ink-500 mb-6">{message}</p>
      <a href="/" class="btn-outline btn-md">Ke Beranda</a>
    {/if}
  </div>
</div>
