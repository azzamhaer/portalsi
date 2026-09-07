<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { t } from '$lib/i18n';
  import { fmtRp } from '$lib/utils';
  import { settings } from '$lib/stores.svelte';
  let { data } = $props();

  const ICON: Record<string, string> = {
    'Virtual Account': 'landmark', 'E-Wallet': 'smartphone', 'QRIS': 'qr-code',
    'Convenience Store': 'store', 'Credit Card': 'credit-card'
  };

  // Grouped methods (reactive — recalc when data.methods changes)
  const grouped = $derived.by(() => {
    const g: Record<string, any[]> = {};
    (data.methods ?? []).forEach((m: any) => { (g[m.group] = g[m.group] || []).push(m); });
    return g;
  });
</script>

<svelte:head><title>Cara Pembayaran — MPSI</title></svelte:head>

<div class="container-x py-12 max-w-5xl">
  <div class="text-center mb-14">
    <div class="section-eyebrow mb-2">{$t('pi.eyebrow')}</div>
    <h1 class="font-display text-4xl md:text-5xl font-bold tracking-tightest mb-4">{$t('pi.heading')}</h1>
    <p class="text-lg text-ink-600 max-w-2xl mx-auto">{(data.methods ?? []).length}{$t('pi.methodsSuffix')}</p>
    {#if settings.paymentIntro}
      <div class="mt-6 max-w-3xl mx-auto text-left text-sm text-ink-700 bg-ink-50 rounded-2xl p-5 whitespace-pre-line">{settings.paymentIntro}</div>
    {/if}
  </div>

  {#each Object.entries(grouped) as [g, ms]}
    {@const iconName = ICON[g] ?? 'credit-card'}
    <section class="mb-10" id={g === 'Virtual Account' ? 'va' : g === 'E-Wallet' ? 'ewallet' : g === 'QRIS' ? 'qris' : g === 'Convenience Store' ? 'retail' : ''}>
      <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
        <Icon name={iconName} size={20} class="text-ink-700" /> {g}
      </h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {#each ms as m}
          <div class="card !p-4 flex items-center gap-3">
            {#if m.icon}
              <div class="w-10 h-10 rounded-lg overflow-hidden bg-white border border-ink-100 grid place-items-center">
                <img src={m.icon} alt={m.name} class="w-full h-full object-contain" />
              </div>
            {:else}
              <div class="w-10 h-10 rounded-lg grid place-items-center text-white text-xs font-bold" style:background={m.color}>{m.code.slice(0,3)}</div>
            {/if}
            <div class="flex-1 min-w-0">
              <b class="text-sm block truncate">{m.name}</b>
              <small class="text-xs text-ink-500">{$t('pi.fee')} {m.fee_pct ? m.fee_pct + '%' : fmtRp(m.fee_flat)}</small>
            </div>
          </div>
        {/each}
      </div>
    </section>
  {/each}

  <section class="mt-16 grid md:grid-cols-3 gap-6 py-12 border-t border-ink-100">
    <div class="flex gap-4">
      <Icon name="shield-check" size={28} class="text-emerald-600 shrink-0" />
      <div><b class="block mb-1">{$t('pi.e2e')}</b><p class="text-sm text-ink-500">{$t('pi.e2eDesc')}</p></div>
    </div>
    <div class="flex gap-4">
      <Icon name="clock" size={28} class="text-blue-600 shrink-0" />
      <div><b class="block mb-1">{$t('pi.realtime')}</b><p class="text-sm text-ink-500">{$t('pi.realtimeDesc')}</p></div>
    </div>
    <div class="flex gap-4">
      <Icon name="landmark" size={28} class="text-amber-600 shrink-0" />
      <div><b class="block mb-1">{$t('pi.escrow')}</b><p class="text-sm text-ink-500">{$t('pi.escrowDesc')}</p></div>
    </div>
  </section>
</div>
