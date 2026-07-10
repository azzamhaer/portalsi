<script lang="ts">
  import { onMount } from 'svelte';
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
  import MapPicker from '$lib/components/MapPicker.svelte';
  import AddressFields from '$lib/components/AddressFields.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import VendorBadge from '$lib/components/VendorBadge.svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, auth } from '$lib/stores.svelte';
  import { goto } from '$app/navigation';

  let v = $state<any>(null);
  let saving = $state(false);
  let uploadingAvatar = $state(false);
  let uploadingBanner = $state(false);
  let savingUsername = $state(false);
  let newUsername = $state('');
  const APPROVED = $derived(v?.verification_status === 'APPROVED');

  const nextChangeAt = $derived.by(() => {
    if (!v?.username_changed_at) return null;
    const d = new Date(v.username_changed_at);
    d.setDate(d.getDate() + 7);
    return d;
  });
  const canChangeUsername = $derived(!nextChangeAt || nextChangeAt < new Date());

  async function saveUsername() {
    if (!newUsername.trim() || newUsername === v.username) return;
    if (!/^[a-z0-9][a-z0-9_-]*$/.test(newUsername)) {
      toast.error('Hanya huruf kecil, angka, "-" dan "_". Harus mulai huruf/angka.');
      return;
    }
    savingUsername = true;
    try {
      const r: any = await apiEndpoints.sellerUpdateUsername(newUsername);
      v = { ...v, username: r.username, username_changed_at: new Date().toISOString() };
      newUsername = '';
      toast.success('Username diperbarui');
    } catch (e: any) {
      toast.error(e.message);
    } finally { savingUsername = false; }
  }

  onMount(async () => {
    if (!auth.user) { goto('/login?next=/seller/profile'); return; }
    try {
      const data: any = await apiEndpoints.sellerDashboard();
      v = data.vendor;
    } catch { goto('/seller/register'); }
  });

  async function save(e: Event) {
    e.preventDefault();
    if (v.latitude == null || v.longitude == null) {
      toast.warn('Geser pin lokasi toko terlebih dahulu.');
      return;
    }
    saving = true;
    try {
      const payload: any = {
        name: v.name, city: v.city, description: v.description,
        country: 'Indonesia', province: v.province, district: v.district, village: v.village,
        postal_code: v.postal_code, address_note: v.address_note,
        latitude: v.latitude, longitude: v.longitude, full_address: v.full_address,
        bank_name: v.bank_name, bank_account: v.bank_account, bank_holder: v.bank_holder,
        avatar: v.avatar, banner: v.banner
      };
      const r: any = await apiEndpoints.sellerUpdateProfile(payload);
      v = { ...v, ...r };
      toast.success('Profil toko disimpan');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }

  function fileToDataUri(file: File): Promise<string> {
    return new Promise((res, rej) => {
      const r = new FileReader();
      r.onload = () => res(String(r.result));
      r.onerror = rej;
      r.readAsDataURL(file);
    });
  }

  function validateImageFile(file: File, maxMb: number): string | null {
    const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!allowed.includes(file.type)) {
      return 'Format foto harus JPG, PNG, WebP, atau GIF. Foto HEIC/RAW dari kamera perlu dikonversi dulu.';
    }
    if (file.size > maxMb * 1024 * 1024) {
      return `Ukuran foto maksimal ${maxMb}MB.`;
    }
    return null;
  }

  async function pickAvatar(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    const invalid = validateImageFile(file, 2);
    if (invalid) { toast.error(invalid); input.value = ''; return; }
    uploadingAvatar = true;
    try {
      const dataUri = await fileToDataUri(file);
      v.avatar = dataUri;
      const r: any = await apiEndpoints.sellerUpdateProfile({ avatar: dataUri });
      v = { ...v, ...r };
      toast.success('Foto profil diperbarui');
    } catch (e: any) { toast.error(e.message); } finally { uploadingAvatar = false; input.value = ''; }
  }

  async function pickBanner(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    const invalid = validateImageFile(file, 3);
    if (invalid) { toast.error(invalid); input.value = ''; return; }
    uploadingBanner = true;
    try {
      const dataUri = await fileToDataUri(file);
      v.banner = dataUri;
      const r: any = await apiEndpoints.sellerUpdateProfile({ banner: dataUri });
      v = { ...v, ...r };
      toast.success('Banner toko diperbarui');
    } catch (e: any) { toast.error(e.message); } finally { uploadingBanner = false; input.value = ''; }
  }

  function addressQuery(a: any) {
    return [a.village, a.district, a.city, a.province, a.postal_code, 'Indonesia'].filter(Boolean).join(', ');
  }
</script>

