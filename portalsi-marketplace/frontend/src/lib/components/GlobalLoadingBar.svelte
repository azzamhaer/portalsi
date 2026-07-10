<script lang="ts">
  import { navigating } from '$app/stores';

  let visible = $state(false);
  let finishing = $state(false);
  let hideTimer: ReturnType<typeof setTimeout> | null = null;

  $effect(() => {
    if ($navigating) {
      if (hideTimer) clearTimeout(hideTimer);
      visible = true;
      finishing = false;
    } else if (visible) {
      finishing = true;
      hideTimer = setTimeout(() => {
        visible = false;
        finishing = false;
      }, 260);
    }
  });
</script>

{#if visible}
  <div class="fixed inset-x-0 top-0 z-[80] h-1 overflow-hidden bg-transparent" aria-live="polite" aria-label="Memuat halaman">
    <div
      class="h-full rounded-r-full bg-app-primary shadow-[0_0_18px_var(--app-primary)]"
      class:loading-bar-run={!finishing}
      class:loading-bar-done={finishing}
    ></div>
  </div>
{/if}
