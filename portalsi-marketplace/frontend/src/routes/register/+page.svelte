<script lang="ts">
  import { goto } from '$app/navigation';
  import { apiEndpoints, setToken } from '$lib/api';
  import { auth, toast } from '$lib/stores.svelte';

  let name = $state(''), email = $state(''), phone = $state(''), password = $state(''), loading = $state(false);

  async function submit(e: Event) {
    e.preventDefault();
    loading = true;
    try {
      const r: any = await apiEndpoints.register({ name, email, phone, password });
      setToken(r.token); auth.set(r.user);
      toast.success('Berhasil daftar. Cek email untuk verifikasi, termasuk folder Spam/Promosi.');
      goto('/');
    } catch (e: any) { toast.error(e.message); } finally { loading = false; }
  }
</script>

<svelte:head><title>Daftar — MPSI</title></svelte:head>

<div class="container-x py-16 grid place-items-center min-h-[60vh]">
  <div class="w-full max-w-md card">
    <h1 class="font-display text-3xl font-bold tracking-tightest text-center mb-2">Daftar</h1>
    <p class="text-center text-sm text-ink-500 mb-8">Buat akun baru, gratis 30 detik.</p>
    <form on:submit={submit} class="space-y-4">
      <div><label class="label">Nama Lengkap</label><input required bind:value={name} class="input" /></div>
      <div><label class="label">Email</label><input type="email" required bind:value={email} class="input" /></div>
      <div><label class="label">No. HP</label><input required bind:value={phone} class="input" placeholder="0812xxxxxxxx" /></div>
      <div><label class="label">Password</label><input type="password" required minlength="6" bind:value={password} class="input" /></div>
      <button disabled={loading} class="btn-primary btn-lg w-full">{loading ? 'Memproses…' : 'Daftar'}</button>
    </form>
    <p class="text-center text-sm text-ink-500 mt-6">
      Sudah punya akun? <a href="/login" class="text-ink-950 font-semibold hover:underline">Masuk</a>
    </p>
  </div>
</div>
