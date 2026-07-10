<script lang="ts">
  import { onMount, onDestroy, tick } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import VendorBadge from '$lib/components/VendorBadge.svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import { auth, toast } from '$lib/stores.svelte';
  import { goto } from '$app/navigation';
  import { fmtRp } from '$lib/utils';
  import { page } from '$app/stores';

  let thread = $state<any>(null);
  let loading = $state(true);
  let msg = $state('');
  let pendingImage = $state<string | null>(null);
  let sending = $state(false);
  let scroller: HTMLDivElement | null = $state(null);
  let poller: any;

  const id = $derived($page.params.id ?? '0');
  const isMyThread = $derived(thread && auth.user?.id === thread.user_id); // true = pembeli (user_id punya thread), false = seller (vendor owner)
  // Counterpart untuk header thread
  const counterpart = $derived.by(() => {
    if (!thread) return null;
    return isMyThread ? thread.vendor : thread.user;
  });

  async function load(scroll = false) {
    try {
      thread = await apiEndpoints.chatThread(+id);
      if (scroll) { await tick(); if (scroller) scroller.scrollTop = scroller.scrollHeight; }
    } catch (e: any) { toast.error(e.message); }
  }

  onMount(async () => {
    if (!getToken()) { goto('/login'); return; }
    await load(true);
    loading = false;
    poller = setInterval(() => load(false), 5000);
  });
  onDestroy(() => clearInterval(poller));

  function onPickImage(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (file.size > 1024 * 1024) { toast.error('Maks 1MB'); return; }
    const r = new FileReader();
    r.onload = () => { pendingImage = String(r.result); };
    r.readAsDataURL(file);
    (e.target as HTMLInputElement).value = '';
  }

  async function send(e: Event) {
    e.preventDefault();
    if (!msg.trim() && !pendingImage) return;
    sending = true;
    try {
      await apiEndpoints.sendMessage(+id, msg.trim(), pendingImage);
      msg = ''; pendingImage = null;
      await load(true);
    } catch (e: any) { toast.error(e.message); } finally { sending = false; }
  }
</script>

<svelte:head><title>Chat</title></svelte:head>

<div class="container-x py-4 sm:py-6">
  <a href="/chats" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-950 mb-4">
    <Icon name="arrow-left" size={14} /> Semua chat
  </a>

  {#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
  {:else if thread}
    <div class="card !p-0 overflow-hidden flex flex-col" style="height: calc(100vh - 200px); min-height: 500px;">
      <!-- Header thread: tampilkan counterpart -->
      <div class="flex items-center gap-3 p-4 border-b border-ink-100 bg-white">
        {#if isMyThread}
          <!-- User saya = pembeli; counterpart = vendor -->
          <a href={thread.vendor?.username ? `/${thread.vendor.username}` : `/vendors/${thread.vendor?.id}`} class="flex items-center gap-3 flex-1 min-w-0">
            <img src={thread.vendor?.avatar} alt="" class="w-10 h-10 rounded-full object-cover" />
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-sm truncate flex items-center gap-1.5">
                {thread.vendor?.name}
                {#if thread.vendor?.badge}<VendorBadge badge={thread.vendor.badge} size={12} />{/if}
              </div>
              <div class="text-xs text-ink-500 truncate">@{thread.vendor?.username} · Toko</div>
            </div>
          </a>
        {:else}
          <!-- User saya = seller; counterpart = pembeli -->
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="w-10 h-10 rounded-full bg-app-primary text-app-pfg grid place-items-center font-semibold shrink-0">
              {thread.user?.name?.[0]?.toUpperCase() ?? '?'}
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-sm truncate">{thread.user?.name}</div>
              <div class="text-xs text-ink-500 truncate">
                {thread.user?.email}{#if thread.user?.phone} · {thread.user.phone}{/if}
              </div>
            </div>
          </div>
        {/if}
      </div>

      {#if thread.product}
        <a href={`/product/${thread.product.slug || thread.product.id}`} class="flex items-center gap-3 p-3 mx-4 mt-3 bg-ink-50 rounded-xl hover:bg-ink-100 transition shrink-0">
          <img src={thread.product.image} alt="" class="w-12 h-12 rounded-lg object-cover" />
          <div class="flex-1 min-w-0">
            <div class="text-xs font-medium line-clamp-1">{thread.product.name}</div>
            <div class="text-sm font-bold">{fmtRp(thread.product.price)}</div>
          </div>
          <Icon name="chevron-right" size={14} class="text-ink-400" />
        </a>
      {/if}

      <!-- Messages -->
      <div bind:this={scroller} class="flex-1 overflow-y-auto p-4 space-y-3">
        {#each thread.messages ?? [] as m (m.id)}
          {@const mine = m.sender_user_id === auth.user?.id}
          <div class="flex {mine ? 'justify-end' : 'justify-start'}">
            <div class="max-w-[80%] sm:max-w-[70%] flex gap-2 items-end {mine ? 'flex-row-reverse' : ''}">
              {#if !mine}
                <div class="w-7 h-7 rounded-full bg-ink-300 text-white grid place-items-center text-[10px] font-semibold shrink-0 self-end">
                  {(m.sender?.name ?? thread.vendor?.name ?? 'U')[0]?.toUpperCase()}
                </div>
              {/if}
              <div class="space-y-1">
                {#if m.image_url}
                  <img src={m.image_url} alt="" class="max-w-[240px] rounded-2xl border border-ink-100" />
                {/if}
                {#if m.message}
                  <div class="px-3 py-2 rounded-2xl text-sm
                              {mine ? 'bg-app-primary text-app-pfg rounded-br-md' : 'bg-ink-100 text-ink-900 rounded-bl-md'}">
                    {m.message}
                  </div>
                {/if}
                <div class="text-[10px] text-ink-400 {mine ? 'text-right' : 'text-left'}">
                  {new Date(m.created_at).toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' })}
                </div>
              </div>
            </div>
          </div>
        {/each}
        {#if thread.messages?.length === 0}
          <div class="text-center text-ink-400 text-sm py-10">
            <Icon name="message-circle" size={32} class="mx-auto mb-2 text-ink-300" />
            Belum ada pesan. Mulai percakapan!
          </div>
        {/if}
      </div>

      <!-- Image preview -->
      {#if pendingImage}
        <div class="px-3 pt-2 border-t border-ink-100 flex items-center gap-2">
          <img src={pendingImage} alt="" class="w-14 h-14 rounded-lg object-cover" />
          <span class="text-xs text-ink-500">Foto siap dikirim</span>
          <button type="button" on:click={() => pendingImage = null} class="ml-auto text-xs text-red-600 hover:underline">Hapus</button>
        </div>
      {/if}

      <!-- Input -->
      <form on:submit={send} class="p-3 border-t border-ink-100 flex gap-2 items-center">
        <label class="w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100 cursor-pointer shrink-0" title="Lampirkan foto">
          <Icon name="image" size={18} class="text-ink-500" />
          <input type="file" accept="image/*" on:change={onPickImage} class="hidden" />
        </label>
        <input bind:value={msg} placeholder="Ketik pesan…" class="input flex-1 !rounded-full" />
        <button type="submit" disabled={sending || (!msg.trim() && !pendingImage)} class="btn-primary btn-md !px-4 shrink-0"><Icon name="send" size={16} /></button>
      </form>
    </div>
  {/if}
</div>
