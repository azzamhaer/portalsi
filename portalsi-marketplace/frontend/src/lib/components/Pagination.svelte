<script lang="ts">
  import Icon from './Icon.svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  let { current = 1, last = 1 } = $props<{ current?: number; last?: number }>();

  function go(n: number) {
    if (n < 1 || n > last || n === current) return;
    const u = new URL($page.url);
    u.searchParams.set('page', String(n));
    goto(u.pathname + '?' + u.searchParams.toString());
  }

  function pages(): (number | '...')[] {
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const arr: (number | '...')[] = [1];
    if (current > 3) arr.push('...');
    for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) arr.push(i);
    if (current < last - 2) arr.push('...');
    arr.push(last);
    return arr;
  }
</script>

{#if last > 1}
  <div class="flex items-center justify-center gap-1 mt-8">
    <button on:click={() => go(current - 1)} disabled={current === 1} class="w-9 h-9 grid place-items-center rounded-full hover:bg-ink-100 disabled:opacity-30 disabled:hover:bg-transparent">
      <Icon name="chevron-left" size={16} />
    </button>
    {#each pages() as p}
      {#if p === '...'}
        <span class="w-9 h-9 grid place-items-center text-sm text-ink-400">…</span>
      {:else}
        <button on:click={() => go(p)} class="w-9 h-9 grid place-items-center rounded-full text-sm transition" class:bg-app-primary={p === current} class:text-app-pfg={p === current} class:hover:bg-ink-100={p !== current}>{p}</button>
      {/if}
    {/each}
    <button on:click={() => go(current + 1)} disabled={current === last} class="w-9 h-9 grid place-items-center rounded-full hover:bg-ink-100 disabled:opacity-30 disabled:hover:bg-transparent">
      <Icon name="chevron-right" size={16} />
    </button>
  </div>
{/if}
