<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import Icon from './Icon.svelte';
  import { auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints, setToken, getToken } from '$lib/api';
  import { goto } from '$app/navigation';

  let resending = $state(false);
  let checking = $state(false);
  let cooldown = $state(0);
  let remainingToday = $state<number | null>(null);
  let limitReached = $state(false);
  let tick: any;

  function startCooldown(seconds: number) {
    cooldown = seconds;
    clearInterval(tick);
    tick = setInterval(() => {
      cooldown -= 1;
      if (cooldown <= 0) clearInterval(tick);
    }, 1000);
  }

  onDestroy(() => clearInterval(tick));

  async function resend() {
    if (cooldown > 0 || resending || limitReached) return;
    resending = true;
    try {
      const r: any = await apiEndpoints.resendVerification();
      remainingToday = typeof r.remaining_today === 'number' ? r.remaining_today : null;
      startCooldown(r.cooldown_seconds || 60);
      toast.success(r.message || 'Email verifikasi Portal SI dikirim ulang.');
    } catch (e: any) {
      if (e.data?.cooldown_seconds) startCooldown(e.data.cooldown_seconds);
      if (e.data?.limit_reached) { limitReached = true; remainingToday = 0; }
      toast.error(e.message);
    } finally {
      resending = false;
    }
  }

  async function refreshStatus() {
    if (!getToken()) return;
    checking = true;
    try {
      const u: any = await apiEndpoints.me();
      auth.set(u);
      if (u.email_verified_at) toast.success('Email Anda sudah terverifikasi!');
      else toast.info('Email belum terverifikasi. Cek inbox atau klik link dari Portal SI.');
    } catch (e: any) {
      toast.error(e.message);
    } finally {
      checking = false;
    }
  }

  async function logout() {
    try { await apiEndpoints.logout(); } catch {}
    setToken(null);
    auth.clear();
    goto('/login');
  }

  onMount(() => {
    const auto = setInterval(refreshStatus, 15000);
    return () => clearInterval(auto);
  });
</script>

<div class="container-x py-16 sm:py-24">
  <div class="max-w-md mx-auto card text-center">
    <div class="w-16 h-16 rounded-full bg-amber-100 grid place-items-center mx-auto mb-4">
      <Icon name="mail" size={28} class="text-amber-600" />
    </div>
    <h2 class="font-display text-xl sm:text-2xl font-bold tracking-tightest mb-2">Verifikasi email Portal SI Anda</h2>
    <p class="text-sm text-ink-500 mb-2">Portal SI sudah mengirim link verifikasi ke:</p>
    <p class="font-semibold text-ink-900 mb-6 break-all">{auth.user?.email}</p>
    <p class="text-xs text-ink-500 mb-6">
      Klik link resmi dari Portal SI untuk mengaktifkan akun. Cek folder Spam/Promosi jika tidak ada di Inbox.
      Anda belum bisa mengakses fitur marketplace sampai verifikasi selesai.
    </p>

    <div class="flex gap-2 justify-center flex-wrap">
      <button onclick={resend} disabled={resending || cooldown > 0 || limitReached}
              class="btn-primary btn-md min-w-[160px]">
        <Icon name="mail" size={14} />
        {#if limitReached}
          Batas tercapai
        {:else if cooldown > 0}
          Tunggu {cooldown}s
        {:else if resending}
          Mengirim...
        {:else}
          Kirim ulang email
        {/if}
      </button>
      <button onclick={refreshStatus} disabled={checking} class="btn-outline btn-md">
        <Icon name="refresh-cw" size={14} class={checking ? 'animate-spin' : ''} />
        Cek status
      </button>
    </div>

    {#if remainingToday !== null}
      <p class="text-xs text-ink-500 mt-3">
        Sisa kuota kirim ulang hari ini: <b>{remainingToday}</b>
      </p>
    {:else}
      <p class="text-xs text-ink-400 mt-3">Kirim ulang mengikuti batas dan jeda dari Portal SI.</p>
    {/if}

    <div class="mt-6 pt-6 border-t border-ink-100">
      <button onclick={logout} class="text-sm text-red-600 hover:underline">Logout</button>
    </div>
  </div>
</div>
