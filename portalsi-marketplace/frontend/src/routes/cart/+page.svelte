<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import LoginRequired from '$lib/components/LoginRequired.svelte';
  import AdminBlock from '$lib/components/AdminBlock.svelte';
  import { cart, auth, toast, confirmDialog } from '$lib/stores.svelte';
  import { fmtRp } from '$lib/utils';
  import { goto } from '$app/navigation';

  const groups = $derived.by(() => {
    const g: Record<number, any[]> = {};
    cart.items.forEach(i => { (g[i.vendor_id] = g[i.vendor_id] || []).push(i); });
    return g;
  });
  const hasSelectedOutOfStock = $derived(cart.items.some(i => i.checked && i.stock <= 0));
  const ship = $derived(cart.subtotal > 90000 ? 0 : 12000);
  const total = $derived(cart.subtotal + (cart.subtotal > 0 ? ship : 0));

  function checkout() {
    if (!cart.items.some(i => i.checked)) { toast.warn('Pilih minimal 1 produk'); return; }
    if (hasSelectedOutOfStock) { toast.warn('Ada produk stok habis. Hapus atau batal pilih produk tersebut sebelum checkout.'); return; }
    if (!auth.user) { goto('/login?next=/checkout'); return; }
    goto('/checkout');
  }
  async function clearSelected() {
    const ok = await confirmDialog.ask({ title: 'Hapus item terpilih?', message: 'Produk yang dicentang akan dihapus dari keranjang.', confirmText: 'Hapus', tone: 'danger' });
    if (ok) cart.clearChecked();
  }
  async function removeItem(key: string | number) {
    const ok = await confirmDialog.ask({ title: 'Hapus produk?', message: 'Produk ini akan dihapus dari keranjang.', confirmText: 'Hapus', tone: 'danger' });
    if (ok) cart.remove(key);
  }
</script>

<svelte:head><title>Keranjang</title></svelte:head>

