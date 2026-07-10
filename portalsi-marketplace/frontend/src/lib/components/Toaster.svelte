<script lang="ts">
  import { toasts } from '$lib/stores.svelte';
  import Icon from './Icon.svelte';
</script>

<div class="fixed top-5 right-5 z-[100] flex flex-col gap-2 pointer-events-none max-w-sm">
  {#each toasts.items as t (t.id)}
    <div class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl shadow-elevated bg-white border border-ink-100 animate-slideUp">
      {#if t.type === 'success'}<Icon name="check-circle-2" size={18} class="text-emerald-600 mt-0.5 shrink-0" />
      {:else if t.type === 'error'}<Icon name="alert-circle" size={18} class="text-red-600 mt-0.5 shrink-0" />
      {:else if t.type === 'warn'}<Icon name="alert-triangle" size={18} class="text-amber-600 mt-0.5 shrink-0" />
      {:else}<Icon name="info" size={18} class="text-blue-600 mt-0.5 shrink-0" />
      {/if}
      <div class="text-sm text-ink-900">{t.msg}</div>
    </div>
  {/each}
</div>
