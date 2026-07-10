<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import Icon from './Icon.svelte';
  import { apiEndpoints } from '$lib/api';
  import { auth, toast } from '$lib/stores.svelte';

  let { onFinish = () => {} } = $props<{ onFinish?: () => void }>();

  type Step = {
    target: string; // CSS selector of element to highlight
    title: string;
    body: string;
    placement?: 'right' | 'bottom' | 'left' | 'top';
  };

  const steps: Step[] = [
    {
      target: 'a[href="/seller/dashboard"]',
      title: '👋 Selamat datang di Seller Center!',
      body: 'Toko Anda sudah disetujui. Ini panduan singkat untuk semua fitur yang tersedia.',
      placement: 'right'
    },
    {
      target: 'a[href="/seller/dashboard"]',
      title: 'Dashboard',
      body: 'Pusat informasi: pesanan masuk, pendapatan, dan produk aktif. Cek di sini setiap pagi.',
      placement: 'right'
    },
    {
      target: 'a[href="/seller/products"]',
      title: 'Produk Saya',
      body: 'Kelola semua produk Anda — edit, nonaktifkan, atau hapus. Awasi stok yang menipis di sini.',
      placement: 'right'
    },
    {
      target: 'a[href="/seller/products/new"]',
      title: 'Tambah Produk',
      body: 'Klik untuk membuat listing baru. Lengkapi dengan minimal 1 foto, tag, dan harga.',
      placement: 'right'
    },
    {
      target: 'a[href="/seller/orders"]',
      title: 'Pesanan Masuk',
      body: 'Lihat semua pesanan dari pembeli, ubah status, dan input nomor resi setelah dikirim.',
      placement: 'right'
    },
    {
      target: 'a[href="/seller/withdraw"]',
      title: 'Penarikan',
      body: 'Tarik saldo pendapatan ke rekening Anda. Komisi platform sudah otomatis dipotong.',
      placement: 'right'
    },
    {
      target: 'a[href="/seller/profile"]',
      title: 'Profil Toko',
      body: 'Upload foto profil & banner toko, ubah username, alamat, dan data bank. Lengkapi sebelum mulai jualan!',
      placement: 'right'
    },
    {
      target: 'a[href="/chats"]',
      title: 'Chat Pembeli',
      body: 'Balas pertanyaan pembeli secepat mungkin untuk meningkatkan trust & rating toko.',
      placement: 'right'
    },
  ];

  let current = $state(0);
  let rect = $state<{ top: number; left: number; width: number; height: number } | null>(null);
  let tipPos = $state<{ top: number; left: number; placement: string }>({ top: 0, left: 0, placement: 'right' });
  let finishing = $state(false);

  function updateRect() {
    const step = steps[current];
    if (!step) return;
    const el = document.querySelector(step.target) as HTMLElement | null;
    if (!el) { rect = null; return; }
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const r = el.getBoundingClientRect();
    rect = { top: r.top, left: r.left, width: r.width, height: r.height };
    // Compute tooltip position
    const place = step.placement ?? 'right';
    const pad = 16;
    let top = r.top, left = r.left;
    if (place === 'right')  { top = r.top + r.height / 2; left = r.right + pad; }
    if (place === 'bottom') { top = r.bottom + pad; left = r.left + r.width / 2; }
    if (place === 'left')   { top = r.top + r.height / 2; left = r.left - pad; }
    if (place === 'top')    { top = r.top - pad; left = r.left + r.width / 2; }
    tipPos = { top, left, placement: place };
  }

  function next() {
    if (current < steps.length - 1) {
      current++;
      setTimeout(updateRect, 100);
    } else {
      finish();
    }
  }
  function prev() {
    if (current > 0) {
      current--;
      setTimeout(updateRect, 100);
    }
  }
  async function finish() {
    if (finishing) return;
    finishing = true;
    try {
      await apiEndpoints.sellerFinishTour();
      // Update auth cache so tour doesn't re-show
      if (auth.user) auth.set({ ...auth.user, vendor_tour_done: true });
      onFinish();
    } catch (e: any) {
      toast.error(e.message);
      onFinish(); // tetap dismiss biar tidak stuck
    } finally { finishing = false; }
  }

  onMount(() => {
    setTimeout(updateRect, 250);
    const onResize = () => updateRect();
    window.addEventListener('resize', onResize);
    window.addEventListener('scroll', onResize, true);
    return () => {
      window.removeEventListener('resize', onResize);
      window.removeEventListener('scroll', onResize, true);
    };
  });
</script>

<!-- Backdrop (with cutout via SVG mask) -->
<div class="fixed inset-0 z-[60] pointer-events-auto" aria-hidden="true">
  {#if rect}
    {@const r = rect}
    <svg class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <mask id="tour-mask">
          <rect width="100%" height="100%" fill="white"/>
          <rect x={r.left - 6} y={r.top - 6} width={r.width + 12} height={r.height + 12} rx="12" fill="black"/>
        </mask>
      </defs>
      <rect width="100%" height="100%" fill="rgba(0,0,0,0.7)" mask="url(#tour-mask)"/>
      <!-- Glow border around highlight -->
      <rect x={r.left - 6} y={r.top - 6} width={r.width + 12} height={r.height + 12} rx="12" fill="none" stroke="white" stroke-width="3" stroke-opacity="0.85"/>
    </svg>
  {:else}
    <div class="absolute inset-0 bg-black/70"></div>
  {/if}
</div>

<!-- Tooltip card -->
<div class="fixed z-[61] max-w-xs sm:max-w-sm pointer-events-auto"
     style="
       top: {Math.max(80, Math.min(tipPos.top - 100, window.innerHeight - 280))}px;
       left: {Math.max(16, Math.min(tipPos.left, window.innerWidth - 380))}px;
     ">
  <div class="bg-white rounded-2xl shadow-elevated p-5 border border-ink-100">
    <div class="flex items-start justify-between gap-2 mb-2">
      <h3 class="font-display font-bold text-base tracking-tightest">{steps[current].title}</h3>
      <span class="text-[10px] text-ink-400 shrink-0">{current + 1}/{steps.length}</span>
    </div>
    <p class="text-sm text-ink-600 leading-relaxed mb-4 whitespace-pre-line">{steps[current].body}</p>

    <!-- Progress dots -->
    <div class="flex gap-1 mb-4">
      {#each steps as _, i}
        <div class="h-1 flex-1 rounded-full {i <= current ? 'bg-app-primary' : 'bg-ink-100'}"></div>
      {/each}
    </div>

    <div class="flex items-center justify-between gap-2">
      <button on:click={finish} disabled={finishing} class="text-xs text-ink-500 hover:text-ink-950">
        Skip tur
      </button>
      <div class="flex gap-2">
        {#if current > 0}
          <button on:click={prev} class="btn-outline btn-sm"><Icon name="chevron-left" size={12} /> Kembali</button>
        {/if}
        <button on:click={next} disabled={finishing} class="btn-primary btn-sm">
          {current === steps.length - 1 ? 'Selesai' : 'Lanjut'}
          {#if current < steps.length - 1}<Icon name="chevron-right" size={12} />{/if}
        </button>
      </div>
    </div>
  </div>
</div>
