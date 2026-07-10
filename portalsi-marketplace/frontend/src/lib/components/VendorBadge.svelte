<script lang="ts">
  import Icon from './Icon.svelte';
  let { badge, size = 14, showLabel = false } = $props<{ badge?: string | null; size?: number; showLabel?: boolean }>();

  const cfg = $derived.by(() => {
    switch (badge) {
      case 'VERIFIED': return { icon: 'badge-check', label: 'Terverifikasi', bg: 'bg-sky-100',     text: 'text-sky-600' };
      case 'MALL':     return { icon: 'building-2',  label: 'Mall',          bg: 'bg-purple-100',  text: 'text-purple-600' };
      case 'STAR':     return { icon: 'sparkles',    label: 'Star Seller',   bg: 'bg-amber-100',   text: 'text-amber-600' };
      default: return null;
    }
  });
</script>

{#if cfg}
  {#if showLabel}
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {cfg.bg} {cfg.text}">
      <Icon name={cfg.icon} size={size - 2} fill="currentColor" />
      {cfg.label}
    </span>
  {:else}
    <span title={cfg.label} class="inline-flex items-center {cfg.text}">
      <Icon name={cfg.icon} size={size} fill="currentColor" />
    </span>
  {/if}
{/if}
