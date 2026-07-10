<script lang="ts">
  import Icon from './Icon.svelte';

  let { categories = [] } = $props<{ categories: any[] }>();

  function hrefFor(c: any) {
    const slug = c.tag_slug || c.slug;
    return c.tag_slug ? `/products?tag=${encodeURIComponent(slug)}` : `/category/${encodeURIComponent(slug)}`;
  }

  const iconMap: Record<string, string> = {
    elektronik: 'smartphone',
    'fashion-pria': 'shirt',
    'fashion-wanita': 'gem',
    kecantikan: 'sparkles',
    kesehatan: 'heart-pulse',
    olahraga: 'dumbbell',
    otomotif: 'car',
    makanan: 'utensils',
    buku: 'book-open',
    rumah: 'sofa',
    mainan: 'baby',
    voucher: 'ticket-percent'
  };

  function iconFor(c: any) {
    const key = String(c.slug || c.tag_slug || c.id || '').toLowerCase();
    if (iconMap[key]) return iconMap[key];
    if (c.icon && !String(c.icon).startsWith('data:')) return c.icon;
    return 'layout-grid';
  }
</script>

{#if categories?.length}
  <section>
    <div class="mb-4 flex items-end justify-between gap-3">
      <div>
        <div class="section-eyebrow mb-2">Kategori</div>
        <h2 class="section-title">Pilih kebutuhan Anda</h2>
      </div>
      <a href="/products" class="hidden items-center gap-1 text-sm text-ink-700 hover:text-ink-950 sm:flex">
        Semua produk <Icon name="arrow-right" size={14} />
      </a>
    </div>

    <div class="no-scrollbar -mx-4 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:px-0 md:grid-cols-3 lg:grid-cols-4">
      {#each categories as c (c.id)}
        <a href={hrefFor(c)} class="group min-w-[210px] rounded-2xl border border-ink-100 bg-white p-4 transition hover:border-ink-300 hover:shadow-elevated sm:min-w-0">
          <div class="mb-3 flex items-start justify-between gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-2xl bg-ink-100 text-ink-900">
              <Icon name={iconFor(c)} size={19} />
            </div>
            <Icon name="arrow-up-right" size={16} class="text-ink-300 transition group-hover:text-ink-900" />
          </div>
          <div class="font-semibold text-ink-950">{c.name}</div>

          {#if c.children?.length}
            <div class="mt-3 flex flex-wrap gap-1.5">
              {#each c.children.slice(0, 4) as child}
                <span class="rounded-full bg-ink-50 px-2 py-1 text-[11px] text-ink-600">{child.name}</span>
              {/each}
            </div>
          {/if}
        </a>
      {/each}
    </div>
  </section>
{/if}
