<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
</script>

<svelte:head><title>Tentang — MPSI</title></svelte:head>

<div class="container-x py-12 md:py-20 max-w-4xl">
  <div class="text-center mb-16">
    <div class="section-eyebrow mb-3">Tentang Kami</div>
    <h1 class="font-display text-4xl md:text-6xl font-bold tracking-tightest leading-[1.05] text-balance mb-5">
      Marketplace untuk semua, <br/><span class="italic font-light">dibangun dengan kepercayaan.</span>
    </h1>
    <p class="text-lg text-ink-600 max-w-2xl mx-auto">
      MPSI menghubungkan jutaan pembeli dengan ribuan toko terpercaya di seluruh Indonesia. Aman, cepat, dan transparan.
    </p>
  </div>

  <div class="grid md:grid-cols-2 gap-8 mb-16">
    <div>
      <div class="section-eyebrow mb-2">Visi</div>
      <h2 class="text-2xl font-bold tracking-tightest mb-3">Menjadi platform e-commerce terdepan di Asia Tenggara.</h2>
      <p class="text-ink-600">Memberdayakan UMKM Indonesia melalui teknologi & ekosistem pembayaran yang aman.</p>
    </div>
    <div>
      <div class="section-eyebrow mb-2">Misi</div>
      <h2 class="text-2xl font-bold tracking-tightest mb-3">Belanja yang mudah, cepat, dan aman.</h2>
      <ul class="text-ink-600 space-y-2">
        <li>— Mendukung pertumbuhan seller lokal dengan tools gratis.</li>
        <li>— Pembayaran end-to-end terenkripsi & terverifikasi.</li>
        <li>— Ekosistem yang transparan untuk pembeli & penjual.</li>
      </ul>
    </div>
  </div>

  <div class="grid md:grid-cols-4 gap-6 py-12 border-y border-ink-100">
    {#each [
      { i:'shield',     t:'100%', s:'Aman & Terenkripsi' },
      { i:'truck',      t:'6+',   s:'Kurir Resmi' },
      { i:'sparkles',   t:'68+',  s:'Produk Pilihan' },
      { i:'headphones', t:'24/7', s:'Dukungan Pelanggan' }
    ] as i}
      <div class="text-center">
        <Icon name={i.i} size={28} class="mx-auto mb-3 text-ink-700" />
        <div class="font-display text-3xl font-bold tracking-tightest">{i.t}</div>
        <div class="text-sm text-ink-500 mt-1">{i.s}</div>
      </div>
    {/each}
  </div>

  <div class="text-center mt-16">
    <p class="text-ink-500 text-sm mb-2">Kantor Pusat</p>
    <p class="text-base">Gedung MPSI Tower, Jl. Jenderal Sudirman Kav. 52-53, Jakarta Selatan 12190</p>
    <p class="text-sm text-ink-500 mt-1">support@portalsi.id · 0800-1-PORTAL</p>
  </div>
</div>
