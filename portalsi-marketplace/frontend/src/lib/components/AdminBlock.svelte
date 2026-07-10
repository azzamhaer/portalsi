<script lang="ts">
  import Icon from './Icon.svelte';
  let { title = 'Halaman ini tidak tersedia untuk admin', description = 'Akun admin dipakai untuk mengelola platform. Untuk berbelanja, gunakan akun pembeli.' } = $props<{
    title?: string;
    description?: string;
  }>();
</script>

<div class="container-x py-16 sm:py-24">
  <div class="max-w-md mx-auto card text-center">
    <div class="w-16 h-16 rounded-full bg-amber-100 grid place-items-center mx-auto mb-4">
      <Icon name="shield-alert" size={28} class="text-amber-600" />
    </div>
    <h2 class="font-display text-xl sm:text-2xl font-bold tracking-tightest mb-2">{title}</h2>
    <p class="text-sm text-ink-500 mb-6">{description}</p>
    <div class="flex gap-2 justify-center">
      <a href="/admin" class="btn-primary btn-md">Ke Admin Center</a>
      <a href="/" class="btn-outline btn-md">Beranda</a>
    </div>
  </div>
</div>
