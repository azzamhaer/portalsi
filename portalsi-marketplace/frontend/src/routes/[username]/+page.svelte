<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import VendorBadge from '$lib/components/VendorBadge.svelte';
  import FollowButton from '$lib/components/FollowButton.svelte';
  import ReportButton from '$lib/components/ReportButton.svelte';
  let { data } = $props();
  let v = $state(data.vendor);
  let isFollowing = $state(!!data.is_following);
  function onFollowChange({ following, count }: { following: boolean; count: number }) {
    isFollowing = following;
    v = { ...v, followers: count };
  }
</script>

<svelte:head><title>{v.name} — MPSI</title></svelte:head>

<div class="container-x py-6">
  <nav class="flex items-center gap-1 text-xs text-ink-500 mb-5">
    <a href="/" class="hover:text-ink-900">Beranda</a><Icon name="chevron-right" size={12} />
    <a href="/vendors" class="hover:text-ink-900">Toko</a><Icon name="chevron-right" size={12} />
    <span>@{v.username}</span>
  </nav>

  <div class="aspect-[1200/300] rounded-3xl overflow-hidden bg-ink-100 mb-6">
    <img src={v.banner} alt="" class="w-full h-full object-cover" />
  </div>

  <div class="card flex items-center gap-6 flex-wrap mb-10">
    <img src={v.avatar} alt="" class="w-20 h-20 rounded-full -mt-12 border-4 border-white object-cover" />
    <div class="flex-1 min-w-[200px]">
      <h1 class="font-display text-2xl font-bold tracking-tightest flex items-center gap-2 flex-wrap">
        {v.name}
        {#if v.badge}<VendorBadge badge={v.badge} size={18} showLabel />{/if}
        {#if v.is_official}<span class="pill-ink">RESMI</span>{/if}
      </h1>
      <div class="text-sm text-ink-500 flex items-center gap-3 mt-1 flex-wrap">
        <span class="text-ink-400">@{v.username}</span>
        <span class="flex items-center gap-1"><Icon name="map-pin" size={12} /> {v.city}</span>
        <span class="flex items-center gap-1"><Icon name="calendar" size={12} /> Bergabung {new Date(v.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long' })}</span>
      </div>
      <p class="text-sm text-ink-600 mt-3 max-w-2xl">{v.description}</p>
    </div>
    <div class="flex items-center gap-6 text-sm">
      <div><div class="font-display text-xl font-bold tracking-tightest flex items-center gap-1">{#if v.rating > 0}<Icon name="star" size={16} class="text-amber-400" fill="currentColor" />{Number(v.rating).toFixed(1)}{:else}<span class="text-ink-400 text-sm">Belum ada</span>{/if}</div><div class="text-xs text-ink-500">Rating</div></div>
      <div><div class="font-display text-xl font-bold tracking-tightest">{(v.total_sold ?? 0).toLocaleString('id-ID')}</div><div class="text-xs text-ink-500">Penjualan</div></div>
      <div><div class="font-display text-xl font-bold tracking-tightest">{(v.followers ?? 0).toLocaleString('id-ID')}</div><div class="text-xs text-ink-500">Pengikut</div></div>
    </div>
    <div class="flex flex-col gap-2 items-end">
      <FollowButton vendorId={v.id} initialFollowing={isFollowing} initialCount={v.followers ?? 0} onChange={onFollowChange} />
      <ReportButton targetType="VENDOR" targetId={v.id} targetName={v.name} label="Laporkan toko" />
    </div>
  </div>

  <h2 class="section-title mb-6">Semua produk</h2>
  <ProductGrid products={data.products} />
</div>
