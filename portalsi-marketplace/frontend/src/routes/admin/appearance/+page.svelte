<script lang="ts">
  import { onMount } from 'svelte';
  import { t } from '$lib/i18n';
  import { get } from 'svelte/store';
  import { apiEndpoints } from '$lib/api';
  import { toast, settings as settingsStore } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let s = $state<any>(null);
  let palettes = $state<any[]>([]);
  let saving = $state(false);
  let logoFile = $state<File | null>(null);
  let heroFile = $state<File | null>(null);
  let tab = $state<'branding' | 'home' | 'payment' | 'faq' | 'pages' | 'footer' | 'visibility'>('branding');

  onMount(async () => {
    const data: any = await apiEndpoints.adminSettings();
    s = data; palettes = data.palettes;
  });

  async function save() {
    saving = true;
    try {
      await apiEndpoints.adminSaveSettings({
        app_name: s.app_name, tagline: s.tagline, palette: s.palette,
        primary_color: s.primary_color, primary_fg: s.primary_fg, accent_color: s.accent_color,
        hero_enabled: !!s.hero_enabled,
        hero_title: s.hero_title, hero_subtitle: s.hero_subtitle,
        hero_cta_label: s.hero_cta_label, hero_cta_href: s.hero_cta_href,
        payment_intro: s.payment_intro, help_intro: s.help_intro,
        footer_columns: s.footer_columns ?? [],
        footer_desc: s.footer_desc,
        footer_contact: s.footer_contact,
        footer_bottom: s.footer_bottom,
        hidden_pages: s.hidden_pages ?? [],
      });
      const pub: any = await apiEndpoints.publicSettings();
      settingsStore.setAll(pub);
      toast.success(get(t)('aap.saved'));
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }

  function pickPalette(p: any) {
    s.palette = p.key; s.primary_color = p.primary; s.primary_fg = p.primaryFg; s.accent_color = p.accent;
  }
  async function uploadLogo() {
    if (!logoFile) return;
    const fd = new FormData(); fd.append('logo', logoFile);
    try {
      const r: any = await apiEndpoints.adminUploadLogo(fd);
      s.logo_url = r.logo_url;
      const pub: any = await apiEndpoints.publicSettings();
      settingsStore.setAll(pub);
      toast.success(get(t)('aap.logoUploaded'));
    } catch (e: any) { toast.error(e.message); }
  }
  async function uploadHero() {
    if (!heroFile) return;
    const fd = new FormData(); fd.append('hero', heroFile);
    try {
      const r: any = await apiEndpoints.adminUploadHero(fd);
      s.hero_image = r.hero_image;
      const pub: any = await apiEndpoints.publicSettings();
      settingsStore.setAll(pub);
      toast.success(get(t)('aap.heroUploaded'));
    } catch (e: any) { toast.error(e.message); }
  }

  // FAQ state
  let faqs = $state<any[]>([]);
  let faqsLoaded = $state(false);
  let faqsSaving = $state(false);
  async function loadFaqs() {
    if (faqsLoaded) return;
    try { faqs = await apiEndpoints.adminFaqs(); faqsLoaded = true; }
    catch (e: any) { toast.error(e.message); }
  }
  function addFaq() { faqs = [...faqs, { section: 'Umum', question: '', answer: '', is_active: true }]; }
  function rmFaq(i: number) { faqs = faqs.filter((_, idx) => idx !== i); }
  function moveFaq(i: number, dir: number) {
    const j = i + dir; if (j < 0 || j >= faqs.length) return;
    const c = [...faqs]; [c[i], c[j]] = [c[j], c[i]]; faqs = c;
  }
  async function saveFaqs() {
    faqsSaving = true;
    try { await apiEndpoints.adminSaveFaqs(faqs); toast.success(get(t)('aap.faqSaved')); }
    catch (e: any) { toast.error(e.message); } finally { faqsSaving = false; }
  }

  // Payment Methods state
  let pms = $state<any[]>([]);
  let pmsLoaded = $state(false);
  let pmsSaving = $state(false);
  async function loadPms() {
    if (pmsLoaded) return;
    try { pms = await apiEndpoints.adminPaymentMethods(); pmsLoaded = true; }
    catch (e: any) { toast.error(e.message); }
  }
  function addPm() {
    pms = [...pms, { code: 'NEW' + Math.floor(Math.random()*1000), name: '', group: 'Virtual Account', icon: '', color: '#0a0a0a', fee_pct: 0, fee_flat: 0, is_active: true }];
  }
  function rmPm(i: number) { if (confirm('Hapus?')) pms = pms.filter((_, idx) => idx !== i); }
  function uploadPmIcon(i: number, file: File) {
    if (file.size > 500_000) { toast.error(get(t)('aap.max500')); return; }
    const r = new FileReader();
    r.onload = () => { pms[i].icon = String(r.result); };
    r.readAsDataURL(file);
  }
  async function savePms() {
    if (pms.some((x) => !x.code || !x.name)) { toast.error(get(t)('aap.completeCodeName')); return; }
    pmsSaving = true;
    try { await apiEndpoints.adminSavePaymentMethods(pms); toast.success(get(t)('aap.pmSaved')); }
    catch (e: any) { toast.error(e.message); } finally { pmsSaving = false; }
  }
  function movePm(i: number, dir: number) {
    const j = i + dir; if (j < 0 || j >= pms.length) return;
    const c = [...pms]; [c[i], c[j]] = [c[j], c[i]]; pms = c;
  }

  // Switch tab triggers load
  $effect(() => {
    if (tab === 'faq') loadFaqs();
    if (tab === 'payment') loadPms();
  });
</script>

{#if !s}<div class="card text-center text-ink-500 py-10">{$t('wl.loading')}</div>
{:else}
  <div class="card !p-2 mb-5 flex gap-1 flex-wrap text-xs">
    {#each [['branding','Branding & Palette'],['home','Beranda (Hero)'],['payment','Pembayaran'],['faq','FAQ'],['pages','Intro Halaman'],['footer','Footer'],['visibility','Visibilitas Halaman']] as [k, l]}
      <button on:click={() => tab = k as any} class="px-3 py-2 rounded-lg transition" class:bg-app-primary={tab === k} class:text-app-pfg={tab === k} class:hover:bg-ink-50={tab !== k}>{l}</button>
    {/each}
  </div>

  {#if tab === 'branding'}
    <div class="space-y-5">
      <div class="card">
        <h3 class="font-semibold mb-4">{$t('aap.appIdentity')}</h3>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="label">{$t('aap.appName')}</label><input bind:value={s.app_name} class="input" /></div>
          <div>
            <label class="label">{$t('aap.tagline')}</label>
            <input bind:value={s.tagline} class="input" placeholder={$t('aap.taglinePh')} />
            <p class="helper">{$t('aap.taglineHint')}</p>
          </div>
        </div>
        <div class="mt-4">
          <label class="label">{$t('aap.appLogo')}</label>
          <div class="grid sm:grid-cols-[120px_1fr] gap-3 items-center">
            <div class="aspect-square w-24 rounded-2xl bg-ink-50 border border-ink-200 grid place-items-center overflow-hidden">
              {#if s.logo_url}<img src={s.logo_url} alt="" class="w-full h-full object-cover" />
              {:else}<Icon name="image" size={28} class="text-ink-300" />{/if}
            </div>
            <div>
              <input type="file" accept="image/png,image/jpeg,image/webp" on:change={(e: any) => logoFile = e.target.files?.[0] ?? null} class="text-sm mb-2" />
              <button on:click={uploadLogo} disabled={!logoFile} class="btn-outline btn-sm">{$t('aset.upload')}</button>
              <p class="helper">{$t('aap.logoHint')}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-4">{$t('aap.colorPalette')}</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          {#each palettes as p}
            <button on:click={() => pickPalette(p)} class="text-left p-4 rounded-2xl border-2 transition" class:border-ink-950={s.palette===p.key} class:border-transparent={s.palette!==p.key}>
              <div class="flex gap-1 mb-2">
                <div class="w-8 h-8 rounded" style:background={p.primary}></div>
                <div class="w-8 h-8 rounded" style:background={p.accent}></div>
                <div class="w-8 h-8 rounded border border-ink-200" style:background={p.primaryFg}></div>
              </div>
              <div class="font-semibold text-sm">{p.name}</div>
              <div class="text-xs text-ink-500">{p.key}</div>
            </button>
          {/each}
        </div>
        <div class="grid sm:grid-cols-3 gap-3 mt-4">
          <div><label class="label">{$t('aap.primary')}</label><input type="color" bind:value={s.primary_color} class="w-full h-12 rounded-xl border border-ink-200" /></div>
          <div><label class="label">{$t('aap.foreground')}</label><input type="color" bind:value={s.primary_fg} class="w-full h-12 rounded-xl border border-ink-200" /></div>
          <div><label class="label">{$t('aap.accent')}</label><input type="color" bind:value={s.accent_color} class="w-full h-12 rounded-xl border border-ink-200" /></div>
        </div>
      </div>

      <div class="flex justify-end">
        <button on:click={save} disabled={saving} class="btn-primary btn-md">{saving ? $t('mk.saving') : $t('pf.save')}</button>
      </div>
    </div>
  {:else if tab === 'home'}
    <div class="space-y-5">
      <div class="card">
        <h3 class="font-semibold mb-4">{$t('aap.heroSection')}</h3>
        <label class="mb-4 flex items-center justify-between gap-4 rounded-2xl border border-ink-100 bg-ink-50 px-4 py-3">
          <span>
            <span class="block text-sm font-semibold">{$t('aap.showHero')}</span>
            <span class="block text-xs text-ink-500">{$t('aap.showHeroHint')}</span>
          </span>
          <input type="checkbox" bind:checked={s.hero_enabled} class="h-5 w-5" />
        </label>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="label">{$t('aap.heroTitle')}</label><input bind:value={s.hero_title} class="input" /></div>
          <div><label class="label">{$t('aap.heroSubtitle')}</label><input bind:value={s.hero_subtitle} class="input" /></div>
          <div><label class="label">{$t('aap.ctaText')}</label><input bind:value={s.hero_cta_label} class="input" /></div>
          <div><label class="label">{$t('aap.ctaLink')}</label><input bind:value={s.hero_cta_href} class="input" placeholder="/products" /></div>
        </div>
        <div class="mt-4">
          <label class="label">{$t('aap.heroImg')}</label>
          <div class="grid sm:grid-cols-[180px_1fr] gap-3 items-center">
            <div class="aspect-[4/3] rounded-2xl bg-ink-50 border border-ink-200 grid place-items-center overflow-hidden">
              {#if s.hero_image}<img src={s.hero_image} alt="" class="w-full h-full object-cover" />
              {:else}<Icon name="image" size={28} class="text-ink-300" />{/if}
            </div>
            <div>
              <input type="file" accept="image/*" on:change={(e: any) => heroFile = e.target.files?.[0] ?? null} class="text-sm mb-2" />
              <button on:click={uploadHero} disabled={!heroFile} class="btn-outline btn-sm">{$t('aset.upload')}</button>
              {#if s.hero_image}<button type="button" on:click={() => s.hero_image = ''} class="btn-sm bg-red-50 text-red-700 hover:bg-red-100 ml-2">{$t('aap.remove')}</button>{/if}
              <p class="helper">{$t('aap.heroImgHint')}</p>
            </div>
          </div>
        </div>
      </div>
      <div class="flex justify-end">
        <button on:click={save} disabled={saving} class="btn-primary btn-md">{saving ? $t('mk.saving') : $t('pf.save')}</button>
      </div>
    </div>
  {:else if tab === 'pages'}
    <div class="space-y-5">
      <div class="card">
        <h3 class="font-semibold mb-3">{$t('aap.payIntro')}</h3>
        <p class="text-xs text-ink-500 mb-2">{$t('aap.payIntroHint')}</p>
        <textarea bind:value={s.payment_intro} class="input" rows={4} placeholder={$t('aap.payIntroPh')}></textarea>
      </div>
      <div class="card">
        <h3 class="font-semibold mb-3">{$t('aap.helpIntro')}</h3>
        <p class="text-xs text-ink-500 mb-2">{$t('aap.helpIntroHint')}</p>
        <textarea bind:value={s.help_intro} class="input" rows={4} placeholder={$t('aap.helpIntroPh')}></textarea>
      </div>
      <div class="flex justify-end">
        <button on:click={save} disabled={saving} class="btn-primary btn-md">{saving ? $t('mk.saving') : $t('pf.save')}</button>
      </div>
    </div>
  {:else if tab === 'payment'}
    <div class="space-y-3">
      <div class="card text-xs text-ink-500 bg-amber-50 text-amber-800">
        <b>{$t('aap.note')}</b> Daftar di sini ditampilkan di halaman <code>/payment-info</code> sebagai info publik. Saat checkout, metode pembayaran sebenarnya tetap diatur oleh integrasi Tripay (sandbox/production).
      </div>
      <div class="flex justify-between items-center flex-wrap gap-2">
        <h3 class="font-semibold">{$t('aap.pmPublicList')}</h3>
        <div class="flex gap-2">
          <button on:click={addPm} class="btn-outline btn-sm"><Icon name="plus" size={14} /> {$t('mk.add')}</button>
          <button on:click={savePms} disabled={pmsSaving} class="btn-primary btn-sm">{pmsSaving ? $t('mk.saving') : $t('pf.save')}</button>
        </div>
      </div>
      {#if !pmsLoaded}<div class="card text-center py-10 text-ink-500">Memuat…</div>
      {:else if pms.length === 0}
        <div class="card text-center py-10 text-ink-500">{$t('lf.noMethods')}</div>
      {:else}
        {#each pms as m, i}
          <div class="card">
            <div class="flex items-start justify-between gap-2 mb-3">
              <div class="text-xs text-ink-500">#{i + 1}</div>
              <div class="flex items-center gap-1">
                <button on:click={() => movePm(i, -1)} class="w-7 h-7 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="arrow-up" size={12} /></button>
                <button on:click={() => movePm(i, 1)} class="w-7 h-7 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="arrow-down" size={12} /></button>
                <label class="flex items-center gap-1 text-xs"><input type="checkbox" bind:checked={m.is_active} /> Aktif</label>
                <button on:click={() => rmPm(i)} class="w-7 h-7 grid place-items-center rounded-full text-red-600 hover:bg-red-50"><Icon name="trash-2" size={12} /></button>
              </div>
            </div>
            <div class="grid sm:grid-cols-[80px_1fr] gap-3 items-start">
              <div>
                <label class="label">Icon</label>
                <div class="w-16 h-16 rounded-xl border border-ink-200 grid place-items-center overflow-hidden bg-ink-50">
                  {#if m.icon}<img src={m.icon} alt="" class="w-full h-full object-contain" />
                  {:else}<span class="text-[10px] text-ink-400 text-center px-1">{m.code.slice(0,4)}</span>{/if}
                </div>
                <input type="file" accept="image/*" on:change={(e: any) => uploadPmIcon(i, e.target.files?.[0])} class="text-[10px] mt-1 w-16" />
              </div>
              <div class="grid sm:grid-cols-2 gap-3">
                <div><label class="label">Kode</label><input bind:value={m.code} class="input font-mono text-xs" /></div>
                <div><label class="label">Nama</label><input bind:value={m.name} class="input" /></div>
                <div>
                  <label class="label">Kategori</label>
                  <input bind:value={m.group} class="input" list="pm-groups-app" />
                  <datalist id="pm-groups-app">
                    <option value="Virtual Account" />
                    <option value="E-Wallet" />
                    <option value="QRIS" />
                    <option value="Convenience Store" />
                    <option value="Credit Card" />
                  </datalist>
                </div>
                <div><label class="label">Warna</label><input type="color" bind:value={m.color} class="w-full h-10 rounded-xl border border-ink-200" /></div>
                <div><label class="label">Fee (%)</label><input type="number" step="0.01" bind:value={m.fee_pct} class="input" /></div>
                <div><label class="label">Fee tetap (Rp)</label><input type="number" bind:value={m.fee_flat} class="input" /></div>
              </div>
            </div>
          </div>
        {/each}
      {/if}
    </div>
  {:else if tab === 'faq'}
    <div class="space-y-3">
      <div class="flex justify-between items-center">
        <h3 class="font-semibold">FAQ Halaman Bantuan</h3>
        <div class="flex gap-2">
          <button on:click={addFaq} class="btn-outline btn-sm"><Icon name="plus" size={14} /> {$t('mk.add')}</button>
          <button on:click={saveFaqs} disabled={faqsSaving} class="btn-primary btn-sm">{faqsSaving ? $t('mk.saving') : $t('pf.save')}</button>
        </div>
      </div>
      {#if !faqsLoaded}<div class="card text-center py-10 text-ink-500">Memuat…</div>
      {:else if faqs.length === 0}
        <div class="card text-center py-10 text-ink-500">{$t('lf.noFaqs')}</div>
      {:else}
        {#each faqs as f, i}
          <div class="card">
            <div class="flex items-start justify-between gap-2 mb-3">
              <div class="text-xs text-ink-500">#{i + 1}</div>
              <div class="flex items-center gap-1">
                <button on:click={() => moveFaq(i, -1)} class="w-7 h-7 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="arrow-up" size={12} /></button>
                <button on:click={() => moveFaq(i, 1)} class="w-7 h-7 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="arrow-down" size={12} /></button>
                <label class="flex items-center gap-1 text-xs"><input type="checkbox" bind:checked={f.is_active} /> Aktif</label>
                <button on:click={() => rmFaq(i)} class="w-7 h-7 grid place-items-center rounded-full text-red-600 hover:bg-red-50"><Icon name="trash-2" size={12} /></button>
              </div>
            </div>
            <div class="grid sm:grid-cols-[200px_1fr] gap-3">
              <div><label class="label">Section</label><input bind:value={f.section} class="input" list="faq-sec" /><datalist id="faq-sec"><option value="Pesanan" /><option value="Pembayaran" /><option value="Pengiriman" /><option value="Akun & Toko" /><option value="Umum" /></datalist></div>
              <div><label class="label">Pertanyaan</label><input bind:value={f.question} class="input" /></div>
            </div>
            <div class="mt-3"><label class="label">Jawaban</label><textarea bind:value={f.answer} class="input" rows={3}></textarea></div>
          </div>
        {/each}
      {/if}
    </div>
  {:else if tab === 'footer'}
    <div class="space-y-5">
      <div class="card">
        <h3 class="font-semibold mb-4">Konten Footer</h3>
        <div class="grid sm:grid-cols-2 gap-3">
          <div class="sm:col-span-2"><label class="label">Deskripsi aplikasi</label><textarea bind:value={s.footer_desc} class="input" rows={3} placeholder="Marketplace multivendor terdepan di Indonesia…"></textarea></div>
          <div><label class="label">Kontak</label><input bind:value={s.footer_contact} class="input" placeholder="support@mpsi.id · 0800-1-MPSI" /></div>
          <div><label class="label">Bottom (copyright)</label><input bind:value={s.footer_bottom} class="input" placeholder="© 2026 MPSI. Semua hak dilindungi." /></div>
        </div>
      </div>

      <div class="card">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
          <h3 class="font-semibold">Kolom Footer</h3>
          <button type="button" on:click={() => s.footer_columns = [...(s.footer_columns ?? []), { title: 'Kolom Baru', links: [] }]} class="btn-outline btn-sm"><Icon name="plus" size={12} /> {$t('lf.addColumn')}</button>
        </div>
        <p class="helper mb-3">{$t('lf.footerColsHint')}</p>
        {#if !s.footer_columns?.length}
          <div class="bg-ink-50 p-3 rounded-xl text-xs text-ink-500">{$t('lf.noCustomCols')}</div>
        {:else}
          {#each s.footer_columns as col, ci}
            <div class="border border-ink-100 rounded-2xl p-3 mb-3">
              <div class="flex items-center gap-2 mb-3">
                <input bind:value={col.title} class="input flex-1 !py-2" placeholder="Judul kolom" />
                <button type="button" on:click={() => s.footer_columns = s.footer_columns.filter((_: any, i: number) => i !== ci)} class="text-red-600 hover:bg-red-50 w-8 h-8 grid place-items-center rounded-full"><Icon name="trash-2" size={12} /></button>
              </div>
              <div class="space-y-2">
                {#each (col.links ?? []) as link, li}
                  <div class="flex gap-2">
                    <input bind:value={link.label} class="input !py-2 !text-sm flex-1" placeholder="Label" />
                    <input bind:value={link.href} class="input !py-2 !text-sm flex-1" placeholder="/path" />
                    <button type="button" on:click={() => col.links = col.links.filter((_: any, i: number) => i !== li)} class="text-red-600 hover:bg-red-50 w-8 h-8 grid place-items-center rounded-full shrink-0"><Icon name="x" size={12} /></button>
                  </div>
                {/each}
                <button type="button" on:click={() => col.links = [...(col.links ?? []), { label: '', href: '' }]} class="text-xs text-ink-500 hover:text-ink-950 flex items-center gap-1"><Icon name="plus" size={11} /> {$t('lf.addLink')}</button>
              </div>
            </div>
          {/each}
        {/if}
      </div>
      <div class="flex justify-end">
        <button on:click={save} disabled={saving} class="btn-primary btn-md">{saving ? $t('mk.saving') : $t('pf.save')}</button>
      </div>
    </div>
  {:else if tab === 'visibility'}
    <div class="space-y-5">
      <div class="card">
        <h3 class="font-semibold mb-2">{$t('lf.pageVisibility')}</h3>
        <p class="text-xs text-ink-500 mb-4">{$t('lf.hidePagesPre')} <b>{$t('lf.hidden')}</b>{$t('lf.hidePagesPost')}</p>
        {#each [['vendors','Halaman Toko (/vendors)'],['payment-info','Halaman Pembayaran (/payment-info)'],['help','Halaman Bantuan (/help)'],['about','Halaman Tentang (/about)']] as [key, label]}
          <label class="flex items-center gap-3 p-3 border border-ink-100 rounded-xl mb-2 cursor-pointer hover:bg-ink-50">
            <input
              type="checkbox"
              checked={s.hidden_pages?.includes(key)}
              on:change={(e: any) => {
                if (e.target.checked) s.hidden_pages = [...(s.hidden_pages ?? []), key];
                else s.hidden_pages = (s.hidden_pages ?? []).filter((p: string) => p !== key);
              }}
            />
            <span class="text-sm">{label}</span>
          </label>
        {/each}
      </div>
      <div class="flex justify-end">
        <button on:click={save} disabled={saving} class="btn-primary btn-md">{saving ? $t('mk.saving') : $t('pf.save')}</button>
      </div>
    </div>
  {/if}
{/if}
