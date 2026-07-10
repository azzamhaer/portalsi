<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let items = $state<any[]>([]);
  let loading = $state(true);
  let saving = $state(false);

  onMount(async () => {
    try {
      items = await apiEndpoints.adminPaymentMethods();
    } catch (e: any) { toast.error(e.message); } finally { loading = false; }
  });

  function add() {
    items = [...items, {
      code: 'NEW' + Math.floor(Math.random() * 1000),
      name: '', group: 'Virtual Account', icon: '', color: '#0a0a0a',
      fee_pct: 0, fee_flat: 0, is_active: true
    }];
  }
  function remove(i: number) {
    if (!confirm('Hapus metode pembayaran ini?')) return;
    items = items.filter((_, idx) => idx !== i);
  }
  async function uploadIcon(i: number, file: File) {
    if (file.size > 500_000) { toast.error('Maks 500KB'); return; }
    const reader = new FileReader();
    reader.onload = () => { items[i].icon = String(reader.result); };
    reader.readAsDataURL(file);
  }
  async function save() {
    if (items.some((x) => !x.code || !x.name)) { toast.error('Lengkapi code & nama'); return; }
    saving = true;
    try {
      await apiEndpoints.adminSavePaymentMethods(items);
      toast.success('Metode pembayaran disimpan');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }
  function move(i: number, dir: number) {
    const j = i + dir;
    if (j < 0 || j >= items.length) return;
    const copy = [...items];
    [copy[i], copy[j]] = [copy[j], copy[i]];
    items = copy;
  }
</script>

{#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
    <h3 class="font-semibold">Metode Pembayaran</h3>
    <div class="flex gap-2">
      <button on:click={add} class="btn-outline btn-sm"><Icon name="plus" size={14} /> Tambah</button>
      <button on:click={save} disabled={saving} class="btn-primary btn-sm">{saving ? 'Menyimpan…' : 'Simpan Semua'}</button>
    </div>
  </div>

  <p class="text-xs text-ink-500 mb-4">Tetapkan opsi pembayaran yang ditampilkan ke pembeli di halaman <code>/payment-info</code> dan saat checkout. Grouping otomatis berdasarkan kolom <b>Kategori</b>.</p>

  {#if items.length === 0}
    <div class="card text-center text-ink-500 py-10">
      Belum ada metode pembayaran. Klik <b>Tambah</b> untuk menambah.
    </div>
  {:else}
    <div class="space-y-3">
      {#each items as m, i}
        <div class="card">
          <div class="flex items-start justify-between gap-2 mb-3">
            <div class="text-xs text-ink-500">#{i + 1}</div>
            <div class="flex items-center gap-1">
              <button on:click={() => move(i, -1)} class="w-7 h-7 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="arrow-up" size={12} /></button>
              <button on:click={() => move(i, 1)} class="w-7 h-7 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="arrow-down" size={12} /></button>
              <label class="flex items-center gap-1 text-xs text-ink-600">
                <input type="checkbox" bind:checked={m.is_active} /> Aktif
              </label>
              <button on:click={() => remove(i)} class="w-7 h-7 grid place-items-center rounded-full text-red-600 hover:bg-red-50"><Icon name="trash-2" size={12} /></button>
            </div>
          </div>
          <div class="grid sm:grid-cols-[80px_1fr] gap-3 items-start">
            <div>
              <label class="label">Icon</label>
              <div class="w-16 h-16 rounded-xl border border-ink-200 grid place-items-center overflow-hidden bg-ink-50">
                {#if m.icon}<img src={m.icon} alt="" class="w-full h-full object-contain" />
                {:else}<span class="text-[10px] text-ink-400 text-center px-1">{m.code.slice(0,4)}</span>{/if}
              </div>
              <input type="file" accept="image/*" on:change={(e: any) => uploadIcon(i, e.target.files?.[0])} class="text-[10px] mt-1 w-16" />
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
              <div><label class="label">Kode (unik)</label><input bind:value={m.code} class="input font-mono text-xs" placeholder="BCAVA" /></div>
              <div><label class="label">Nama metode</label><input bind:value={m.name} class="input" placeholder="BCA Virtual Account" /></div>
              <div>
                <label class="label">Kategori</label>
                <input bind:value={m.group} class="input" list="pm-groups" placeholder="Virtual Account / E-Wallet / QRIS / dst" />
                <datalist id="pm-groups">
                  <option value="Virtual Account" />
                  <option value="E-Wallet" />
                  <option value="QRIS" />
                  <option value="Convenience Store" />
                  <option value="Credit Card" />
                </datalist>
              </div>
              <div>
                <label class="label">Warna brand</label>
                <input type="color" bind:value={m.color} class="w-full h-10 rounded-xl border border-ink-200" />
              </div>
              <div><label class="label">Fee (%)</label><input type="number" step="0.01" bind:value={m.fee_pct} class="input" /></div>
              <div><label class="label">Fee tetap (Rp)</label><input type="number" bind:value={m.fee_flat} class="input" /></div>
            </div>
          </div>
        </div>
      {/each}
    </div>
    <div class="mt-4 flex gap-2 justify-end">
      <button on:click={add} class="btn-outline btn-sm"><Icon name="plus" size={14} /> Tambah</button>
      <button on:click={save} disabled={saving} class="btn-primary btn-sm">{saving ? 'Menyimpan…' : 'Simpan Semua'}</button>
    </div>
  {/if}
{/if}
