<script lang="ts">
  import { onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
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
      const data: any[] = await apiEndpoints.chats();
      threads = data.filter((t) => t.vendor_id === auth.user?.vendor_id);
    } catch (e: any) {
      error = e?.message || 'Gagal memuat chat.';
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    if (!getToken()) {
      loading = false;
      goto('/login?next=/seller/chats');
      return;
    }
    load();
  });
</script>

<svelte:head><title>Chat Pembeli</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <h1 class="section-title mb-6 sm:mb-8">Seller Center</h1>
  <div class="grid lg:grid-cols-[230px_1fr] gap-6">
    <SellerSidebar />
    <section class="min-w-0">
      <div class="mb-5">
        <div class="eyebrow">Inbox</div>
        <h2 class="font-display text-3xl font-bold tracking-tightest">Chat Pembeli</h2>
        <p class="text-sm text-ink-500 mt-1">Kelola pertanyaan pembeli tanpa keluar dari dashboard seller.</p>
      </div>

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
          <p class="text-sm text-ink-500">Chat pembeli akan tampil di sini ketika mereka mengirim pertanyaan.</p>
        </div>
      {:else}
        <div class="card !p-2">
          {#each threads as t (t.id)}
            {@const isMyThread = auth.user?.id === t.user_id}
            {@const last = t.messages?.[0]}
            <a href={`/seller/chats/${t.id}`} class="flex items-center gap-3 rounded-xl p-3 transition hover:bg-ink-50">
              {#if isMyThread}
                <img src={t.vendor?.avatar} alt="" class="w-12 h-12 rounded-full object-cover" />
              {:else}
                <div class="w-12 h-12 rounded-full bg-app-primary text-app-pfg grid place-items-center font-semibold shrink-0">
                  {t.user?.name?.[0]?.toUpperCase() ?? '?'}
                </div>
              {/if}
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-3">
                  <div class="font-semibold text-sm truncate">{isMyThread ? t.vendor?.name : t.user?.name}</div>
                  {#if t.last_message_at}<small class="text-xs text-ink-500 shrink-0">{timeAgo(t.last_message_at)}</small>{/if}
                </div>
                {#if t.product}
                  <div class="text-xs text-ink-500 truncate flex items-center gap-1 mt-0.5">
                    <Icon name="package" size={12} />
                    <span>{t.product.name}</span>
                  </div>
                {/if}
                {#if last}<div class="text-sm text-ink-600 truncate mt-0.5">{last.message || 'Mengirim foto'}</div>{/if}
              </div>
            </a>
          {/each}
        </div>
      {/if}
    </section>
  </div>
</div>
