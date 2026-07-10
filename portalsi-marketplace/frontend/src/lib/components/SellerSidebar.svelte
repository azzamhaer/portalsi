<script lang="ts">
  import Icon from './Icon.svelte';
  import { page } from '$app/stores';
  const items = [
    { href:'/seller/dashboard',    label:'Dashboard',     icon:'layout-dashboard' },
    { href:'/seller/products',     label:'Produk Saya',   icon:'package' },
    { href:'/seller/products/new', label:'Tambah Produk', icon:'plus' },
    { href:'/seller/vouchers',     label:'Voucher',       icon:'ticket-percent' },
    { href:'/seller/orders',       label:'Pesanan Masuk', icon:'shopping-bag' },
    { href:'/seller/withdraw',     label:'Penarikan',     icon:'wallet' },
    { href:'/seller/profile',      label:'Profil Toko',   icon:'store' },
    { href:'/seller/chats',        label:'Chat Pembeli',  icon:'message-circle' }
  ];
</script>

<aside class="card !p-2 h-fit">
  <details class="lg:hidden">
    <summary class="flex cursor-pointer items-center justify-between rounded-xl px-3 py-2 text-sm font-semibold">
      Seller menu <Icon name="chevron-down" size={14} />
    </summary>
    <div class="mt-2 grid grid-cols-2 gap-1">
      {#each items as it}
        {@const active = $page.url.pathname === it.href}
        <a href={it.href} class="flex items-center gap-2 rounded-lg px-2 py-2 text-xs transition"
           class:bg-app-primary={active} class:text-app-pfg={active}
           class:hover:bg-ink-50={!active}>
          <Icon name={it.icon} size={14} />
          <span>{it.label}</span>
        </a>
      {/each}
    </div>
  </details>
  <div class="hidden lg:block">
    {#each items as it}
      {@const active = $page.url.pathname === it.href}
      <a href={it.href} class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition mb-0.5"
         class:bg-app-primary={active} class:text-app-pfg={active}
         class:hover:bg-ink-50={!active}>
        <Icon name={it.icon} size={16} />
        <span>{it.label}</span>
      </a>
    {/each}
  </div>
</aside>
