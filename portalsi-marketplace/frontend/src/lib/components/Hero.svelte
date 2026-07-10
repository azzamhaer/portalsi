<script lang="ts">
  import Icon from './Icon.svelte';
  import { settings } from '$lib/stores.svelte';
</script>

<section class="relative overflow-hidden bg-gradient-to-br from-ink-50 via-white to-ink-50 rounded-3xl">
  <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_15%,rgba(99,102,241,0.08),transparent_50%)]"></div>

  <div class="relative px-5 py-12 sm:px-10 sm:py-16 md:px-14 md:py-20 lg:py-24 grid lg:grid-cols-2 gap-10 items-center">
    <div class="max-w-xl">
      {#if settings.tagline?.trim()}
        <div class="inline-flex items-center gap-2 text-xs font-medium px-3 py-1 rounded-full bg-app-primary text-app-pfg mb-5">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
          {settings.tagline}
        </div>
      {/if}
      <h1 class="font-display text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold tracking-tightest text-ink-950 leading-[1.05] text-balance mb-5">
        {settings.heroTitle}
      </h1>
      <p class="text-base md:text-lg text-ink-600 leading-relaxed mb-8 max-w-md">
        {settings.heroSubtitle}
      </p>
      <div class="flex flex-wrap items-center gap-3">
        <a href={settings.heroCtaHref || '/products'} class="btn-primary btn-lg">
          {settings.heroCtaLabel || 'Mulai belanja'} <Icon name="arrow-right" size={16} />
        </a>
        <a href="/seller/register" class="btn-outline btn-lg">Buka Toko Anda</a>
      </div>
    </div>

    <div class="relative hidden lg:block">
      {#if settings.heroImage}
        <div class="aspect-[4/3] rounded-3xl overflow-hidden bg-ink-100">
          <img src={settings.heroImage} alt="" class="w-full h-full object-cover" />
        </div>
      {:else}
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-4">
            <div class="aspect-[3/4] rounded-2xl bg-gradient-to-br from-ink-900 to-ink-700"></div>
            <div class="aspect-square rounded-2xl bg-gradient-to-br from-amber-200 to-orange-300"></div>
          </div>
          <div class="space-y-4 mt-12">
            <div class="aspect-square rounded-2xl bg-gradient-to-br from-rose-200 to-pink-300"></div>
            <div class="aspect-[3/4] rounded-2xl bg-gradient-to-br from-emerald-200 to-teal-300"></div>
          </div>
        </div>
      {/if}
    </div>
  </div>
</section>
