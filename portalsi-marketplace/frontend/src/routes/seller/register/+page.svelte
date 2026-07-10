<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import AddressFields from '$lib/components/AddressFields.svelte';
  import MapPicker from '$lib/components/MapPicker.svelte';
  import { auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';

  let name = $state(''), description = $state('');
  let address = $state<any>({ country: 'Indonesia', province: '', city: '', district: '', village: '', postal_code: '', full_address: '', address_note: '', latitude: null, longitude: null });
  let bank_name = $state('BCA'), bank_account = $state(''), bank_holder = $state('');
  let ktpData = $state('');
  let saving = $state(false);

  onMount(async () => {
    if (!auth.user) goto('/login?next=/seller/register');
    else if (auth.user.vendor_id) {
      try {
        const me: any = await apiEndpoints.me();
        auth.set(me);
        goto(me.vendor_status === 'APPROVED' ? '/seller/dashboard' : '/seller/pending');
      } catch {
        goto(auth.user.vendor_status === 'APPROVED' ? '/seller/dashboard' : '/seller/pending');
      }
    }
  });

  function onKtp(e: any) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { toast.warn('Ukuran KTP maks 2MB'); return; }
    const r = new FileReader();
    r.onload = () => { ktpData = r.result as string; };
    r.readAsDataURL(file);
  }

  async function submit(e: Event) {
    e.preventDefault();
    if (!ktpData) { toast.warn('Upload foto KTP terlebih dahulu'); return; }
    if (address.latitude == null || address.longitude == null) { toast.warn('Geser pin lokasi toko terlebih dahulu'); return; }
    saving = true;
    try {
      await apiEndpoints.sellerRegister({ name, ...address, description, bank_name, bank_account, bank_holder, ktp_image: ktpData });
      const me: any = await apiEndpoints.me();
      auth.set(me);
      toast.success('Pendaftaran terkirim, menunggu verifikasi admin');
      // Vendor baru status PENDING — langsung ke halaman pending
      goto('/seller/pending');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }

  const benefits = [
    { i:'rocket',      t:'Daftar Gratis',     d:'Tanpa biaya pendaftaran, langsung jualan.' },
    { i:'credit-card', t:'Pembayaran Otomatis',d:'Terima dana semua bank, e-wallet, & retail.' },
    { i:'bar-chart-3', t:'Dashboard Lengkap', d:'Kelola produk, pesanan & laporan satu tempat.' },
    { i:'gift',        t:'Promo Resmi',       d:'Ikut Flash Sale, Gratis Ongkir, & cashback.' }
  ];

  const addressQuery = $derived([address.village, address.district, address.city, address.province, address.postal_code, 'Indonesia'].filter(Boolean).join(', '));
</script>

<svelte:head><title>Buka Toko</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <section class="card bg-app-primary text-app-pfg">
    <h1 class="font-display text-2xl sm:text-3xl md:text-4xl font-bold tracking-tightest mb-2">Mulai berjualan</h1>
    <p class="text-ink-300 max-w-xl text-sm sm:text-base">Jangkau jutaan pembeli, kelola toko semudah klik, terima pembayaran instan otomatis.</p>
  </section>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-5">
    {#each benefits as b}
      <div class="card text-center">
        <div class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-3 rounded-2xl bg-ink-100 grid place-items-center"><Icon name={b.i} size={20} /></div>
        <h3 class="font-semibold mb-1 text-sm sm:text-base">{b.t}</h3>
        <p class="text-xs sm:text-sm text-ink-500">{b.d}</p>
      </div>
    {/each}
  </div>

  <div class="card mt-6 max-w-2xl">
    <h3 class="font-semibold mb-4">Formulir Pendaftaran</h3>
    <form on:submit={submit} class="space-y-4">
      <div><label class="label">Nama Toko <span class="text-red-600">*</span></label><input class="input" bind:value={name} required /></div>
      <div><label class="label">Deskripsi Toko <span class="text-red-600">*</span></label><textarea class="input" rows={3} bind:value={description} required></textarea></div>
      <AddressFields bind:value={address} contact={false} title="Alamat Toko" />
      <div>
        <label class="label">Pin Lokasi Toko</label>
        <MapPicker bind:lat={address.latitude} bind:lng={address.longitude} query={addressQuery} />
      </div>
      <div class="grid sm:grid-cols-3 gap-3">
        <div><label class="label">Bank</label>
          <select class="input" bind:value={bank_name}>
            <option>BCA</option><option>BRI</option><option>Mandiri</option><option>BNI</option><option>BSI</option><option>CIMB Niaga</option><option>Permata</option>
          </select>
        </div>
        <div><label class="label">No. Rekening</label><input class="input" bind:value={bank_account} /></div>
        <div><label class="label">Atas Nama</label><input class="input" bind:value={bank_holder} /></div>
      </div>

      <div>
        <label class="label">Foto KTP <span class="text-red-600">*</span></label>
        <p class="helper mb-2">KTP digunakan untuk verifikasi identitas. Data Anda dilindungi & tidak dipublikasikan.</p>
        <div class="grid sm:grid-cols-[200px_1fr] gap-3 items-start">
          <div class="aspect-[1.6/1] rounded-xl border-2 border-dashed border-ink-200 bg-ink-50 grid place-items-center overflow-hidden">
            {#if ktpData}<img src={ktpData} alt="KTP" class="w-full h-full object-cover" />
            {:else}<Icon name="id-card" size={36} class="text-ink-300" />{/if}
          </div>
          <input type="file" accept="image/*" on:change={onKtp} class="text-sm" />
        </div>
      </div>

      <div class="bg-amber-50 text-amber-800 text-xs p-3 rounded-xl">
        <Icon name="info" size={12} class="inline" /> Toko Anda akan aktif setelah admin memverifikasi identitas (1-2 hari kerja).
      </div>

      <button disabled={saving} class="btn-primary btn-lg w-full">{saving ? 'Memproses…' : 'Buka Toko Sekarang'}</button>
    </form>
  </div>
</div>
