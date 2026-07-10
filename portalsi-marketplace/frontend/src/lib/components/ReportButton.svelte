<script lang="ts">
  import Icon from './Icon.svelte';
  import { auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { goto } from '$app/navigation';

  let {
    targetType,
    targetId,
    targetName = '',
    label = 'Laporkan',
    size = 'sm'
  } = $props<{
    targetType: 'PRODUCT' | 'VENDOR';
    targetId: number;
    targetName?: string;
    label?: string;
    size?: 'sm' | 'md';
  }>();

  let open = $state(false);
  let cats = $state<Record<string, string>>({});
  let category = $state('');
  let description = $state('');
  let submitting = $state(false);

  async function openModal() {
    if (!auth.user) { goto('/login'); return; }
    if (auth.user.role === 'ADMIN') { toast.warn('Admin tidak bisa melaporkan'); return; }
    open = true;
    if (Object.keys(cats).length === 0) {
      try { cats = await apiEndpoints.reportCategories(); } catch {}
    }
  }

  async function submit() {
    if (!category) { toast.warn('Pilih jenis laporan'); return; }
    if (description.trim().length < 10) { toast.warn('Deskripsi minimal 10 karakter'); return; }
    submitting = true;
    try {
      await apiEndpoints.submitReport({
        target_type: targetType, target_id: targetId,
        category, description: description.trim()
      });
      toast.success('Laporan terkirim. Admin akan meninjau.');
      open = false;
      category = ''; description = '';
    } catch (e: any) { toast.error(e.message); } finally { submitting = false; }
  }
</script>

<button type="button" on:click={openModal}
        class="inline-flex items-center gap-1.5 text-xs text-ink-500 hover:text-red-600 transition"
        title="Laporkan {targetType === 'PRODUCT' ? 'produk' : 'toko'} ini">
  <Icon name="flag" size={size === 'sm' ? 12 : 14} />
  <span>{label}</span>
</button>

{#if open}
  <div class="fixed inset-0 z-50 bg-black/40 grid place-items-center p-4 animate-fadeIn" on:click={() => open = false} role="dialog" aria-modal="true">
    <div class="bg-white rounded-3xl p-6 max-w-md w-full" on:click|stopPropagation>
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-lg flex items-center gap-2"><Icon name="flag" size={18} /> Laporkan {targetType === 'PRODUCT' ? 'Produk' : 'Toko'}</h3>
        <button on:click={() => open = false} class="w-8 h-8 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="x" size={16} /></button>
      </div>
      {#if targetName}
        <div class="bg-ink-50 p-3 rounded-xl text-sm mb-4">
          <div class="text-xs text-ink-500">Anda melaporkan:</div>
          <div class="font-semibold">{targetName}</div>
        </div>
      {/if}
      <div class="space-y-3">
        <div>
          <label class="label">Jenis pelanggaran</label>
          <select bind:value={category} class="input">
            <option value="">— Pilih jenis —</option>
            {#each Object.entries(cats) as [k, v]}<option value={k}>{v}</option>{/each}
          </select>
        </div>
        <div>
          <label class="label">Deskripsi detail</label>
          <textarea bind:value={description} class="input" rows={5} placeholder="Jelaskan detail pelanggaran yang Anda temui..."></textarea>
          <p class="helper">Min 10 karakter. Laporan palsu/spam akan ditindak.</p>
        </div>
        <div class="flex gap-2 pt-2">
          <button on:click={() => open = false} class="btn-outline btn-md flex-1">Batal</button>
          <button on:click={submit} disabled={submitting} class="btn-primary btn-md flex-1">{submitting ? 'Mengirim…' : 'Kirim Laporan'}</button>
        </div>
      </div>
    </div>
  </div>
{/if}
