<script lang="ts">
  import { onMount } from 'svelte';
  import { t } from '$lib/i18n';
  import { get } from 'svelte/store';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp } from '$lib/utils';

  let items = $state<any[]>([]);
  let loading = $state(true);
  let saving = $state(false);

  onMount(async () => {
    try { items = await apiEndpoints.adminShipping(); }
    finally { loading = false; }
    if (items.length === 0) {
      items = [
        { name:'JNE Reguler', eta:'2-4 hari', cost:12000, is_active:true },
        { name:'J&T Express', eta:'2-3 hari', cost:14000, is_active:true },
      ];
    }
  });

  function add() { items = [...items, { name: '', eta: '', cost: 0, is_active: true }]; }
  function remove(i: number) { items = items.filter((_, idx) => idx !== i); }

  async function save() {
    saving = true;
    try {
      await apiEndpoints.adminSaveShipping(items.filter(it => it.name && it.eta && it.cost >= 0));
      toast.success(get(t)('adm.shipSaved'));
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }
</script>

<div class="card flex items-center justify-between mb-4 flex-wrap gap-2">
  <h3 class="font-semibold">{$t('adm.shipOptions')}</h3>
  <button on:click={add} class="btn-outline btn-sm"><Icon name="plus" size={12} /> {$t('mk.add')}</button>
</div>

{#if loading}<div class="card text-center text-ink-500 py-10">{$t('wl.loading')}</div>
{:else}
  <div class="space-y-2">
    {#each items as it, i (i)}
      <div class="card flex items-center gap-3 flex-wrap">
        <input bind:value={it.name} class="input-sm input flex-1 min-w-[160px]" placeholder={$t('adm.shipNamePlaceholder')} />
        <input bind:value={it.eta} class="input-sm input w-32" placeholder={$t('adm.estPlaceholder')} />
        <input type="number" bind:value={it.cost} class="input-sm input w-32" placeholder={$t('adm.costPlaceholder')} />
        <span class="text-xs text-ink-500 w-24">{fmtRp(+it.cost || 0)}</span>
        <label class="flex items-center gap-1 text-xs"><input type="checkbox" bind:checked={it.is_active} />{$t('sv.active')}</label>
        <button on:click={() => remove(i)} class="text-red-600 hover:bg-red-50 w-8 h-8 grid place-items-center rounded"><Icon name="trash-2" size={14} /></button>
      </div>
    {/each}
  </div>
  <div class="mt-4 flex gap-2"><button on:click={save} disabled={saving} class="btn-primary btn-md">{saving ? $t('mk.saving') : $t('mk.saveAll')}</button></div>
{/if}
