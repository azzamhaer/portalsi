<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, confirmDialog } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let tags = $state<any[]>([]);
  let categories = $state<any[]>([]);
  let loading = $state(true);
  let savingTag = $state(false);
  let savingCategory = $state(false);

  let tagForm = $state<any>({ id: null, name: '', slug: '' });
  let categoryForm = $state<any>({
    id: null,
    parent_id: '',
    name: '',
    slug: '',
    tag_slug: '',
    icon: 'tag',
    sort_order: 0,
    is_active: true,
    featured_home: true,
  });
  const iconPresets = [
    'smartphone', 'shirt', 'watch', 'laptop', 'headphones', 'gamepad-2',
    'baby', 'home', 'sofa', 'car', 'bike', 'book-open', 'sparkles',
    'utensils', 'pill', 'dumbbell', 'camera', 'gift', 'shopping-bag', 'tag'
  ];

  const flatCategories = $derived(categories.flatMap((c) => [c, ...(c.children ?? [])]));

  async function load() {
    loading = true;
    try {
      const [tagData, categoryData]: any[] = await Promise.all([
        apiEndpoints.adminTags(),
        apiEndpoints.adminCategories(),
      ]);
      tags = tagData;
      categories = categoryData;
    } catch (e: any) {
      toast.error(e.message);
    } finally {
      loading = false;
    }
  }

  onMount(load);

  function resetTag() {
    tagForm = { id: null, name: '', slug: '' };
  }

  function editTag(t: any) {
    tagForm = { id: t.id, name: t.name, slug: t.slug };
  }

  async function saveTag() {
    if (!tagForm.name.trim()) return toast.warn('Nama tag wajib diisi');
    savingTag = true;
    try {
      await apiEndpoints.adminSaveTag(tagForm.id, { name: tagForm.name, slug: tagForm.slug });
      toast.success(tagForm.id ? 'Tag diperbarui' : 'Tag dibuat');
      resetTag();
      await load();
    } catch (e: any) {
      toast.error(e.message);
    } finally {
      savingTag = false;
    }
  }

  async function deleteTag(id: number) {
    const ok = await confirmDialog.ask({
      title: 'Hapus tag ini?',
      message: 'Produk yang memakai tag ini akan dilepas dari tag tersebut.',
      confirmText: 'Hapus tag',
      tone: 'danger',
    });
    if (!ok) return;
    try {
      await apiEndpoints.adminDeleteTag(id);
      toast.success('Tag dihapus');
      await load();
    } catch (e: any) {
      toast.error(e.message);
    }
  }

  function resetCategory() {
    categoryForm = { id: null, parent_id: '', name: '', slug: '', tag_slug: '', icon: 'tag', sort_order: 0, is_active: true, featured_home: true };
  }

  function editCategory(c: any) {
    categoryForm = {
      id: c.id,
      parent_id: c.parent_id ?? '',
      name: c.name,
      slug: c.slug,
      tag_slug: c.tag_slug ?? c.slug,
      icon: c.icon ?? 'tag',
      sort_order: c.sort_order ?? 0,
      is_active: c.is_active !== false,
      featured_home: c.featured_home !== false,
    };
  }

  async function saveCategory() {
    if (!categoryForm.name.trim()) return toast.warn('Nama kategori wajib diisi');
    savingCategory = true;
    try {
      await apiEndpoints.adminSaveCategory(categoryForm.id, {
        parent_id: categoryForm.parent_id || null,
        name: categoryForm.name,
        slug: categoryForm.slug,
        tag_slug: categoryForm.tag_slug,
        icon: categoryForm.icon,
        sort_order: Number(categoryForm.sort_order ?? 0),
        is_active: !!categoryForm.is_active,
        featured_home: !!categoryForm.featured_home,
      });
      toast.success(categoryForm.id ? 'Kategori diperbarui' : 'Kategori dibuat');
      resetCategory();
      await load();
    } catch (e: any) {
      toast.error(e.message);
    } finally {
      savingCategory = false;
    }
  }

  async function deleteCategory(id: string) {
    const ok = await confirmDialog.ask({
      title: 'Hapus kategori ini?',
      message: 'Kategori yang masih punya produk atau subkategori tidak bisa dihapus.',
      confirmText: 'Hapus kategori',
      tone: 'danger',
    });
    if (!ok) return;
    try {
      await apiEndpoints.adminDeleteCategory(id);
      toast.success('Kategori dihapus');
      await load();
    } catch (e: any) {
      toast.error(e.message);
    }
  }
