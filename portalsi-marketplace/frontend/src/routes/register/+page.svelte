<script lang="ts">
  import { goto } from '$app/navigation';
  import { apiEndpoints, setToken } from '$lib/api';
  import { auth, toast } from '$lib/stores.svelte';
  import { t } from '$lib/i18n';
  import { get } from 'svelte/store';

  let username = $state(''), name = $state(''), email = $state(''), phone = $state(''), password = $state(''), loading = $state(false);

  async function submit(e: Event) {
    e.preventDefault();
    loading = true;
    try {
      const r: any = await apiEndpoints.register({ username, full_name: name, name, email, phone, password });
      setToken(r.token); auth.set(r.user);
      toast.success(get(t)('auth.registerOk'));
      goto('/');
    } catch (e: any) { toast.error(e.message); } finally { loading = false; }
  }
</script>

<svelte:head><title>Daftar — MPSI</title></svelte:head>

<div class="container-x py-16 grid place-items-center min-h-[60vh]">
  <div class="w-full max-w-md card">
    <h1 class="font-display text-3xl font-bold tracking-tightest text-center mb-2">{$t('auth.register')}</h1>
    <p class="text-center text-sm text-ink-500 mb-8">{$t('auth.registerSub')}</p>
    <form on:submit={submit} class="space-y-4">
      <div><label class="label">{$t('auth.username')}</label><input required bind:value={username} class="input" placeholder="contoh: azzam.hudiya" pattern="[A-Za-z0-9._]+" /></div>
      <div><label class="label">{$t('auth.fullName')}</label><input required bind:value={name} class="input" /></div>
      <div><label class="label">{$t('auth.email')}</label><input type="email" required bind:value={email} class="input" /></div>
      <div><label class="label">{$t('auth.phone')}</label><input required bind:value={phone} class="input" placeholder="0812xxxxxxxx" /></div>
      <div><label class="label">{$t('auth.password')}</label><input type="password" required minlength="6" bind:value={password} class="input" /></div>
      <button disabled={loading} class="btn-primary btn-lg w-full">{loading ? $t('auth.processing') : $t('auth.register')}</button>
    </form>
    <p class="text-center text-sm text-ink-500 mt-6">
      {$t('auth.haveAccount')} <a href="/login" class="text-ink-950 font-semibold hover:underline">{$t('auth.signin')}</a>
    </p>
  </div>
</div>