{#if !auth.user}
  <LoginRequired
    icon="shopping-bag"
    title="Login untuk melihat keranjang"
    description="Masuk ke akun Anda untuk melihat dan checkout produk di keranjang."
  />
{:else if auth.user.role === 'ADMIN'}
  <AdminBlock title="Admin tidak punya keranjang" description="Akun admin tidak bisa berbelanja. Gunakan akun pembeli." />
{:else}
<div class="container-x py-6 sm:py-8">
  <h1 class="section-title mb-6 sm:mb-8">Keranjang</h1>

  {#if cart.items.length === 0}
    <div class="text-center py-16 sm:py-20">
      <Icon name="shopping-bag" size={56} class="mx-auto text-ink-300 mb-4" />
      <h3 class="text-lg font-semibold mb-1">Keranjang masih kosong</h3>
      <p class="text-sm text-ink-500 mb-5">Mulai belanja produk pilihan Anda.</p>
      <a href="/products" class="btn-primary btn-md">Mulai Belanja</a>
    </div>
  {:else}
    <div class="grid lg:grid-cols-[1fr_360px] gap-6 lg:gap-8 items-start">
      <div class="space-y-4">
        <div class="card flex items-center justify-between">
          <label class="flex items-center gap-3 text-sm">
            <input type="checkbox" checked={cart.items.every(i => i.checked)} on:change={(e: any) => cart.checkAll(e.target.checked)} />
            <span>Pilih Semua ({cart.items.length})</span>
          </label>
          <button on:click={clearSelected} class="text-xs text-red-600 hover:underline flex items-center gap-1">
            <Icon name="trash-2" size={14} /> Hapus
          </button>
        </div>

        {#each Object.entries(groups) as [vid, items]}
          <div class="card">
            <div class="flex items-center gap-2 pb-3 mb-3 border-b border-ink-100">
              <Icon name="store" size={14} class="text-ink-500" />
              <a href={items[0].vendor_username ? `/${items[0].vendor_username}` : `/vendors/${vid}`} class="font-semibold text-sm hover:text-ink-950">{items[0].vendor_name}</a>
            </div>
            {#each items as it (it.cart_key || it.product_id)}
              <div class="flex items-start sm:items-center gap-3 py-3 border-b border-ink-100 last:border-0">
                <input type="checkbox" checked={it.checked} on:change={() => cart.toggleCheck(it.cart_key || it.product_id)} class="mt-2 sm:mt-0" />
                <a href={`/product/${it.product_slug || it.product_id}`} class="shrink-0">
                  <img src={it.image} alt="" class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl object-cover" />
                </a>
                <div class="flex-1 min-w-0">
                  <a href={`/product/${it.product_slug || it.product_id}`} class="font-medium line-clamp-2 hover:text-ink-950 text-sm">{it.name}</a>
                  {#if it.variant_selection}<div class="text-[10px] text-ink-500 mt-0.5">{it.variant_selection}</div>{/if}
                  {#if it.stock <= 0}
                    <div class="mt-1 text-xs font-medium text-red-600">Stok habis. Masukkan ke wishlist untuk notifikasi restock.</div>
                  {/if}
                  <div class="text-sm font-semibold text-ink-950 mt-1">{fmtRp(it.price)}</div>
                  <div class="flex items-center gap-2 mt-2 sm:hidden">
                    <div class="inline-flex items-center border border-ink-200 rounded-full">
                      <button on:click={() => cart.update(it.cart_key || it.product_id, it.qty - 1)} disabled={it.stock <= 0} class="w-7 h-7 grid place-items-center disabled:opacity-40"><Icon name="minus" size={12} /></button>
                      <span class="w-8 text-center text-sm">{it.qty}</span>
                      <button on:click={() => cart.update(it.cart_key || it.product_id, it.qty + 1)} disabled={it.stock <= 0} class="w-7 h-7 grid place-items-center disabled:opacity-40"><Icon name="plus" size={12} /></button>
                    </div>
                    <button on:click={() => removeItem(it.cart_key || it.product_id)} class="ml-auto w-8 h-8 grid place-items-center text-red-600 hover:bg-red-50 rounded-full"><Icon name="trash-2" size={14} /></button>
                  </div>
                </div>
                <div class="hidden sm:inline-flex items-center border border-ink-200 rounded-full">
                  <button on:click={() => cart.update(it.cart_key || it.product_id, it.qty - 1)} disabled={it.stock <= 0} class="w-8 h-8 grid place-items-center hover:bg-ink-50 rounded-l-full disabled:opacity-40"><Icon name="minus" size={12} /></button>
                  <span class="w-9 text-center text-sm">{it.qty}</span>
                  <button on:click={() => cart.update(it.cart_key || it.product_id, it.qty + 1)} disabled={it.stock <= 0} class="w-8 h-8 grid place-items-center hover:bg-ink-50 rounded-r-full disabled:opacity-40"><Icon name="plus" size={12} /></button>
                </div>
                <button on:click={() => removeItem(it.cart_key || it.product_id)} class="hidden sm:grid w-8 h-8 place-items-center text-red-600 hover:bg-red-50 rounded-full">
                  <Icon name="trash-2" size={14} />
                </button>
              </div>
            {/each}
          </div>
        {/each}
      </div>

      <aside class="lg:sticky lg:top-24">
        <div class="card">
          <h3 class="font-semibold mb-4">Ringkasan</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-ink-500">Subtotal</span><span>{fmtRp(cart.subtotal)}</span></div>
            <div class="flex justify-between"><span class="text-ink-500">Estimasi ongkir</span><span>{ship === 0 ? 'GRATIS' : fmtRp(ship)}</span></div>
            <div class="flex justify-between text-base font-semibold pt-3 border-t border-ink-100 mt-3"><span>Total</span><span>{fmtRp(total)}</span></div>
          </div>
          <button on:click={checkout} disabled={cart.subtotal === 0 || hasSelectedOutOfStock} class="btn-primary btn-lg w-full mt-5">
            Checkout <Icon name="arrow-right" size={16} />
          </button>
          <a href="/products" class="btn-ghost btn-md w-full mt-2">Lanjut Belanja</a>
          <div class="flex items-start gap-2 mt-5 text-xs text-ink-500">
            <Icon name="shield-check" size={14} class="text-emerald-600 mt-0.5 shrink-0" />
            <span>Pembayaran dilindungi enkripsi & escrow.</span>
          </div>
        </div>
      </aside>
    </div>
  {/if}
</div>
{/if}
