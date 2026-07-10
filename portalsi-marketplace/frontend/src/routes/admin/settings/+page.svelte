<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, settings as settingsStore, confirmDialog } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let s = $state<any>(null);
  let saving = $state(false);
  let freshSummary = $state<any>(null);
  let freshLoading = $state(false);
  let freshRunning = $state(false);
  let freshPassword = $state('');
  let seoFile = $state<File | null>(null);

  onMount(async () => {
    s = await apiEndpoints.adminSettings();
  });

  async function save() {
    saving = true;
    try {
      await apiEndpoints.adminSaveSettings({
        tripay_mode: s.tripay_mode,
        tripay_api_key: s.tripay_api_key,
        tripay_private_key: s.tripay_private_key,
        tripay_merchant_code: s.tripay_merchant_code,
        rajaongkir_enabled: Boolean(s.rajaongkir_enabled),
        rajaongkir_mode: s.rajaongkir_mode,
        rajaongkir_api_key: s.rajaongkir_api_key,
        rajaongkir_tariff_base_url: s.rajaongkir_tariff_base_url,
        rajaongkir_order_base_url: s.rajaongkir_order_base_url,
        commission_percent: Number(s.commission_percent),
        brevo_api_key: s.brevo_api_key,
        brevo_sender_email: s.brevo_sender_email,
        brevo_sender_name: s.brevo_sender_name,
        seo_title: s.seo_title,
        seo_description: s.seo_description,
        seo_image: s.seo_image,
        seo_home_title: s.seo_home_title,
        seo_home_description: s.seo_home_description,
        seo_home_image: s.seo_home_image,
        seo_products_title: s.seo_products_title,
        seo_products_description: s.seo_products_description,
        seo_products_image: s.seo_products_image,
      });
      const pub: any = await apiEndpoints.publicSettings();
      settingsStore.setAll(pub);
      toast.success('Pengaturan disimpan');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }

  async function loadFreshSummary() {
    freshLoading = true;
    try {
      freshSummary = await apiEndpoints.adminFreshStartSummary();
    } catch (e: any) {
      toast.error(e.message || 'Gagal memuat ringkasan migrate fresh');
    } finally {
      freshLoading = false;
    }
  }

  async function uploadSeoImage() {
    if (!seoFile) return;
    const fd = new FormData();
    fd.append('seo_image', seoFile);
    try {
      const r: any = await apiEndpoints.adminUploadSeoImage(fd);
      s.seo_image = r.seo_image;
      seoFile = null;
      const pub: any = await apiEndpoints.publicSettings();
      settingsStore.setAll(pub);
      toast.success('Gambar share diupload');
    } catch (e: any) {
      toast.error(e.message || 'Gagal upload gambar share');
    }
  }

  async function runMigrateFresh() {
    if (!freshPassword) { toast.warn('Masukkan password admin'); return; }
    const ok = await confirmDialog.ask({
      title: 'Jalankan Migrate Fresh?',
      message: 'Aksi ini menghapus seluruh data transaksi/testing non-konfigurasi. Admin, settings, kategori, tag, FAQ, kurir, dan payment method tetap disimpan.',
      confirmText: 'Migrate Fresh',
      tone: 'danger',
    });
    if (!ok) return;
    freshRunning = true;
    try {
      await apiEndpoints.adminFreshStart(freshPassword);
      freshPassword = '';
      toast.success('Migrate fresh selesai');
      freshSummary = await apiEndpoints.adminFreshStartSummary();
    } catch (e: any) {
      toast.error(e.message || 'Migrate fresh gagal');
    } finally {
      freshRunning = false;
    }
  }
</script>

{#if !s}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <div class="space-y-5">
    <div class="card bg-sky-50 text-sky-800 text-xs p-3 rounded-xl flex items-start gap-2">
      <Icon name="info" size={14} class="mt-0.5 shrink-0" />
      <div>
        Halaman ini untuk konfigurasi sistem (komisi, payment gateway, email). Untuk mengubah tampilan UI, logo, hero, FAQ, atau metode pembayaran yang ditampilkan, buka <a href="/admin/appearance" class="underline font-semibold">Tampilan</a>.
      </div>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="share-2" size={18} /> SEO & Share Preview</h3>
      <p class="text-xs text-ink-500 mb-4">
        Judul, deskripsi, dan gambar ini dipakai WhatsApp, Telegram, Facebook, dan crawler lain saat link marketplace dibagikan.
        Gunakan gambar rasio 1.91:1 atau 1200x630 agar preview terlihat rapi.
      </p>

      <div class="grid lg:grid-cols-[1fr_280px] gap-4">
        <div class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-3">
            <div>
              <label class="label">Default SEO title</label>
              <input bind:value={s.seo_title} class="input" placeholder="MPSI Marketplace" />
            </div>
            <div>
              <label class="label">Gambar share default</label>
              <div class="flex gap-2">
                <input type="file" accept="image/*" on:change={(e: any) => seoFile = e.target.files?.[0] ?? null} class="text-sm flex-1" />
                <button type="button" on:click={uploadSeoImage} disabled={!seoFile} class="btn-outline btn-sm">Upload</button>
              </div>
            </div>
            <div class="sm:col-span-2">
              <label class="label">Default SEO description</label>
              <textarea bind:value={s.seo_description} class="input" rows={2} maxlength="300" placeholder="Deskripsi singkat marketplace untuk preview share."></textarea>
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-3 rounded-2xl border border-ink-100 bg-ink-50 p-3">
            <div class="sm:col-span-2 text-xs font-semibold uppercase tracking-widest text-ink-500">Homepage</div>
            <div><label class="label">Title beranda</label><input bind:value={s.seo_home_title} class="input bg-white" placeholder="Kosongkan untuk pakai default" /></div>
            <div><label class="label">Gambar beranda</label><input bind:value={s.seo_home_image} class="input bg-white" placeholder="Kosongkan untuk pakai gambar default" /></div>
            <div class="sm:col-span-2"><label class="label">Description beranda</label><textarea bind:value={s.seo_home_description} class="input bg-white" rows={2} placeholder="Kosongkan untuk pakai default"></textarea></div>
          </div>

          <div class="grid sm:grid-cols-2 gap-3 rounded-2xl border border-ink-100 bg-ink-50 p-3">
            <div class="sm:col-span-2 text-xs font-semibold uppercase tracking-widest text-ink-500">Halaman Produk</div>
            <div><label class="label">Title /products</label><input bind:value={s.seo_products_title} class="input bg-white" placeholder="Semua Produk di MPSI" /></div>
            <div><label class="label">Gambar /products</label><input bind:value={s.seo_products_image} class="input bg-white" placeholder="Kosongkan untuk pakai gambar default" /></div>
            <div class="sm:col-span-2"><label class="label">Description /products</label><textarea bind:value={s.seo_products_description} class="input bg-white" rows={2} placeholder="Deskripsi katalog produk"></textarea></div>
          </div>
        </div>

        <div class="rounded-2xl border border-ink-100 bg-white p-3 shadow-soft">
          <div class="aspect-[1.91/1] overflow-hidden rounded-xl bg-ink-100">
            {#if s.seo_image}
              <img src={s.seo_image} alt="" class="h-full w-full object-cover" />
            {:else}
              <div class="grid h-full place-items-center text-ink-400"><Icon name="image" size={28} /></div>
            {/if}
          </div>
          <div class="pt-3">
            <div class="line-clamp-2 text-sm font-semibold text-ink-950">{s.seo_home_title || s.seo_title || s.app_name || 'MPSI Marketplace'}</div>
            <div class="mt-1 line-clamp-3 text-xs leading-relaxed text-ink-500">{s.seo_home_description || s.seo_description || 'Deskripsi preview share akan tampil di sini.'}</div>
            <div class="mt-3 flex items-center gap-2 text-xs text-ink-500"><Icon name="link" size={13} /> marketplace.portalsi.com</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4">Komisi Platform</h3>
      <p class="text-xs text-ink-500 mb-4">Persen yang dipotong dari setiap pesanan selesai sebelum dana bisa ditarik oleh seller.</p>
      <div class="grid sm:grid-cols-[200px_1fr] gap-3 items-center">
        <div>
          <label class="label">Persen (0–50%)</label>
          <div class="flex items-center gap-2">
            <input type="number" min="0" max="50" step="0.5" bind:value={s.commission_percent} class="input" />
            <span class="font-semibold">%</span>
          </div>
        </div>
        <div class="bg-ink-50 p-3 rounded-xl text-xs text-ink-600">
          Contoh: pesanan Rp 100.000 dengan komisi {s.commission_percent ?? 5}% berarti seller mendapat
          <span class="font-bold text-ink-950">Rp {(100000 - 100000 * (Number(s.commission_percent ?? 5)) / 100).toLocaleString('id-ID')}</span>
          ke saldo penarikan.
        </div>
      </div>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="mail" size={18} /> Email Notifikasi (Brevo)</h3>
      <p class="text-xs text-ink-500 mb-4">
        Konfigurasi <b>Brevo</b> (formerly Sendinblue) untuk mengirim email transaksional otomatis:
        email selamat datang saat daftar, konfirmasi pesanan, update status, reset password, dll.
        Daftar gratis di <a href="https://app.brevo.com" target="_blank" class="link">brevo.com</a>.
        Kalau dikosongkan, email tidak terkirim — sistem tetap jalan.
      </p>
      <div class="mb-4 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Agar email tidak mudah masuk Spam, pastikan domain pengirim sudah diverifikasi di Brevo dan DNS SPF, DKIM, serta DMARC sudah aktif.
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2">
          <label class="label">API Key</label>
          <input type="password" bind:value={s.brevo_api_key} class="input font-mono text-xs" placeholder="xkeysib-..." />
        </div>
        <div>
          <label class="label">Sender Email</label>
          <input bind:value={s.brevo_sender_email} class="input" placeholder="noreply@yourdomain.com" />
        </div>
        <div>
          <label class="label">Sender Name</label>
          <input bind:value={s.brevo_sender_name} class="input" placeholder="MPSI" />
        </div>
      </div>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4">Tripay Payment Gateway</h3>
      <p class="text-xs text-ink-500 mb-4">Settings ini override .env. Kosongkan untuk pakai .env. Daftar credentials di <a href="https://tripay.co.id/member/merchant" target="_blank" class="link">tripay.co.id</a>.</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="label">Mode</label>
          <select bind:value={s.tripay_mode} class="input">
            <option value="sandbox">Sandbox (testing)</option>
            <option value="production">Production (live)</option>
          </select>
        </div>
        <div><label class="label">Merchant Code</label><input bind:value={s.tripay_merchant_code} class="input" placeholder="T0001" /></div>
        <div><label class="label">API Key</label><input bind:value={s.tripay_api_key} class="input font-mono text-xs" /></div>
        <div><label class="label">Private Key</label><input type="password" bind:value={s.tripay_private_key} class="input font-mono text-xs" /></div>
      </div>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4">RajaOngkir / Komerce Shipping</h3>
      <p class="text-xs text-ink-500 mb-4">API ini dipakai untuk cari destinasi, hitung ongkir live saat checkout, dan mencoba membuat data order/AWB saat seller mengirim pesanan.</p>
      <label class="mb-4 flex items-center justify-between gap-4 rounded-xl border border-ink-100 bg-ink-50 px-4 py-3 text-sm">
        <span>
          <b class="block text-ink-900">Aktifkan tarif otomatis RajaOngkir</b>
          <span class="text-ink-500">Jika dimatikan atau API gagal, checkout memakai opsi pengiriman manual dari menu admin.</span>
        </span>
        <input type="checkbox" bind:checked={s.rajaongkir_enabled} class="h-5 w-5 shrink-0" />
      </label>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="label">Mode</label>
          <select bind:value={s.rajaongkir_mode} class="input">
            <option value="sandbox">Sandbox</option>
            <option value="production">Production</option>
          </select>
        </div>
        <div><label class="label">API Key</label><input type="password" bind:value={s.rajaongkir_api_key} class="input font-mono text-xs" /></div>
        <div><label class="label">Tariff Base URL (opsional)</label><input bind:value={s.rajaongkir_tariff_base_url} class="input font-mono text-xs" placeholder="default otomatis sesuai mode" /></div>
        <div><label class="label">Order Base URL (opsional)</label><input bind:value={s.rajaongkir_order_base_url} class="input font-mono text-xs" placeholder="default otomatis sesuai mode" /></div>
      </div>
    </div>

    <div class="card border-red-100 bg-red-50/40">
      <div class="flex items-start gap-3">
        <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-100 text-red-700">
          <Icon name="database-zap" size={18} />
        </div>
        <div class="min-w-0 flex-1">
          <h3 class="font-semibold text-red-900">Migrate Fresh</h3>
          <p class="mt-1 text-sm text-red-800/80">
            Membersihkan data testing seperti user non-admin, vendor, produk, order, chat, laporan, wishlist, review, voucher, withdrawal, dan notifikasi. Masukkan password admin untuk menjalankan.
          </p>
          {#if freshSummary}
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.users_removed}</b><br />User non-admin</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.vendors}</b><br />Vendor</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.products}</b><br />Produk</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.orders}</b><br />Pesanan</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.chats}</b><br />Chat thread</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.reports}</b><br />Laporan</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.notifications}</b><br />Notifikasi</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.admins_kept}</b><br />Admin tetap</div>
            </div>
          {/if}
          <div class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto_auto]">
            <input type="password" bind:value={freshPassword} class="input bg-white" placeholder="Password admin" />
            <button type="button" on:click={loadFreshSummary} disabled={freshLoading} class="btn-outline btn-sm bg-white">
              <Icon name="list-checks" size={13} /> {freshLoading ? 'Memuat...' : 'Ringkasan'}
            </button>
            <button type="button" on:click={runMigrateFresh} disabled={freshRunning || !freshPassword} class="btn-danger btn-sm">
              <Icon name="trash-2" size={13} /> {freshRunning ? 'Memproses...' : 'Migrate Fresh'}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="flex justify-end">
      <button on:click={save} disabled={saving} class="btn-primary btn-lg">{saving ? 'Menyimpan…' : 'Simpan Pengaturan'}</button>
    </div>
  </div>
{/if}
