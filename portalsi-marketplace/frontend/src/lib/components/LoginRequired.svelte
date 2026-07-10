<script lang="ts">
  import Icon from './Icon.svelte';
  import { page } from '$app/stores';

  let { icon = 'lock', title = 'Login dulu', description = 'Masuk ke akun untuk mengakses halaman ini.' } = $props<{
    icon?: string;
    title?: string;
    description?: string;
  }>();
  const next = $derived(encodeURIComponent($page.url.pathname));
</script>

<div class="container-x py-16 sm:py-24">
  <div class="max-w-md mx-auto card text-center">
    <div class="w-16 h-16 rounded-full bg-ink-100 grid place-items-center mx-auto mb-4">
      <Icon name={icon} size={28} class="text-ink-500" />
    </div>
    <h2 class="font-display text-xl sm:text-2xl font-bold tracking-tightest mb-2">{title}</h2>
    <p class="text-sm text-ink-500 mb-6">{description}</p>
    <div class="flex gap-2 justify-center">
      <a href={`/login?next=${next}`} class="btn-primary btn-md">Masuk</a>
      <a href={`/register?next=${next}`} class="btn-outline btn-md">Daftar</a>
    </div>
  </div>
</div>
