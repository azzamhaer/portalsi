<script lang="ts">
  import { confirmDialog } from '$lib/stores.svelte';
  import Icon from './Icon.svelte';
</script>

{#if confirmDialog.open}
  <div class="fixed inset-0 z-[3000] grid place-items-center bg-black/55 p-4 backdrop-blur-sm animate-fadeIn" role="dialog" aria-modal="true">
    <div class="w-full max-w-sm rounded-[28px] border border-white/70 bg-white/90 p-5 shadow-[0_24px_80px_rgba(0,0,0,0.28)] backdrop-blur-2xl">
      <div class="mb-4 flex items-start gap-3">
        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl {confirmDialog.tone === 'danger' ? 'bg-red-100 text-red-700' : 'bg-ink-100 text-ink-950'}">
          <Icon name={confirmDialog.tone === 'danger' ? 'alert-triangle' : 'help-circle'} size={20} />
        </div>
        <div class="min-w-0">
          <h2 class="text-base font-bold text-ink-950">{confirmDialog.title}</h2>
          {#if confirmDialog.message}
            <p class="mt-1 text-sm leading-relaxed text-ink-600">{confirmDialog.message}</p>
          {/if}
        </div>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <button type="button" on:click={() => confirmDialog.cancel()} class="btn-outline btn-md">{confirmDialog.cancelText}</button>
        <button
          type="button"
          on:click={() => confirmDialog.confirm()}
          class="{confirmDialog.tone === 'danger' ? 'btn-danger' : 'btn-primary'} btn-md"
        >
          {confirmDialog.confirmText}
        </button>
      </div>
    </div>
  </div>
{/if}