</script>

<svelte:head><title>Katalog Admin</title></svelte:head>

{#if loading}
  <div class="card py-10 text-center text-ink-500">Memuat...</div>
{:else}
  <div class="space-y-6">
    <div>
      <div class="section-eyebrow mb-2">Admin</div>
      <h1 class="section-title">Kategori & Tag</h1>
      <p class="mt-1 text-sm text-ink-500">Kategori homepage diarahkan ke tag produk. Contoh: kategori Handphone memakai tag handphone, subkategori Samsung memakai tag samsung.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-[360px_1fr]">
      <div class="space-y-5">
        <div class="card">
          <h2 class="mb-4 font-semibold">{tagForm.id ? 'Edit Tag' : 'Buat Tag'}</h2>
          <div class="space-y-3">
            <div><label class="label">Nama tag</label><input bind:value={tagForm.name} class="input" placeholder="Handphone" /></div>
            <div><label class="label">Slug</label><input bind:value={tagForm.slug} class="input" placeholder="handphone" /></div>
            <div class="flex gap-2">
              <button type="button" on:click={saveTag} disabled={savingTag} class="btn-primary btn-sm">{savingTag ? 'Menyimpan...' : 'Simpan Tag'}</button>
              {#if tagForm.id}<button type="button" on:click={resetTag} class="btn-outline btn-sm">Batal</button>{/if}
            </div>
          </div>
        </div>

        <div class="card">
          <h2 class="mb-4 font-semibold">{categoryForm.id ? 'Edit Kategori' : 'Buat Kategori'}</h2>
          <div class="space-y-3">
            <div>
              <label class="label">Parent</label>
              <select bind:value={categoryForm.parent_id} class="input">
                <option value="">Kategori utama</option>
                {#each categories as c}
                  {#if c.id !== categoryForm.id}<option value={c.id}>{c.name}</option>{/if}
                {/each}
              </select>
            </div>
            <div><label class="label">Nama kategori</label><input bind:value={categoryForm.name} class="input" placeholder="Handphone" /></div>
            <div><label class="label">Slug kategori</label><input bind:value={categoryForm.slug} class="input" placeholder="handphone" /></div>
            <div>
              <label class="label">Tag tujuan produk</label>
              <input bind:value={categoryForm.tag_slug} list="admin-tags" class="input" placeholder="handphone" />
              <datalist id="admin-tags">
                {#each tags as t}<option value={t.slug}>{t.name}</option>{/each}
              </datalist>
              <p class="helper">Klik kategori akan membuka produk dengan tag ini.</p>
            </div>
            <div>
              <label class="label">Icon kategori</label>
              <div class="mb-2 flex flex-wrap items-center gap-2 rounded-xl bg-ink-50 px-3 py-2 text-xs text-ink-600">
                <Icon name="external-link" size={12} />
                <span>Cari nama icon di</span>
                <a href="https://lucide.dev/icons/" target="_blank" rel="noreferrer" class="font-semibold text-ink-950 underline">Lucide Icons</a>
                <span>lalu tempel namanya di bawah.</span>
              </div>
              <div class="grid grid-cols-5 gap-2">
                {#each iconPresets as icon}
                  <button type="button" on:click={() => categoryForm.icon = icon} class="grid h-10 place-items-center rounded-xl border transition" class:border-ink-950={categoryForm.icon === icon} class:bg-ink-50={categoryForm.icon === icon} class:border-ink-100={categoryForm.icon !== icon} title={icon}>
                    <Icon name={icon} size={17} />
                  </button>
                {/each}
              </div>
              <input bind:value={categoryForm.icon} class="input input-sm mt-2" placeholder="custom lucide icon, contoh: smartphone" />
            </div>
            <div>
              <label class="label">Urutan</label><input type="number" bind:value={categoryForm.sort_order} class="input" />
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" bind:checked={categoryForm.is_active} /> Aktif</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" bind:checked={categoryForm.featured_home} /> Tampilkan di homepage</label>
            <div class="flex gap-2">
              <button type="button" on:click={saveCategory} disabled={savingCategory} class="btn-primary btn-sm">{savingCategory ? 'Menyimpan...' : 'Simpan Kategori'}</button>
              {#if categoryForm.id}<button type="button" on:click={resetCategory} class="btn-outline btn-sm">Batal</button>{/if}
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-5">
        <div class="card">
          <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-semibold">Daftar kategori</h2>
            <span class="text-xs text-ink-500">{flatCategories.length} item</span>
          </div>
          <div class="space-y-2">
            {#each categories as c}
              <div class="rounded-2xl border border-ink-100 p-3">
                <div class="flex items-center gap-3">
                  <div class="grid h-10 w-10 place-items-center rounded-xl bg-ink-100"><Icon name={c.icon || 'tag'} size={17} /></div>
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <b>{c.name}</b>
                      <span class="pill-ink">#{c.tag_slug || c.slug}</span>
                      {#if !c.is_active}<span class="pill-red">Nonaktif</span>{/if}
                      {#if c.featured_home}<span class="pill-green">Homepage</span>{/if}
                    </div>
                    <div class="text-xs text-ink-500">/{c.slug} · urutan {c.sort_order ?? 0}</div>
                  </div>
                  <button type="button" on:click={() => editCategory(c)} class="btn-outline btn-sm"><Icon name="pencil" size={12} /></button>
                  <button type="button" on:click={() => deleteCategory(c.id)} class="btn-sm bg-red-50 text-red-700 hover:bg-red-100"><Icon name="trash-2" size={12} /></button>
                </div>
                {#if c.children?.length}
                  <div class="mt-3 space-y-2 pl-7">
                    {#each c.children as child}
                      <div class="flex items-center gap-3 rounded-xl bg-ink-50 p-2">
                        <Icon name={child.icon || 'corner-down-right'} size={15} class="text-ink-400" />
                        <div class="min-w-0 flex-1">
                          <div class="text-sm font-medium">{child.name} <span class="text-xs text-ink-500">#{child.tag_slug || child.slug}</span></div>
                        </div>
                        <button type="button" on:click={() => editCategory(child)} class="btn-outline btn-sm"><Icon name="pencil" size={12} /></button>
                        <button type="button" on:click={() => deleteCategory(child.id)} class="btn-sm bg-red-50 text-red-700 hover:bg-red-100"><Icon name="trash-2" size={12} /></button>
                      </div>
                    {/each}
                  </div>
                {/if}
              </div>
            {/each}
          </div>
        </div>

        <div class="card">
          <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-semibold">Daftar tag</h2>
            <span class="text-xs text-ink-500">{tags.length} tag</span>
          </div>
          <div class="flex flex-wrap gap-2">
            {#each tags as t}
              <span class="inline-flex items-center gap-1 rounded-full bg-ink-100 px-3 py-1.5 text-xs">
                #{t.slug}
                <span class="text-ink-400">({t.product_count ?? t.count ?? 0})</span>
                <button type="button" on:click={() => editTag(t)} class="ml-1 text-ink-500 hover:text-ink-950"><Icon name="pencil" size={11} /></button>
                <button type="button" on:click={() => deleteTag(t.id)} class="text-red-600 hover:text-red-800"><Icon name="x" size={11} /></button>
              </span>
            {/each}
          </div>
        </div>
      </div>
    </div>
  </div>
{/if}
