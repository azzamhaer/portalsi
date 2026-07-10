<script lang="ts">
  import { settings } from '$lib/stores.svelte';
  let { data } = $props();
</script>

<svelte:head><title>Pusat Bantuan — MPSI</title></svelte:head>

<div class="container-x py-12 max-w-4xl">
  <div class="text-center mb-12">
    <div class="section-eyebrow mb-2">Bantuan</div>
    <h1 class="font-display text-4xl font-bold tracking-tightest">Bagaimana kami bisa membantu?</h1>
    {#if settings.helpIntro}
      <div class="mt-6 max-w-2xl mx-auto text-left text-sm text-ink-700 bg-ink-50 rounded-2xl p-5 whitespace-pre-line">{settings.helpIntro}</div>
    {/if}
  </div>

  <div class="space-y-10">
    {#each data.sections as s}
      <section>
        <h2 class="text-xl font-bold tracking-tightest mb-4 pb-3 border-b border-ink-200">{s.title}</h2>
        <div class="space-y-2">
          {#each s.items as it}
            <details class="group border border-ink-100 rounded-2xl p-5 hover:border-ink-200 transition">
              <summary class="cursor-pointer font-medium flex items-center justify-between gap-4 list-none">
                <span>{it.q}</span>
                <span class="w-6 h-6 rounded-full bg-ink-100 grid place-items-center text-xs font-bold group-open:bg-app-primary group-open:text-app-pfg transition">+</span>
              </summary>
              <p class="text-sm text-ink-600 mt-3 leading-relaxed whitespace-pre-line">{it.a}</p>
            </details>
          {/each}
        </div>
      </section>
    {/each}
  </div>
</div>
