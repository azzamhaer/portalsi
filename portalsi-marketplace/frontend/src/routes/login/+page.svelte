<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import { apiEndpoints, setToken } from '$lib/api';
  import { auth, toast } from '$lib/stores.svelte';

  let email = $state(''), password = $state(''), loading = $state(false);
  const next = $derived($page.url.searchParams.get('next') || '/');
  const action = $derived($page.url.searchParams.get('action') || '');

  async function submit(e: Event) {
    e.preventDefault();
    loading = true;
    try {
      const r: any = await apiEndpoints.login(email, password);
      setToken(r.token); auth.set(r.user);
      toast.success('Selamat datang');
      if (action) {
        const u = new URL(next, window.location.origin);
        u.searchParams.set('resume_action', action);
        goto(u.pathname + u.search);
      } else {
        goto(next);
      }
    } catch (e: any) { toast.error(e.message); } finally { loading = false; }
  }
</script>

<svelte:head><title>Masuk — MPSI</title></svelte:head>

<div class="container-x py-16 grid place-items-center min-h-[60vh]">
  <div class="w-full max-w-md card">
    <h1 class="font-display text-3xl font-bold tracking-tightest text-center mb-2">Masuk</h1>
    <p class="text-center text-sm text-ink-500 mb-8">Lanjutkan ke akun Anda.</p>
    <form on:submit={submit} class="space-y-4">
      <div><label class="label">Email</label>
        <input type="email" required autocomplete="email" bind:value={email} class="input" />
      </div>
      <div><label class="label">Password</label>
        <input type="password" required autocomplete="current-password" bind:value={password} class="input" />
      </div>
      <div class="text-right -mt-2">
        <a href="/forgot-password" class="text-xs text-ink-500 hover:text-ink-950">Lupa password?</a>
      </div>
      <button disabled={loading} class="btn-primary btn-lg w-full">{loading ? 'Memproses…' : 'Masuk'}</button>
    </form>
    <p class="text-center text-sm text-ink-500 mt-6">
      Belum punya akun? <a href="/register" class="text-ink-950 font-semibold hover:underline">Daftar</a>
    </p>
  </div>
</div>
