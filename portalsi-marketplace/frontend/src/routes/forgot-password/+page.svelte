<script lang="ts">
  import { apiEndpoints } from '$lib/api';
  import { setToken } from '$lib/api';
  import { auth, toast } from '$lib/stores.svelte';
  import { onMount } from 'svelte';

  let email = $state(''), sending = $state(false), sent = $state(false);
  onMount(() => {
    setToken(null);
    auth.clear();
  });
  async function submit(e: Event) {
    e.preventDefault();
    sending = true;
    try {
      await apiEndpoints.forgotPassword(email);
      sent = true;
      toast.success('Email reset password sudah dikirim');
    } catch (e: any) { toast.error(e.message); } finally { sending = false; }
  }
</script>

<svelte:head><title>Lupa Password</title></svelte:head>

<div class="container-x py-16 grid place-items-center min-h-[60vh]">
  <div class="w-full max-w-md card">
    <h1 class="font-display text-2xl font-bold tracking-tightest text-center mb-2">Lupa Password</h1>
    <p class="text-center text-sm text-ink-500 mb-6">Masukkan email akun Anda. Kami akan kirim link untuk mengatur password baru.</p>
    {#if sent}
      <div class="bg-emerald-50 text-emerald-800 text-sm p-4 rounded-xl">
        Jika email <b>{email}</b> terdaftar, link reset password sudah dikirim. Cek inbox (atau folder spam) Anda.
      </div>
      <p class="text-center text-sm mt-5"><a href="/login" class="link">Kembali ke Masuk</a></p>
    {:else}
      <form on:submit={submit} class="space-y-4">
        <div><label class="label">Email</label><input type="email" required bind:value={email} class="input" /></div>
        <button disabled={sending} class="btn-primary btn-md w-full">{sending ? 'Mengirim…' : 'Kirim Link Reset'}</button>
      </form>
      <p class="text-center text-sm text-ink-500 mt-6"><a href="/login" class="link">Kembali ke Masuk</a></p>
    {/if}
  </div>
</div>
