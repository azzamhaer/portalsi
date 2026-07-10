<script lang="ts">
  import { page } from '$app/stores';
  import Icon from '$lib/components/Icon.svelte';
  import { settings } from '$lib/stores.svelte';

  const status = $derived($page.status);
  const headline = $derived(status === 404 ? 'Halaman tidak ditemukan' : 'Terjadi kesalahan');
  const description = $derived(
    status === 404
      ? 'Halaman yang Anda cari mungkin sudah dipindahkan, dihapus, atau alamatnya salah.'
      : $page.error?.message || 'Silakan kembali ke beranda dan coba lagi nanti.'
  );
  const siteName = $derived(settings.appName ?? 'MPSI Marketplace');
</script>

<svelte:head>
  <title>{status} - {siteName}</title>
  <meta name="robots" content="noindex,nofollow" />
</svelte:head>

<section class="container-x grid min-h-[calc(100vh-18rem)] place-items-center py-16 text-center sm:py-24">
  <div class="mx-auto max-w-xl">
    <p class="font-display text-8xl font-extrabold leading-none tracking-tightest text-ink-950 sm:text-9xl">
      {status}
    </p>
    <h1 class="mt-6 font-display text-2xl font-bold tracking-tightest text-ink-950 sm:text-4xl">
      {headline}
    </h1>
    <p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-ink-600 sm:text-base">
      {description}
    </p>
    <a href="/" class="btn-primary btn-md mt-8">
      <Icon name="home" size={16} />
      Kembali ke Beranda
    </a>
  </div>
</section>
