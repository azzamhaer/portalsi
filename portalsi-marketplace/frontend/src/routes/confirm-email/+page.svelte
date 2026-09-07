<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { t } from '$lib/i18n';
</script>

<svelte:head><title>{$t('ce.title')}</title></svelte:head>

<div class="container-x py-16 grid place-items-center min-h-[60vh]">
  <div class="w-full max-w-md card text-center">
    <div class="w-16 h-16 rounded-full bg-emerald-100 grid place-items-center mx-auto mb-4">
      <Icon name="mail-check" size={28} class="text-emerald-600" />
    </div>
    <h1 class="font-display text-xl font-bold tracking-tightest mb-2">{$t('ce.heading')}</h1>
    <p class="text-sm text-ink-500 mb-6">
      Perubahan email akun diproses langsung oleh Portal SI. Buka link konfirmasi dari email Portal SI,
      lalu login ulang di marketplace jika data email belum berubah.
    </p>
    <a href="/login" class="btn-primary btn-md">{$t('auth.signin')}</a>
  </div>
</div>
