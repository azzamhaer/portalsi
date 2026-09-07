<script lang="ts">
  import Icon from './Icon.svelte';
  import { t } from '$lib/i18n';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';

  let { vendor = null, onClose = () => {} } = $props<{
    vendor: any;
    onClose?: () => void;
  }>();

  const show = $derived(
    !!vendor?.admin_warning &&
    !vendor?.warning_dismissed_at
  );

  let dismissing = $state(false);
  async function dismiss() {
    dismissing = true;
    try {
      await apiEndpoints.sellerDismissWarning();
      onClose();
    } catch (e: any) { toast.error(e.message); } finally { dismissing = false; }
  }

  const severity = $derived(vendor?.moderation_mode === 'DISABLED' ? 'red' : vendor?.moderation_mode === 'LIMITED' ? 'amber' : 'sky');
</script>

{#if show}
  <div class="fixed inset-0 z-50 bg-black/40 grid place-items-center p-4 animate-fadeIn" role="dialog" aria-modal="true">
    <div class="bg-white rounded-3xl p-6 max-w-md w-full">
      <div class="flex items-start gap-3 mb-4">
        <div class="w-12 h-12 rounded-full bg-{severity}-100 grid place-items-center shrink-0">
          <Icon name="shield-alert" size={24} class="text-{severity}-600" />
        </div>
        <div class="flex-1">
          <h2 class="font-display text-lg font-bold tracking-tightest">{$t('vwp.title')}</h2>
          {#if vendor.moderation_mode === 'LIMITED'}
            <p class="text-xs text-amber-700 mt-1">{$t('vwp.storeNow')} <b>{$t('vwp.restricted')}</b>{$t('vwp.restrictedTail')}</p>
          {:else if vendor.moderation_mode === 'DISABLED'}
            <p class="text-xs text-red-700 mt-1">{$t('vwp.storeNow')} <b>{$t('vwp.hidden')}</b>{$t('vwp.hiddenTail')}</p>
          {/if}
        </div>
      </div>

      <div class="bg-ink-50 p-4 rounded-2xl text-sm text-ink-700 whitespace-pre-line mb-4 max-h-60 overflow-y-auto">
        {vendor.admin_warning}
      </div>

      <p class="text-xs text-ink-500 mb-4">{$t('vwp.pleaseFix')}</p>

      <div class="flex gap-2">
        <button on:click={dismiss} disabled={dismissing} class="btn-primary btn-md flex-1">
          {dismissing ? $t('mk.saving') : $t('vwp.understand')}
        </button>
      </div>
    </div>
  </div>
{/if}
