<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import MapPicker from '$lib/components/MapPicker.svelte';
  import AddressFields from '$lib/components/AddressFields.svelte';
  import { auth, cart, wishlist, toast, confirmDialog } from '$lib/stores.svelte';
  import { apiEndpoints, setToken } from '$lib/api';
  import { fmtRp } from '$lib/utils';
  import { t, lang, setLang } from '$lib/i18n';
  import { get } from 'svelte/store';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';

  const isAdmin = $derived(auth.user?.role === 'ADMIN');

  let name = $state(''), phone = $state(''), saving = $state(false);
  let addresses = $state<any[]>([]);
  let editing = $state<any>(null);
  let addressFormOpen = $state(false);
  let formAddr = $state<any>({ recipient:'', phone:'', country:'Indonesia', province:'', city:'', district:'', village:'', full_address:'', postal_code:'', address_note:'', latitude:null, longitude:null, is_default:false });
  let wallet = $state<any>(null);
  let walletLoading = $state(false);
  let wdAmount = $state('');
  let wdBankName = $state('');
  let wdBankAccount = $state('');
  let wdBankHolder = $state('');
  let wdSaving = $state(false);

  // Change password state
  let cpOld = $state(''), cpNew = $state(''), cpConfirm = $state(''), cpSaving = $state(false);

  // Change email state
  let newEmail = $state(''), ceSaving = $state(false);

  onMount(async () => {
    if (!auth.user) { goto('/login?next=/profile'); return; }
    name = auth.user.name; phone = auth.user.phone || '';
    if (!isAdmin) {
      try { addresses = await apiEndpoints.addresses(); } catch {}
      await loadWallet();
    }
  });

  async function loadWallet() {
    walletLoading = true;
    try { wallet = await apiEndpoints.userWallet(); }
    catch {}
    finally { walletLoading = false; }
  }

  async function requestUserWithdraw(e: Event) {
    e.preventDefault();
    wdSaving = true;
    try {
      await apiEndpoints.userRequestWithdraw({
        amount: Number(wdAmount),
        bank_name: wdBankName,
        bank_account: wdBankAccount,
        bank_holder: wdBankHolder,
      });
      wdAmount = wdBankName = wdBankAccount = wdBankHolder = '';
      toast.success(get(t)('pf.withdrawSent'));
      await loadWallet();
    } catch (e: any) { toast.error(e.message); } finally { wdSaving = false; }
  }

  async function save(e: Event) {
    e.preventDefault();
    saving = true;
    try {
      const u = await apiEndpoints.updateProfile({ name, phone });
      auth.set(u);
      toast.success(get(t)('pf.saved'));
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }
  async function logout() {
    try { await apiEndpoints.logout(); } catch {}
    setToken(null);
    auth.clear();
    cart.clear();
    wishlist.clear();
    goto('/');
  }

  async function changePassword(e: Event) {
    e.preventDefault();
    if (cpNew !== cpConfirm) { toast.error(get(t)('rp.mismatch')); return; }
    if (cpNew.length < 8) { toast.error(get(t)('pf.pwMin8')); return; }
    cpSaving = true;
    try {
      await apiEndpoints.changePassword(cpOld, cpNew);
      cpOld = cpNew = cpConfirm = '';
      toast.success(get(t)('pf.pwChanged'));
    } catch (e: any) { toast.error(e.message); } finally { cpSaving = false; }
  }

  async function changeEmail(e: Event) {
    e.preventDefault();
    if (!newEmail.includes('@')) { toast.error(get(t)('pf.emailInvalid')); return; }
    ceSaving = true;
    try {
      await apiEndpoints.requestChangeEmail(newEmail);
      newEmail = '';
      toast.success(get(t)('pf.emailLinkSent'));
    } catch (e: any) { toast.error(e.message); } finally { ceSaving = false; }
  }

  function openAddrForm(a: any | null) {
    editing = a;
    addressFormOpen = true;
    formAddr = a ? { country: 'Indonesia', ...a } : { recipient: name, phone, country:'Indonesia', province:'', city:'', district:'', village:'', full_address:'', postal_code:'', address_note:'', latitude: null, longitude: null, is_default: addresses.length === 0 };
  }

  async function saveAddr(e: Event) {
    e.preventDefault();
    if (formAddr.latitude == null || formAddr.longitude == null) {
      toast.warn(get(t)('pf.pinFirst'));
      return;
    }
    try {
      if (editing?.id) await apiEndpoints.updateAddress(editing.id, formAddr);
      else await apiEndpoints.saveAddress(formAddr);
      addresses = await apiEndpoints.addresses();
      editing = null;
      addressFormOpen = false;
      toast.success(get(t)('pf.addrSaved'));
    } catch (e: any) { toast.error(e.message); }
  }
  async function delAddr(id: number) {
    const ok = await confirmDialog.ask({
      title: get(t)('pf.deleteAddrQ'),
      message: get(t)('pf.deleteAddrMsg'),
      confirmText: get(t)('pf.deleteAddr'),
      tone: 'danger',
    });
    if (!ok) return;
    try { await apiEndpoints.deleteAddress(id); addresses = await apiEndpoints.addresses(); }
    catch (e: any) { toast.error(e.message); }
  }

  function addressQuery(a: any) {
    return [a.village, a.district, a.city, a.province, a.postal_code, 'Indonesia'].filter(Boolean).join(', ');
  }
</script>

<svelte:head><title>Profil</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <h1 class="section-title mb-6 sm:mb-8">{$t('nav.profile')}</h1>

  <div class="card mb-6">
    <h3 class="font-semibold mb-3 flex items-center gap-2"><Icon name="globe" size={16} /> {$t('set.language')}</h3>
    <div class="flex gap-2">
      <button type="button" on:click={() => setLang('id')} class="px-4 py-2 rounded-lg text-sm font-semibold border {$lang === 'id' ? 'bg-black text-white border-black' : 'border-gray-300 text-gray-600'}">{$t('lang.id')}</button>
      <button type="button" on:click={() => setLang('en')} class="px-4 py-2 rounded-lg text-sm font-semibold border {$lang === 'en' ? 'bg-black text-white border-black' : 'border-gray-300 text-gray-600'}">{$t('lang.en')}</button>
    </div>
    <p class="text-xs text-gray-500 mt-2">{$t('set.languageNote')}</p>
  </div>

  {#if isAdmin}
    <!-- Admin: hanya panel profile, tanpa sidebar buyer -->
    <div class="max-w-2xl mx-auto space-y-5">
      <div class="card">
        <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="user" size={16} /> {$t('pf.accountData')}</h3>
        <form on:submit={save} class="space-y-4">
          <div><label class="label">{$t('pf.name')}</label><input bind:value={name} class="input" /></div>
          <div><label class="label">{$t('pf.currentEmail')}</label><input value={auth.user?.email ?? ''} disabled class="input bg-ink-50" /></div>
          <div><label class="label">{$t('auth.phone')}</label><input bind:value={phone} class="input" /></div>
          <button disabled={saving} class="btn-primary btn-md">{saving ? $t('pf.saving') : $t('pf.save')}</button>
        </form>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="mail" size={16} /> {$t('pf.changeEmail')}</h3>
        <p class="text-xs text-ink-500 mb-3">{$t('pf.emailNote')}</p>
        <form on:submit={changeEmail} class="flex gap-2">
          <input type="email" bind:value={newEmail} class="input flex-1" placeholder={$t('pf.newEmailPlaceholder')} required />
          <button disabled={ceSaving} class="btn-primary btn-md">{ceSaving ? $t('pf.sending') : $t('pf.sendConfirm')}</button>
        </form>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="lock" size={16} /> {$t('pf.changePw')}</h3>
        <p class="text-xs text-ink-500 mb-3">{$t('pf.pwNote')}<a href="/forgot-password" class="link">{$t('pf.resetViaEmail')}</a>.</p>
        <form on:submit={changePassword} class="space-y-3 max-w-md">
          <div><label class="label">{$t('pf.oldPw')}</label><input type="password" bind:value={cpOld} class="input" required /></div>
          <div><label class="label">{$t('pf.newPw')}</label><input type="password" bind:value={cpNew} class="input" required minlength="8" /></div>
          <div><label class="label">{$t('pf.confirmNewPw')}</label><input type="password" bind:value={cpConfirm} class="input" required minlength="8" /></div>
          <button disabled={cpSaving} class="btn-primary btn-md">{cpSaving ? $t('pf.saving') : $t('pf.changePw')}</button>
        </form>
      </div>

      <div class="flex justify-end">
        <button on:click={logout} class="btn-outline btn-md text-red-600 border-red-200 hover:bg-red-50">{$t('nav.logout')}</button>
      </div>
    </div>
  {:else}
    <!-- User biasa (BUYER/SELLER) — tanpa sidebar karena sudah ada di header dropdown -->
    <div class="max-w-2xl mx-auto">
      <div class="space-y-5">
        <div class="card">
          <div class="flex items-start justify-between gap-4 mb-4">
            <div>
              <h3 class="font-semibold flex items-center gap-2"><Icon name="wallet" size={16} /> Saldo Profil</h3>
              <p class="text-xs text-ink-500 mt-1">{$t('pf.refundNote')}</p>
              <a href="/refunds" class="mt-2 inline-flex items-center gap-1 text-xs text-app-primary hover:underline">
                <Icon name="receipt" size={12} /> Lihat riwayat refund detail
              </a>
            </div>
            <div class="text-right">
              <div class="text-xs text-ink-500">{$t('pf.available')}</div>
              <div class="text-xl font-bold">{walletLoading ? '...' : fmtRp(wallet?.available ?? 0)}</div>
            </div>
          </div>

          <div class="grid sm:grid-cols-3 gap-2 mb-4">
            <div class="rounded-2xl bg-ink-50 p-3">
              <div class="text-xs text-ink-500">{$t('pf.totalIn')}</div>
              <div class="font-semibold">{fmtRp(wallet?.gross ?? 0)}</div>
            </div>
            <div class="rounded-2xl bg-ink-50 p-3">
              <div class="text-xs text-ink-500">{$t('pf.processingWithdraw')}</div>
              <div class="font-semibold">{fmtRp(wallet?.withdrawn ?? 0)}</div>
            </div>
            <div class="rounded-2xl bg-ink-50 p-3">
              <div class="text-xs text-ink-500">{$t('pf.history')}</div>
              <div class="font-semibold">{wallet?.transactions?.length ?? 0} transaksi</div>
            </div>
          </div>

          <form on:submit={requestUserWithdraw} class="space-y-3">
            <div class="grid sm:grid-cols-2 gap-3">
              <div><label class="label">{$t('pf.withdrawAmount')}</label><input type="number" min="10000" bind:value={wdAmount} class="input" placeholder={$t('pf.amountPlaceholder')} required /></div>
              <div><label class="label">{$t('pf.bankName')}</label><input bind:value={wdBankName} class="input" placeholder="BCA, Mandiri, DANA" required /></div>
              <div><label class="label">{$t('pf.accountNumber')}</label><input bind:value={wdBankAccount} class="input" required /></div>
              <div><label class="label">{$t('pf.ownerName')}</label><input bind:value={wdBankHolder} class="input" required /></div>
            </div>
            <button disabled={wdSaving || (wallet?.available ?? 0) <= 0} class="btn-primary btn-md">{wdSaving ? $t('pf.sending2') : 'Ajukan Penarikan'}</button>
          </form>
        </div>

        <div class="card">
          <h3 class="font-semibold mb-4">{$t('pf.accountData')}</h3>
          <form on:submit={save} class="space-y-4 max-w-lg">
            <div><label class="label">{$t('pf.name')}</label><input bind:value={name} class="input" /></div>
            <div><label class="label">{$t('auth.email')}</label><input value={auth.user?.email ?? ''} disabled class="input bg-ink-50" /></div>
            <div><label class="label">{$t('auth.phone')}</label><input bind:value={phone} class="input" /></div>
            <button disabled={saving} class="btn-primary btn-md">{saving ? $t('pf.saving') : $t('pf.save')}</button>
          </form>
        </div>

        <div class="card">
          <h3 class="font-semibold mb-3 flex items-center gap-2"><Icon name="mail" size={16} /> {$t('pf.changeEmail')}</h3>
          <p class="text-xs text-ink-500 mb-3">{$t('pf.emailNote')}</p>
          <form on:submit={changeEmail} class="flex gap-2">
            <input type="email" bind:value={newEmail} class="input flex-1" placeholder={$t('pf.newEmailPlaceholder')} required />
            <button disabled={ceSaving} class="btn-primary btn-md">{ceSaving ? $t('pf.sending') : $t('pf.send')}</button>
          </form>
        </div>

        <div class="card">
          <h3 class="font-semibold mb-3 flex items-center gap-2"><Icon name="lock" size={16} /> {$t('pf.changePw')}</h3>
          <p class="text-xs text-ink-500 mb-3">{$t('pf.pwNote')}<a href="/forgot-password" class="link">{$t('pf.resetViaEmail')}</a>.</p>
          <form on:submit={changePassword} class="space-y-3 max-w-md">
            <div><label class="label">{$t('pf.oldPw')}</label><input type="password" bind:value={cpOld} class="input" required /></div>
            <div><label class="label">{$t('pf.newPw')}</label><input type="password" bind:value={cpNew} class="input" required minlength="8" /></div>
            <div><label class="label">{$t('pf.confirmNewPw')}</label><input type="password" bind:value={cpConfirm} class="input" required minlength="8" /></div>
            <button disabled={cpSaving} class="btn-primary btn-md">{cpSaving ? $t('pf.saving') : $t('pf.changePw')}</button>
          </form>
        </div>

        <div id="addresses" class="card">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">{$t('pf.addresses')}</h3>
            <button on:click={() => openAddrForm(null)} class="btn-outline btn-sm"><Icon name="plus" size={12} /> {$t('pf.add')}</button>
          </div>
          {#if addresses.length === 0 && !addressFormOpen}
            <p class="text-sm text-ink-500">{$t('pf.noAddr')}</p>
          {/if}
          {#each addresses as a (a.id)}
            <div class="border border-ink-100 rounded-xl p-4 mb-2 flex items-start gap-3">
              <Icon name="map-pin" size={18} class="text-ink-500 mt-0.5" />
              <div class="flex-1">
                <div class="flex items-center gap-2"><b>{a.recipient}</b> <span class="text-xs text-ink-500">{a.phone}</span> {#if a.is_default}<span class="pill-ink">{$t('co.primary')}</span>{/if}</div>
                <div class="text-sm text-ink-600 mt-0.5">
                  {a.full_address}, {a.village ? `${a.village}, ` : ''}{a.district ? `${a.district}, ` : ''}{a.city}{a.province ? `, ${a.province}` : ''}{a.postal_code ? ` ${a.postal_code}` : ''}
                </div>
                {#if a.latitude && a.longitude}
                  <a href={`https://www.google.com/maps?q=${a.latitude},${a.longitude}`} target="_blank" class="text-xs text-blue-600 mt-1 inline-flex items-center gap-1">{$t('pf.viewMaps')}<Icon name="external-link" size={10} /></a>
                {:else}
                  <div class="mt-1 inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-medium text-red-700">
                    <Icon name="map-pin-off" size={11} /> {$t('pf.pinRequired')}
                  </div>
                {/if}
              </div>
              <button on:click={() => openAddrForm(a)} class="text-ink-700 hover:bg-ink-50 w-8 h-8 grid place-items-center rounded"><Icon name="pencil" size={14} /></button>
              <button on:click={() => delAddr(a.id)} class="text-red-600 hover:bg-red-50 w-8 h-8 grid place-items-center rounded"><Icon name="trash-2" size={14} /></button>
            </div>
          {/each}
        </div>

        {#if addressFormOpen}
          <div class="card">
            <h3 class="font-semibold mb-4">{editing?.id ? $t('pf.edit') : $t('pf.add')} {$t('pf.addrWord')}</h3>
            <form on:submit={saveAddr} class="space-y-3">
              <AddressFields bind:value={formAddr} />
              <div>
                <label class="label">{$t('pf.pinOptional')}</label>
                <MapPicker bind:lat={formAddr.latitude} bind:lng={formAddr.longitude} query={addressQuery(formAddr)} />
              </div>
              <label class="flex items-center gap-2 text-sm"><input type="checkbox" bind:checked={formAddr.is_default} /> {$t('pf.makeDefault')}</label>
              <div class="flex gap-2">
                <button class="btn-primary btn-md">{$t('pf.save')}</button>
                <button type="button" on:click={() => { editing = null; addressFormOpen = false; }} class="btn-outline btn-md">{$t('od.cancel')}</button>
              </div>
            </form>
          </div>
        {/if}

        <div class="flex justify-end">
          <button on:click={logout} class="btn-outline btn-md text-red-600 border-red-200 hover:bg-red-50">Keluar</button>
        </div>
      </div>
    </div>
  {/if}
</div>
