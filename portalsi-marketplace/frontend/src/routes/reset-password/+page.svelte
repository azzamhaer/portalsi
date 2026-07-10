<script lang="ts">
  import { page } from '$app/stores';
  import { apiEndpoints, setToken } from '$lib/api';
  import { auth, toast } from '$lib/stores.svelte';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';

  const token = $derived($page.url.searchParams.get('token') || '');
  let pwd = $state(''), confirm = $state(''), saving = $state(false);

  onMount(() => {
    setToken(null);
    auth.clear();
  });

  async function submit(e: Event) {
    e.preventDefault();
    if (pwd !== confirm) { toast.error('Konfirmasi password tidak cocok'); return; }
    saving = true;
    try {
      await apiEndpoints.resetPassword(token, pwd);
      toast.success('Password berhasil direset, silakan masuk');
      goto('/login');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }
</script>

<svelte:head><title>Reset Password</title></svelte:head>

<div class="container-x py-16 grid place-items-center min-h-[60vh]">
  <div class="w-full max-w-md card">
    <h1 class="font-display text-2xl font-bold tracking-tightest text-center mb-6">Atur Password Baru</h1>
    {#if !token}
      <div class="bg-red-50 text-red-800 text-sm p-4 rounded-xl">Token tidak valid. Silakan minta link baru dari halaman <a href="/forgot-password" class="underline">Lupa Password</a>.</div>
    {:else}
      <form on:submit={submit} class="space-y-4">
        <div><label class="label">Password baru</label><input type="password" required minlength="6" bind:value={pwd} class="input" /></div>
        <div><label class="label">Konfirmasi password</label><input type="password" required minlength="6" bind:value={confirm} class="input" /></div>
        <button disabled={saving} class="btn-primary btn-md w-full">{saving ? 'Menyimpan…' : 'Reset Password'}</button>
      </form>
    {/if}
  </div>
</div>