<svelte:head><title>Profil Toko</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <h1 class="section-title mb-6 sm:mb-8">Seller Center</h1>
  <div class="grid lg:grid-cols-[230px_1fr] gap-6">
    <SellerSidebar />
    <div>
      {#if !v}<div class="card text-center text-ink-500 py-10">Memuat…</div>
      {:else}
        <!-- Status banner -->
        <div class="card mb-5">
          {#if v.verification_status === 'PENDING'}
            <div class="bg-amber-50 text-amber-800 text-sm p-3 rounded-xl flex items-center gap-2">
              <Icon name="clock" size={14} /> Toko menunggu verifikasi admin. Anda belum bisa upload foto profil & banner.
            </div>
          {:else if v.verification_status === 'REJECTED'}
            <div class="bg-red-50 text-red-800 text-sm p-3 rounded-xl">
              <b>Verifikasi ditolak.</b> {v.verification_note}
            </div>
          {:else}
            <div class="bg-emerald-50 text-emerald-800 text-sm p-3 rounded-xl flex items-center gap-2 flex-wrap">
              <Icon name="shield-check" size={14} /> Toko terverifikasi.
              {#if v.badge}<VendorBadge badge={v.badge} size={14} showLabel />{/if}
            </div>
          {/if}
        </div>

        <!-- Banner + avatar upload (visual edit) - tersedia untuk semua status -->
        <div class="card mb-5 !p-0 overflow-hidden">
            <div class="relative aspect-[1200/300] bg-ink-100">
              <img src={v.banner} alt="" class="w-full h-full object-cover" />
              <label class="absolute right-3 bottom-3 bg-white/95 backdrop-blur px-3 py-2 rounded-full text-xs font-medium shadow-soft cursor-pointer hover:bg-white inline-flex items-center gap-1.5">
                <Icon name={uploadingBanner ? 'loader' : 'image'} size={14} />
                {uploadingBanner ? 'Mengunggah…' : 'Ganti Banner'}
                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" on:change={pickBanner} class="hidden" />
              </label>
            </div>
            <div class="flex items-center gap-4 p-4">
              <div class="relative">
                <img src={v.avatar} alt="" class="w-20 h-20 rounded-full object-cover -mt-12 border-4 border-white" />
                <label class="absolute -bottom-1 -right-1 w-8 h-8 grid place-items-center bg-app-primary text-app-pfg rounded-full cursor-pointer hover:bg-ink-800 shadow-soft">
                  <Icon name={uploadingAvatar ? 'loader' : 'camera'} size={14} />
                  <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" on:change={pickAvatar} class="hidden" />
                </label>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-display text-lg font-bold tracking-tightest truncate flex items-center gap-2">
                  {v.name}
                  {#if v.badge}<VendorBadge badge={v.badge} size={16} />{/if}
                </div>
                <div class="text-xs text-ink-500">{v.city} · {(v.followers ?? 0).toLocaleString('id-ID')} pengikut</div>
              </div>
              <a href={v.username ? `/${v.username}` : `/vendors/${v.id}`} class="btn-outline btn-sm">Lihat Toko Publik</a>
            </div>
          </div>

        <!-- Username editor -->
        <div class="card mb-5">
          <h3 class="font-semibold mb-1">Username Toko</h3>
          <p class="text-xs text-ink-500 mb-4">URL toko Anda: <code class="bg-ink-50 px-1.5 py-0.5 rounded">/{v.username}</code></p>
          <div class="flex gap-2 items-stretch">
            <div class="flex items-stretch flex-1 rounded-xl overflow-hidden border border-ink-200 focus-within:border-ink-950 transition">
              <span class="px-3 grid place-items-center bg-ink-50 text-ink-500 text-sm font-mono">/</span>
              <input
                bind:value={newUsername}
                placeholder={v.username}
                maxlength="30"
                disabled={!canChangeUsername}
                class="flex-1 px-3 py-2.5 text-sm outline-none font-mono lowercase"
              />
            </div>
            <button type="button" on:click={saveUsername} disabled={savingUsername || !canChangeUsername || !newUsername.trim() || newUsername === v.username} class="btn-primary btn-md">
              {savingUsername ? 'Menyimpan…' : 'Ubah'}
            </button>
          </div>
          {#if !canChangeUsername && nextChangeAt}
            <div class="bg-amber-50 text-amber-800 text-xs p-2.5 rounded-lg mt-3 flex items-center gap-2">
              <Icon name="clock" size={12} /> Username dapat diubah lagi pada {nextChangeAt.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}.
            </div>
          {:else}
            <p class="text-xs text-ink-500 mt-2">Maksimal 1× ubah username per minggu. Min 3 huruf, hanya huruf kecil, angka, "-" dan "_".</p>
          {/if}
        </div>

        <!-- Profile form -->
        <div class="card">
          <h3 class="font-semibold mb-4">Profil Toko</h3>
          <form on:submit={save} class="space-y-4 max-w-2xl">
            <div class="grid sm:grid-cols-2 gap-3">
              <div><label class="label">Nama Toko</label><input bind:value={v.name} class="input" /></div>
            </div>
            <div><label class="label">Deskripsi</label><textarea bind:value={v.description} class="input" rows={3}></textarea></div>
            <AddressFields bind:value={v} contact={false} title="Alamat Toko" />
            <div>
              <label class="label">Pin Lokasi Toko</label>
              <MapPicker bind:lat={v.latitude} bind:lng={v.longitude} query={addressQuery(v)} />
            </div>
            <div class="grid sm:grid-cols-3 gap-3 pt-3 border-t border-ink-100">
              <div><label class="label">Bank</label>
                <select bind:value={v.bank_name} class="input">
                  <option>BCA</option><option>BRI</option><option>Mandiri</option><option>BNI</option><option>BSI</option><option>CIMB Niaga</option><option>Permata</option>
                </select>
              </div>
              <div><label class="label">No. Rekening</label><input bind:value={v.bank_account} class="input" /></div>
              <div><label class="label">Atas Nama</label><input bind:value={v.bank_holder} class="input" /></div>
            </div>
            <button disabled={saving} class="btn-primary btn-md">{saving ? 'Menyimpan…' : 'Simpan'}</button>
          </form>
        </div>
      {/if}
    </div>
  </div>
</div>
