<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { t } from '$lib/i18n';
</script>

<svelte:head><title>Tentang — MPSI</title></svelte:head>

<div class="container-x py-12 md:py-20 max-w-4xl">
  <div class="text-center mb-16">
    <div class="section-eyebrow mb-3">{$t('ab.heading')}</div>
    <h1 class="font-display text-4xl md:text-6xl font-bold tracking-tightest leading-[1.05] text-balance mb-5">
      Marketplace untuk semua, <br/><span class="italic font-light">{$t('lf.aboutTrust')}</span>
    </h1>
    <p class="text-lg text-ink-600 max-w-2xl mx-auto">
      MPSI menghubungkan jutaan pembeli dengan ribuan toko terpercaya di seluruh Indonesia. Aman, cepat, dan transparan.
    </p>
  </div>

  <div class="grid md:grid-cols-2 gap-8 mb-16">
    <div>
      <div class="section-eyebrow mb-2">{$t('ab.vision')}</div>
      <h2 class="text-2xl font-bold tracking-tightest mb-3">{$t('ab.visionText1')}</h2>
      <p class="text-ink-600">{$t('ab.visionText2')}</p>
    </div>
    <div>
      <div class="section-eyebrow mb-2">{$t('ab.mission')}</div>
      <h2 class="text-2xl font-bold tracking-tightest mb-3">{$t('ab.missionText')}</h2>
      <ul class="text-ink-600 space-y-2">
        <li>{$t('lf.aboutV1')}</li>
        <li>— Pembayaran end-to-end terenkripsi & terverifikasi.</li>
        <li>{$t('lf.aboutV2')}</li>
      </ul>
    </div>
  </div>

  <div class="grid md:grid-cols-4 gap-6 py-12 border-y border-ink-100">
    {#each [
      { i:'shield',     t:'100%', s:$t('ab.stat1') },
      { i:'truck',      t:'6+',   s:$t('ab.stat2') },
      { i:'sparkles',   t:'68+',  s:$t('ab.stat3') },
      { i:'headphones', t:'24/7', s:$t('ab.stat4') }
    ] as i}
      <div class="text-center">
        <Icon name={i.i} size={28} class="mx-auto mb-3 text-ink-700" />
        <div class="font-display text-3xl font-bold tracking-tightest">{i.t}</div>
        <div class="text-sm text-ink-500 mt-1">{i.s}</div>
      </div>
    {/each}
  </div>

  <div class="text-center mt-16">
    <p class="text-ink-500 text-sm mb-2">{$t('ab.hq')}</p>
    <p class="text-base">Gedung MPSI Tower, Jl. Jenderal Sudirman Kav. 52-53, Jakarta Selatan 12190</p>
    <p class="text-sm text-ink-500 mt-1">support@portalsi.id · 0800-1-PORTAL</p>
  </div>
</div>
