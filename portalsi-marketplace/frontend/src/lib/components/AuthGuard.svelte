<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import { auth } from '$lib/stores.svelte';
  import AdminBlock from './AdminBlock.svelte';

  let { role = null as null | 'BUYER'|'SELLER'|'ADMIN', blockAdmin = false, children } = $props<{
    role?: null | 'BUYER'|'SELLER'|'ADMIN';
    blockAdmin?: boolean;
    children: any;
  }>();

  const ok = $derived(!!auth.user && (!role || auth.user.role === role || auth.user.role === 'ADMIN'));
  const isAdminBlocked = $derived(blockAdmin && auth.user?.role === 'ADMIN');
  let mounted = $state(false);

  onMount(() => {
    mounted = true;
    if (!auth.user) {
      goto('/login?next=' + ($page.url.pathname || '/'));
      return;
    }
    if (role && auth.user.role !== role && auth.user.role !== 'ADMIN') {
      goto('/');
    }
  });
</script>

{#if isAdminBlocked}
  <AdminBlock />
{:else if ok}
  {@render children()}
{:else}
  <div class="container-x py-16">
    <div class="card text-center text-ink-500">
      {mounted ? 'Mengalihkan…' : 'Memeriksa akses…'}
    </div>
  </div>
{/if}
