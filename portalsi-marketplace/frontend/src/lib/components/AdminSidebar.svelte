<script lang="ts">
  import Icon from './Icon.svelte';
  import { page } from '$app/stores';
  const items = [
    { href:'/admin',                  label:'Dashboard',     icon:'layout-dashboard' },
    { href:'/admin/users',            label:'Users',         icon:'users' },
    { href:'/admin/vendors',          label:'Vendor & KTP',  icon:'store' },
    { href:'/admin/orders',           label:'Pesanan',       icon:'shopping-cart' },
    { href:'/admin/returns',          label:'Pengembalian',  icon:'undo-2' },
    { href:'/admin/reports',          label:'Laporan',       icon:'flag' },
    { href:'/admin/withdrawals',      label:'Penarikan',     icon:'wallet' },
    { href:'/admin/shipping',         label:'Opsi Kurir',    icon:'truck' },
    { href:'/admin/catalog',          label:'Kategori & Tag', icon:'tags' },
    { href:'/admin/appearance',       label:'Tampilan',      icon:'palette' },
    { href:'/admin/settings',         label:'Pengaturan',    icon:'settings' }
  ];
</script>

<aside class="card !p-2 h-fit">
  <details class="lg:hidden">
    <summary class="flex cursor-pointer items-center justify-between rounded-xl px-3 py-2 text-sm font-semibold">
      Admin menu <Icon name="chevron-down" size={14} />
    </summary>
    <div class="mt-2 grid grid-cols-2 gap-1">
      {#each items as it}
        {@const active = $page.url.pathname === it.href || ($page.url.pathname.startsWith(it.href) && it.href !== '/admin')}
        <a href={it.href} class="flex items-center gap-2 rounded-lg px-2 py-2 text-xs transition"
           class:bg-app-primary={active} class:text-app-pfg={active}
           class:hover:bg-ink-50={!active}>
          <Icon name={it.icon} size={14} />
          <span>{it.label}</span>
        </a>
      {/each}
    </div>
  </details>
  <div class="hidden lg:block px-3 py-2 mb-2 border-b border-ink-100">
    <div class="text-[10px] uppercase tracking-widest text-ink-500">Admin Center</div>
  </div>
  <div class="hidden lg:block">
    {#each items as it}
      {@const active = $page.url.pathname === it.href || ($page.url.pathname.startsWith(it.href) && it.href !== '/admin')}
      <a href={it.href} class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition mb-0.5"
         class:bg-app-primary={active} class:text-app-pfg={active}
         class:hover:bg-ink-50={!active}>
        <Icon name={it.icon} size={16} />
        <span>{it.label}</span>
      </a>
    {/each}
  </div>
</aside>
