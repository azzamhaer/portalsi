<script lang="ts">
  import { onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import { auth } from '$lib/stores.svelte';
  import { goto } from '$app/navigation';
  import { timeAgo } from '$lib/utils';

  let threads = $state<any[]>([]);
  let loading = $state(true);
  let error = $state('');

  async function load() {
    loading = true;
    error = '';
    try {
      threads = await apiEndpoints.chats();
    } catch (e: any) {
      error = e?.message || 'Gagal memuat chat.';
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    if (!getToken()) {
      loading = false;
      goto('/login?next=/chats');
      return;
    }
    load();
  });
</script>

<svelte:head><title>Chat</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <h1 class="section-title mb-6 sm:mb-8">Chat</h1>

  {#if loading}
    <div class="card text-center text-ink-500 py-10">Memuat...</div>
  {:else if error}
    <div class="card text-center py-12">
      <Icon name="message-circle-warning" size={42} class="mx-auto text-amber-500 mb-3" />
      <h3 class="font-semibold mb-1">Chat belum bisa dimuat</h3>
      <p class="text-sm text-ink-500 mb-4">{error}</p>
      <button type="button" on:click={load} class="btn-outline btn-sm">Coba lagi</button>
    </div>
  {:else if threads.length === 0}
    <div class="card text-center py-16">
      <Icon name="message-circle" size={48} class="mx-auto text-ink-300 mb-3" />
      <h3 class="font-semibold mb-1">Belum ada percakapan</h3>
      <p class="text-sm text-ink-500">Klik "Tanyakan barang ini" di halaman produk untuk mulai chat.</p>
    </div>
  {:else}
    <div class="grid gap-2 max-w-2xl">
      {#each threads as t (t.id)}
        {@const isMyThread = auth.user?.id === t.user_id}
        {@const last = t.messages?.[0]}
        <a href={`/chats/${t.id}`} class="card-hover flex items-center gap-3">
          <img src={t.vendor?.avatar} alt="" class="w-12 h-12 rounded-full object-cover" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <div class="font-semibold text-sm truncate">{isMyThread ? t.vendor?.name : t.user?.name}</div>
              {#if t.last_message_at}<small class="text-xs text-ink-500 shrink-0">{timeAgo(t.last_message_at)}</small>{/if}
            </div>
            {#if t.product}
              <div class="text-xs text-ink-500 truncate flex items-center gap-1 mt-0.5">
                <Icon name="package" size={12} />
                <span>{t.product.name}</span>
              </div>
            {/if}
            {#if last}<div class="text-sm text-ink-600 truncate mt-0.5">{last.message}</div>{/if}
          </div>
        </a>
      {/each}
    </div>
  {/if}
</div>
