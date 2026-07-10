<script lang="ts">
  import Icon from './Icon.svelte';
  import { toast } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { goto } from '$app/navigation';

  let { mode = 'create', product = null }: { mode?: 'create'|'edit'; product?: any } = $props();

  let name = $state(product?.name ?? '');
  let description = $state(product?.description ?? '');
  let price = $state(product?.price?.toString() ?? '');
  let original_price = $state(product?.original_price?.toString() ?? '');
  let stock = $state(product?.stock?.toString() ?? '10');
  let weight = $state(product?.weight?.toString() ?? '500');
  let images = $state<string[]>(
    Array.isArray(product?.images) && product.images.length
      ? product.images
      : (product?.image ? [product.image] : [])
  );
  let is_active = $state(product?.is_active ?? true);
  let saving = $state(false);
  let tagInput = $state('');
  let tags = $state<string[]>(product?.tags ?? []);

  // Variants: { "Warna": [{ label:"Merah", image:"..." }], "Ukuran": [{ label:"S" }] }
  let variants = $state<Record<string, any[]>>(
    product?.variants && typeof product.variants === 'object' ? product.variants : {}
  );
  let newAttrName = $state('');

  function addAttr() {
    const n = newAttrName.trim();
    if (!n || variants[n]) return;
    variants = { ...variants, [n]: [] };
    newAttrName = '';
  }
  function removeAttr(key: string) {
    const c = { ...variants }; delete c[key]; variants = c;
  }
  function addOpt(key: string, val: string) {
    const v = val.trim();
    if (!v) return;
    const list = variants[key] ?? [];
    if (list.some((x) => optLabel(x) === v)) return;
    variants = { ...variants, [key]: [...list, { label: v, image: '' }] };
  }
  function removeOpt(key: string, val: any) {
    variants = { ...variants, [key]: variants[key].filter(x => x !== val) };
  }
  function optLabel(opt: any) {
    return typeof opt === 'string' ? opt : (opt?.label ?? '');
  }
  function optImage(opt: any) {
    return typeof opt === 'object' ? (opt?.image ?? '') : '';
  }
  function setOptImage(key: string, opt: any, image: string) {
    variants = { ...variants, [key]: (variants[key] ?? []).map((x) => x === opt ? { label: optLabel(x), image } : x) };
  }
  function onVariantImage(e: any, key: string, opt: any) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 1024 * 1024) { toast.warn(`${file.name} > 1MB, dilewati`); e.target.value = ''; return; }
    const r = new FileReader();
    r.onload = () => setOptImage(key, opt, r.result as string);
    r.readAsDataURL(file);
    e.target.value = '';
  }

  function addTag() {
    const t = tagInput.toLowerCase().trim().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    if (t && !tags.includes(t)) tags = [...tags, t];
    tagInput = '';
  }
  function removeTag(t: string) { tags = tags.filter(x => x !== t); }

  function onFiles(e: any) {
    const files: File[] = Array.from(e.target.files || []);
    if (!files.length) return;
    for (const file of files) {
      if (file.size > 1024 * 1024) { toast.warn(`${file.name} > 1MB, dilewati`); continue; }
      const r = new FileReader();
      r.onload = () => { images = [...images, r.result as string].slice(0, 8); };
      r.readAsDataURL(file);
    }
    e.target.value = '';
  }
  function removeImage(i: number) {
    images = images.filter((_, idx) => idx !== i);
  }
  function moveImage(i: number, dir: number) {
    const j = i + dir;
    if (j < 0 || j >= images.length) return;
    const c = [...images]; [c[i], c[j]] = [c[j], c[i]]; images = c;
  }

  async function submit(e: Event) {
    e.preventDefault();
    if (!name || !price) { toast.warn('Lengkapi data'); return; }
    if (tags.length === 0) { toast.warn('Tambah minimal 1 tag'); return; }
    saving = true;
    try {
      const cleanVariants: any = {};
      for (const [k, v] of Object.entries(variants)) {
        if (Array.isArray(v) && v.length) {
          const opts = v.map((opt) => ({ label: optLabel(opt), image: optImage(opt) })).filter((opt) => opt.label);
          if (opts.length) cleanVariants[k] = opts;
        }
      }
      const body: any = {
        name, description,
        price: +price,
        original_price: original_price ? +original_price : null,
        stock: +stock,
        weight: +weight,
        image: images[0] || '',
        images,
        variants: Object.keys(cleanVariants).length ? cleanVariants : null,
        is_active, tags
      };
      if (mode === 'create') await apiEndpoints.sellerCreateProduct(body);
      else await apiEndpoints.sellerUpdateProduct(product.id, body);
      toast.success(mode === 'create' ? 'Produk dibuat' : 'Produk diperbarui');
      goto('/seller/products');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }
</script>

<form on:submit={submit} class="space-y-5">
  <div><label class="label">Nama Produk</label><input bind:value={name} class="input" required /></div>

  <div>
    <label class="label">Tag <span class="text-red-600">*</span></label>
    <p class="helper mb-2">Gunakan tag untuk mengkategorikan produk. Otomatis lowercase, contoh: <code>elektronik</code>, <code>kemeja-pria</code>.</p>
    <div class="flex flex-wrap gap-1.5 mb-2">
      {#each tags as t}
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-app-primary text-app-pfg text-xs">
          #{t}
          <button type="button" on:click={() => removeTag(t)} class="hover:text-red-300"><Icon name="x" size={10} /></button>
        </span>
      {/each}
    </div>
    <div class="flex gap-2">
      <input bind:value={tagInput} on:keydown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addTag(); } }} class="input flex-1" placeholder="ketik tag lalu Enter" />
      <button type="button" on:click={addTag} class="btn-outline btn-md">Tambah</button>
    </div>
  </div>

  <div class="grid sm:grid-cols-4 gap-3">
    <div><label class="label">Harga (Rp)</label><input type="number" min="1" bind:value={price} class="input" required /></div>
    <div><label class="label">Harga Coret</label><input type="number" min="0" bind:value={original_price} class="input" /></div>
    <div><label class="label">Stok</label><input type="number" min="0" bind:value={stock} class="input" required /></div>
    <div><label class="label">Berat (gram)</label><input type="number" min="1" bind:value={weight} class="input" required /></div>
  </div>

  <div><label class="label">Deskripsi</label><textarea rows={4} bind:value={description} class="input" required /></div>

  <!-- Multi images -->
  <div>
    <label class="label">Gambar Produk (maks 8)</label>
    <p class="helper mb-2">Gambar pertama dipakai sebagai cover. Drag urutan pakai tombol panah.</p>
    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-2">
      {#each images as img, i}
        <div class="relative aspect-square rounded-xl border border-ink-200 bg-ink-50 overflow-hidden group">
          <img src={img} alt="" class="w-full h-full object-cover" />
          {#if i === 0}<span class="absolute top-1 left-1 bg-app-primary text-app-pfg text-[10px] px-2 py-0.5 rounded-full">Cover</span>{/if}
          <div class="absolute inset-x-1 bottom-1 flex items-center justify-between opacity-0 group-hover:opacity-100 transition">
            <div class="flex gap-1">
              <button type="button" on:click={() => moveImage(i, -1)} class="w-6 h-6 grid place-items-center rounded-full bg-white shadow-soft" title="Kiri"><Icon name="chevron-left" size={12} /></button>
              <button type="button" on:click={() => moveImage(i, 1)} class="w-6 h-6 grid place-items-center rounded-full bg-white shadow-soft" title="Kanan"><Icon name="chevron-right" size={12} /></button>
            </div>
            <button type="button" on:click={() => removeImage(i)} class="w-6 h-6 grid place-items-center rounded-full bg-red-500 text-white shadow-soft" title="Hapus"><Icon name="x" size={12} /></button>
          </div>
        </div>
      {/each}
      {#if images.length < 8}
        <label class="aspect-square rounded-xl border-2 border-dashed border-ink-200 grid place-items-center cursor-pointer hover:border-ink-400">
          <Icon name="plus" size={20} class="text-ink-400" />
          <input type="file" accept="image/*" multiple on:change={onFiles} class="hidden" />
        </label>
      {/if}
    </div>
    <p class="helper">Maks 1MB per gambar. Jika kosong, sistem generate placeholder otomatis.</p>
  </div>

  <!-- Variants -->
  <div>
    <label class="label">Varian (opsional)</label>
    <p class="helper mb-2">Tambah atribut seperti <b>Warna</b>, <b>Ukuran</b>, dll. Pembeli bisa pilih saat checkout.</p>
    <div class="space-y-2">
      {#each Object.entries(variants) as [key, opts]}
        <div class="card !p-3 bg-ink-50">
          <div class="flex items-center justify-between mb-2">
            <b class="text-sm">{key}</b>
            <button type="button" on:click={() => removeAttr(key)} class="text-xs text-red-600 hover:underline flex items-center gap-1"><Icon name="trash-2" size={12} /> Hapus atribut</button>
          </div>
          <div class="flex flex-wrap gap-2 mb-2">
            {#each opts as o}
              <div class="w-[92px] rounded-xl border border-ink-200 bg-white p-2 text-center text-xs">
                <label class="mb-1 grid h-12 cursor-pointer place-items-center overflow-hidden rounded-lg bg-ink-50">
                  {#if optImage(o)}
                    <img src={optImage(o)} alt="" class="h-full w-full object-cover" />
                  {:else}
                    <Icon name="image-plus" size={16} class="text-ink-400" />
                  {/if}
                  <input type="file" accept="image/*" on:change={(e) => onVariantImage(e, key, o)} class="hidden" />
                </label>
                <div class="truncate" title={optLabel(o)}>{optLabel(o)}</div>
                <button type="button" on:click={() => removeOpt(key, o)} class="mt-1 inline-flex items-center gap-1 text-[11px] text-red-600"><Icon name="x" size={10} /> Hapus</button>
              </div>
            {/each}
          </div>
          <div class="flex gap-2">
            <input
              on:keydown={(e: any) => { if (e.key === 'Enter') { e.preventDefault(); addOpt(key, e.target.value); e.target.value = ''; } }}
              class="input flex-1 !py-2 !text-sm"
              placeholder="Tambah opsi lalu Enter (contoh: Merah)" />
          </div>
        </div>
      {/each}
    </div>
    <div class="flex gap-2 mt-2">
      <input bind:value={newAttrName} on:keydown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addAttr(); } }} class="input flex-1 !py-2 !text-sm" placeholder="Nama atribut (contoh: Warna, Ukuran)" />
      <button type="button" on:click={addAttr} class="btn-outline btn-sm">Tambah Atribut</button>
    </div>
  </div>

  <label class="flex items-center gap-2 text-sm">
    <input type="checkbox" bind:checked={is_active} />
    Aktifkan produk (tampil di etalase)
  </label>

  <div class="flex gap-2 pt-3 border-t border-ink-100">
    <button disabled={saving} class="btn-primary btn-md">{saving ? 'Menyimpan…' : (mode === 'create' ? 'Buat Produk' : 'Simpan Perubahan')}</button>
    <button type="button" on:click={() => history.back()} class="btn-outline btn-md">Batal</button>
  </div>
</form>
