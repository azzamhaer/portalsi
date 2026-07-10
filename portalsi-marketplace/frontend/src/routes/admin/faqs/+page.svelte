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
      items = await apiEndpoints.adminFaqs();
    } catch (e: any) { toast.error(e.message); } finally { loading = false; }
  });

  function add() {
    items = [...items, { section: 'Umum', question: '', answer: '', is_active: true }];
  }
  function remove(i: number) {
    items = items.filter((_, idx) => idx !== i);
  }
  async function save() {
    saving = true;
    try {
      await apiEndpoints.adminSaveFaqs(items);
      toast.success('FAQ disimpan');
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
  <div class="flex items-center justify-between mb-4">
    <h3 class="font-semibold">FAQ Halaman Bantuan</h3>
    <div class="flex gap-2">
      <button on:click={add} class="btn-outline btn-sm"><Icon name="plus" size={14} /> Tambah</button>
      <button on:click={save} disabled={saving} class="btn-primary btn-sm">{saving ? 'Menyimpan…' : 'Simpan Semua'}</button>
    </div>
  </div>

  {#if items.length === 0}
    <div class="card text-center text-ink-500 py-10">
      Belum ada FAQ. Klik <b>Tambah</b> untuk menambah pertanyaan baru.
    </div>
  {:else}
    <div class="space-y-3">
      {#each items as f, i}
        <div class="card">
          <div class="flex items-start justify-between gap-2 mb-3">
            <div class="text-xs text-ink-500">#{i + 1}</div>
            <div class="flex items-center gap-1">
              <button on:click={() => move(i, -1)} class="w-7 h-7 grid place-items-center rounded-full hover:bg-ink-100" title="Naik"><Icon name="arrow-up" size={12} /></button>
              <button on:click={() => move(i, 1)} class="w-7 h-7 grid place-items-center rounded-full hover:bg-ink-100" title="Turun"><Icon name="arrow-down" size={12} /></button>
              <label class="flex items-center gap-1 text-xs text-ink-600">
                <input type="checkbox" bind:checked={f.is_active} /> Aktif
              </label>
              <button on:click={() => remove(i)} class="w-7 h-7 grid place-items-center rounded-full text-red-600 hover:bg-red-50" title="Hapus"><Icon name="trash-2" size={12} /></button>
            </div>
          </div>
          <div class="grid sm:grid-cols-[200px_1fr] gap-3">
            <div>
              <label class="label">Section</label>
              <input bind:value={f.section} class="input" list="faq-sections" placeholder="Pesanan / Pembayaran / dll" />
              <datalist id="faq-sections">
                <option value="Pesanan" />
                <option value="Pembayaran" />
                <option value="Pengiriman" />
                <option value="Akun & Toko" />
                <option value="Umum" />
              </datalist>
            </div>
            <div>
              <label class="label">Pertanyaan</label>
              <input bind:value={f.question} class="input" placeholder="Contoh: Bagaimana cara melacak pesanan?" />
            </div>
          </div>
          <div class="mt-3">
            <label class="label">Jawaban</label>
            <textarea bind:value={f.answer} class="input" rows={3} placeholder="Jawaban lengkap…"></textarea>
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
