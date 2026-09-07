<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { t } from '$lib/i18n';
</script>

<svelte:head><title>{$t('ve.title')}</title></svelte:head>

<div class="container-x py-16 grid place-items-center min-h-[60vh]">
  <div class="w-full max-w-md card text-center">
    <div class="w-16 h-16 rounded-full bg-amber-100 grid place-items-center mx-auto mb-4">
      <Icon name="mail-check" size={28} class="text-amber-600" />
    </div>
    <h1 class="font-display text-xl font-bold tracking-tightest mb-2">{$t('ve.heading')}</h1>
    <p class="text-sm text-ink-500 mb-6">
      Link verifikasi akun kini dikirim dan diproses langsung oleh Portal SI. Buka link dari email Portal SI,
      lalu kembali ke marketplace dan klik cek status pada halaman verifikasi.
    </p>
    <a href="/" class="btn-primary btn-md">{$t('ve.toHome')}</a>
  </div>
</div>
