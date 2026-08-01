<script lang="ts">
	import { enhance } from '$app/forms';
	import { invalidateAll } from '$app/navigation';
	import { navigating } from '$app/stores';
	import { tick } from 'svelte';
	import {
		Menu,
		Inbox,
		Star,
		Send,
		FileText,
		Archive,
		ShieldAlert,
		Trash2,
		Search,
		RefreshCw,
		Reply,
		Forward,
		Paperclip,
		X,
		ChevronLeft,
		ChevronRight,
		PenSquare,
		MailOpen,
		Mail as MailIcon,
		MailCheck,
		Bold,
		Italic,
		Underline,
		List as ListIcon,
		Link2,
		Image as ImageIcon,
		Download,
		Settings,
		Trash,
		Maximize2,
		Minimize2,
		Printer,
		BadgeCheck
	} from '@lucide/svelte';

	let { data, form } = $props();

	const folderIcon: Record<string, any> = {
		inbox: Inbox,
		starred: Star,
		sent: Send,
		drafts: FileText,
		archive: Archive,
		junk: ShieldAlert,
		trash: Trash2
	};

	// ── UI state ──
	let sidebarOpen = $state(true);
	let filter = $state<'all' | 'unread'>('all');
	let selected = $state<any>(data.message ?? null);
	let selectedUid = $state<number | null>(data.message?.uid ?? null);
	let thread = $state<any[]>(data.thread ?? []);
	let loadingMsg = $state(false);
	let readerFull = $state(false);

	let starOverride = $state<Record<number, boolean>>({});
	let seenOverride = $state<Record<number, boolean>>({});
	let checked = $state<Set<number>>(new Set());
	let bulkBusy = $state(false);

	// ── composer ──
	let composeOpen = $state(false);
	let composeMin = $state(false);
	let composeFull = $state(false);
	let showCc = $state(false);
	let showBcc = $state(false);
	let sending = $state(false);
	let toastMsg = $state('');
	let settingsOpen = $state(false);
	let signature = $state('');
	const MAX_ATTACH_TOTAL = 20 * 1024 * 1024;

	let compose = $state({ to: '', cc: '', bcc: '', subject: '', in_reply_to: '', references: '' });
	let files = $state<File[]>([]);
	let editorEl: HTMLDivElement | null = null;
	let fileInput: HTMLInputElement | null = null;

	// reset saat pindah folder / halaman / pencarian
	let navKey = $derived(`${data.folderKey}|${data.page}|${data.q}`);
	$effect(() => {
		navKey;
		selected = data.message ?? null;
		selectedUid = data.message?.uid ?? null;
		thread = data.thread ?? [];
		starOverride = {};
		seenOverride = {};
		checked = new Set();
		readerFull = false;
	});

	$effect(() => {
		if (typeof localStorage !== 'undefined') {
			signature = localStorage.getItem('ps_mail_sig') || '';
			const sb = localStorage.getItem('ps_mail_sidebar');
			if (typeof window !== 'undefined' && window.innerWidth <= 820) sidebarOpen = false;
			else if (sb !== null) sidebarOpen = sb === '1';
		}
	});
	function toggleSidebar() {
		sidebarOpen = !sidebarOpen;
		if (typeof localStorage !== 'undefined') localStorage.setItem('ps_mail_sidebar', sidebarOpen ? '1' : '0');
	}

	function saveSignature() {
		if (typeof localStorage !== 'undefined') localStorage.setItem('ps_mail_sig', signature);
		settingsOpen = false;
		toast('Tanda tangan disimpan');
	}

	function starOf(m: any): boolean {
		return m.uid in starOverride ? starOverride[m.uid] : m.flagged;
	}
	function seenOf(m: any): boolean {
		return m.uid in seenOverride ? seenOverride[m.uid] : m.seen;
	}

	function toast(msg: string) {
		toastMsg = msg;
		setTimeout(() => (toastMsg = ''), 3000);
	}

	async function postAction(action: string, fields: Record<string, string | number>) {
		const fd = new FormData();
		for (const [k, v] of Object.entries(fields)) fd.set(k, String(v));
		try {
			await fetch(`/?/${action}`, { method: 'POST', body: fd });
		} catch {
			/* diamkan */
		}
	}

	// ── daftar: filter + kelompok tanggal ──
	let filtered = $derived(filter === 'unread' ? data.messages.filter((m: any) => !seenOf(m)) : data.messages);
	let groups = $derived.by(() => {
		const order = ['Hari ini', 'Kemarin', '7 hari terakhir', 'Bulan ini', 'Lebih lama'];
		const map = new Map<string, any[]>();
		for (const m of filtered) {
			const g = groupOf(m.date);
			if (!map.has(g)) map.set(g, []);
			map.get(g)!.push(m);
		}
		return order.filter((o) => map.has(o)).map((o) => ({ label: o, items: map.get(o)! }));
	});

	function groupOf(iso: string | null): string {
		if (!iso) return 'Lebih lama';
		const d = new Date(iso);
		const now = new Date();
		const sd = new Date(d.getFullYear(), d.getMonth(), d.getDate());
		const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
		const diff = Math.round((today.getTime() - sd.getTime()) / 86400000);
		if (diff <= 0) return 'Hari ini';
		if (diff === 1) return 'Kemarin';
		if (diff < 7) return '7 hari terakhir';
		if (d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth()) return 'Bulan ini';
		return 'Lebih lama';
	}

	// ── buka pesan tanpa reload ──
	async function openMessage(m: any) {
		if (selectedUid === m.uid && selected) return;
		selectedUid = m.uid;
		seenOverride = { ...seenOverride, [m.uid]: true };
		loadingMsg = true;
		selected = null;
		try {
			const r = await fetch(`/message?folder=${encodeURIComponent(data.folderPath)}&uid=${m.uid}`);
			if (r.ok) {
				const d = await r.json();
				selected = d.message;
				thread = d.thread ?? [];
			} else {
				toast('Gagal memuat pesan.');
			}
		} catch {
			toast('Gagal memuat pesan.');
		} finally {
			loadingMsg = false;
		}
		try {
			history.replaceState(history.state, '', `/?folder=${data.folderKey}&uid=${m.uid}${qStr}`);
		} catch {
			/* ignore */
		}
	}
	function closeReader() {
		selected = null;
		selectedUid = null;
		readerFull = false;
		try {
			history.replaceState(history.state, '', `/?folder=${data.folderKey}${qStr}`);
		} catch {
			/* ignore */
		}
	}

	let selIndex = $derived(data.messages.findIndex((m: any) => m.uid === selectedUid));
	function goRel(delta: number) {
		const i = selIndex + delta;
		if (i >= 0 && i < data.messages.length) openMessage(data.messages[i]);
	}

	function toggleStar(m: any, e?: Event) {
		e?.preventDefault();
		e?.stopPropagation();
		const cur = starOf(m);
		starOverride = { ...starOverride, [m.uid]: !cur };
		postAction('star', { uid: m.uid, folder_path: data.folderPath, on: !cur ? 1 : 0 });
	}
	function markUnread(m: any) {
		seenOverride = { ...seenOverride, [m.uid]: false };
		postAction('toggleRead', { uid: m.uid, folder_path: data.folderPath, seen: 0 });
		closeReader();
		toast('Ditandai belum dibaca');
	}

	// ── aksi baris (arsip/hapus) via form enhance ──

	// ── seleksi massal ──
	function toggleCheck(uid: number, e?: Event) {
		e?.stopPropagation();
		const s = new Set(checked);
		s.has(uid) ? s.delete(uid) : s.add(uid);
		checked = s;
	}
	function selectAll() {
		checked = checked.size === filtered.length ? new Set() : new Set(filtered.map((m: any) => m.uid));
	}
	async function bulk(action: 'archive' | 'trash' | 'read') {
		if (!checked.size) return;
		bulkBusy = true;
		const uids = [...checked];
		for (const uid of uids) {
			if (action === 'read') await postAction('toggleRead', { uid, folder_path: data.folderPath, seen: 1 });
			else await postAction(action, { uid, folder_path: data.folderPath, folder_key: data.folderKey });
		}
		checked = new Set();
		await invalidateAll();
		bulkBusy = false;
		toast(action === 'read' ? 'Ditandai dibaca' : action === 'archive' ? 'Diarsipkan' : 'Dipindah ke sampah');
	}

	// ── composer ──
	function escapeHtml(s: string): string {
		return (s || '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]!);
	}
	function sigHtml(): string {
		return signature ? `<br><br>--<br>${escapeHtml(signature).replace(/\n/g, '<br>')}` : '';
	}
	async function openComposer(initialHtml: string) {
		files = [];
		showCc = false;
		showBcc = false;
		composeMin = false;
		composeOpen = true;
		composeFull = typeof window !== 'undefined' && window.innerWidth <= 900;
		await tick();
		if (editorEl) {
			editorEl.innerHTML = initialHtml;
			editorEl.focus();
		}
	}
	function newMail() {
		compose = { to: '', cc: '', bcc: '', subject: '', in_reply_to: '', references: '' };
		openComposer(sigHtml());
	}
	function replyTo(m: any) {
		const original = m.html || `<pre style="white-space:pre-wrap;font-family:inherit">${escapeHtml(m.text || '')}</pre>`;
		const quote = `<div style="color:#5f6368;border-left:2px solid #dadce0;padding-left:12px;margin-top:8px">Pada ${fmtFull(m.date)}, ${escapeHtml(m.fromName)} &lt;${escapeHtml(m.fromAddr)}&gt; menulis:<br>${original}</div>`;
		compose = {
			to: m.fromAddr,
			cc: '',
			bcc: '',
			subject: m.subject?.startsWith('Re:') ? m.subject : `Re: ${m.subject}`,
			in_reply_to: m.messageId || '',
			references: (m.references ? `${m.references} ` : '') + (m.messageId || '')
		};
		openComposer(`${sigHtml()}<br><br>${quote}`);
	}
	function forwardMsg(m: any) {
		const original = m.html || `<pre style="white-space:pre-wrap;font-family:inherit">${escapeHtml(m.text || '')}</pre>`;
		const header = `<div style="color:#5f6368;margin-top:8px">---------- Pesan diteruskan ----------<br>Dari: ${escapeHtml(m.fromName)} &lt;${escapeHtml(m.fromAddr)}&gt;<br>Tanggal: ${fmtFull(m.date)}<br>Subjek: ${escapeHtml(m.subject)}<br>Kepada: ${escapeHtml(m.to)}</div>`;
		compose = {
			to: '',
			cc: '',
			bcc: '',
			subject: m.subject?.startsWith('Fwd:') ? m.subject : `Fwd: ${m.subject}`,
			in_reply_to: '',
			references: ''
		};
		openComposer(`${sigHtml()}<br><br>${header}<br>${original}`);
	}
	function exec(cmd: string, val?: string) {
		editorEl?.focus();
		document.execCommand(cmd, false, val);
	}
	function insertLink() {
		const url = prompt('Tautan URL:');
		if (url) exec('createLink', url);
	}
	function addFiles(e: Event) {
		const input = e.target as HTMLInputElement;
		if (input.files) {
			const next = [...files, ...Array.from(input.files)];
			if (next.reduce((s, f) => s + f.size, 0) > MAX_ATTACH_TOTAL) toast(`Total lampiran maksimal ${fmtSize(MAX_ATTACH_TOTAL)}.`);
			else files = next;
		}
		input.value = '';
	}
	function removeFile(i: number) {
		files = files.filter((_, idx) => idx !== i);
	}

	// ── format ──
	function fmtDate(iso: string | null): string {
		if (!iso) return '';
		const d = new Date(iso);
		const now = new Date();
		if (d.toDateString() === now.toDateString())
			return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
		const opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short' };
		if (d.getFullYear() !== now.getFullYear()) opts.year = 'numeric';
		return d.toLocaleDateString('id-ID', opts);
	}
	function fmtFull(iso: string | null): string {
		if (!iso) return '';
		return new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
	}
	function fmtSize(n: number): string {
		if (!n) return '';
		if (n < 1024) return `${n} B`;
		if (n < 1024 * 1024) return `${(n / 1024).toFixed(0)} KB`;
		return `${(n / 1024 / 1024).toFixed(1)} MB`;
	}
	function initial(name: string): string {
		return (name || '?').trim().charAt(0).toUpperCase();
	}
	const avatarColors = ['#1f6feb', '#0b8043', '#d93025', '#e37400', '#8430ce', '#0b7285', '#c2185b', '#5f6368'];
	function avColor(s: string): string {
		let h = 0;
		for (let i = 0; i < (s || '').length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
		return avatarColors[h % avatarColors.length];
	}
	function attUrl(uid: number, i: number, view = false): string {
		return `/attachment?folder=${encodeURIComponent(data.folderPath)}&uid=${uid}&i=${i}${view ? '&view=1' : ''}`;
	}
	function isImage(ct: string): boolean {
		return /^image\//.test(ct || '');
	}
	let qStr = $derived(data.q ? `&q=${encodeURIComponent(data.q)}` : '');
	let allChecked = $derived(filtered.length > 0 && checked.size === filtered.length);
</script>

{#if $navigating}
	<div class="topbar-progress"></div>
{/if}

<div class="app" class:collapsed={!sidebarOpen}>
	<!-- ═══════════ SIDEBAR ═══════════ -->
	<aside class="sb">
		<div class="sb-head">
			<button class="hamb" onclick={toggleSidebar} aria-label="Menu"><Menu size={20} /></button>
			<span class="sb-brand">Portal<b>SI</b></span>
		</div>

		<button class="compose" onclick={newMail}>
			<PenSquare size={18} />
			<span class="lbl">Tulis</span>
		</button>

		<nav class="folders">
			{#each data.folders as f (f.key)}
				{@const Icon = folderIcon[f.key] ?? Inbox}
				<a href="/?folder={f.key}" class="fitem" class:active={f.key === data.folderKey} title={f.label} onclick={() => { if (typeof window !== 'undefined' && window.innerWidth <= 820) sidebarOpen = false; }}>
					<span class="fico"><Icon size={19} /></span>
					<span class="lbl">{f.label}</span>
					{#if f.unseen}<b class="count">{f.unseen}</b>{/if}
				</a>
			{/each}
		</nav>

		<div class="sb-foot">
			<span class="avatar sm" style="background:{avColor(data.account?.email || '')}">{initial(data.account?.email || 'U')}</span>
			<span class="me lbl" title={data.account?.email}>{data.account?.email}</span>
			<button class="gear" title="Pengaturan" aria-label="Pengaturan" onclick={() => (settingsOpen = true)}><Settings size={17} /></button>
		</div>
	</aside>

	{#if sidebarOpen}
		<button class="sb-backdrop" onclick={toggleSidebar} aria-label="Tutup menu"></button>
	{/if}

	<!-- ═══════════ DAFTAR ═══════════ -->
	<section class="listpane" class:hide-on-mobile={selected || loadingMsg}>
		<div class="lp-head">
			<button class="hamb only-mobile" onclick={toggleSidebar} aria-label="Menu"><Menu size={20} /></button>
			<button class="chk head" class:on={allChecked} onclick={selectAll} aria-label="Pilih semua">
				{#if allChecked}<span class="tick">✓</span>{/if}
			</button>
			<div class="segs">
				<button class:on={filter === 'all'} onclick={() => (filter = 'all')}>Semua</button>
				<button class:on={filter === 'unread'} onclick={() => (filter = 'unread')}>Belum dibaca</button>
			</div>
			<a class="icon-btn" href="/?folder={data.folderKey}{qStr}" title="Muat ulang"><RefreshCw size={17} /></a>
		</div>

		<form class="lp-search" method="GET">
			<Search size={16} />
			<input name="q" value={data.q} placeholder="Cari email…" />
			<input type="hidden" name="folder" value={data.folderKey} />
		</form>

		{#if checked.size}
			<div class="bulkbar">
				<span>{checked.size} dipilih</span>
				<div class="bulk-actions">
					<button onclick={() => bulk('read')} disabled={bulkBusy} title="Tandai dibaca"><MailCheck size={16} /></button>
					<button onclick={() => bulk('archive')} disabled={bulkBusy} title="Arsipkan"><Archive size={16} /></button>
					<button onclick={() => bulk('trash')} disabled={bulkBusy} title="Hapus"><Trash2 size={16} /></button>
					{#if bulkBusy}<span class="spin dark"></span>{/if}
				</div>
			</div>
		{/if}

		<div class="lp-scroll">
			{#if $navigating}
				{#each Array(9) as _, i (i)}
					<div class="sk-row">
						<span class="sk sk-av"></span>
						<div class="sk-lines"><span class="sk sk-l1"></span><span class="sk sk-l2"></span></div>
					</div>
				{/each}
			{:else if filtered.length === 0}
				<div class="empty small">
					<MailOpen size={38} />
					<p>{data.q ? 'Tidak ada hasil.' : filter === 'unread' ? 'Semua sudah dibaca 🎉' : 'Folder ini kosong.'}</p>
				</div>
			{:else}
				{#each groups as g (g.label)}
					<div class="grp-h">{g.label}</div>
					{#each g.items as m (m.uid)}
						<div
							class="row"
							class:unseen={!seenOf(m)}
							class:sel={selectedUid === m.uid}
							class:checked={checked.has(m.uid)}
							onclick={() => openMessage(m)}
							role="button"
							tabindex="0"
							onkeydown={(e) => e.key === 'Enter' && openMessage(m)}
						>
							<button class="chk" class:on={checked.has(m.uid)} onclick={(e) => toggleCheck(m.uid, e)} aria-label="Pilih">
								{#if checked.has(m.uid)}<span class="tick">✓</span>{/if}
							</button>
							<button class="star" class:on={starOf(m)} onclick={(e) => toggleStar(m, e)} aria-label="Bintang">
								<Star size={16} fill={starOf(m) ? 'currentColor' : 'none'} />
							</button>
							<span class="avatar" style="background:{avColor(m.fromAddr)}">{initial(m.fromName)}</span>
							<div class="rbody">
								<div class="rline1">
									<span class="who">{data.folderKey === 'sent' ? m.to : m.fromName}</span>
									<span class="date">{fmtDate(m.date)}</span>
								</div>
								<div class="rline2">
									{#if !seenOf(m)}<span class="dot"></span>{/if}
									<span class="subj">{m.subject}</span>
									{#if m.attachments}<Paperclip size={13} class="clip" />{/if}
								</div>
							</div>
						</div>
					{/each}
				{/each}
			{/if}

			{#if data.pages > 1 && !$navigating}
				<div class="pager">
					<a class="icon-btn" class:disabled={data.page <= 1} href="/?folder={data.folderKey}&page={data.page - 1}{qStr}"><ChevronLeft size={18} /></a>
					<span>{data.page} / {data.pages}</span>
					<a class="icon-btn" class:disabled={data.page >= data.pages} href="/?folder={data.folderKey}&page={data.page + 1}{qStr}"><ChevronRight size={18} /></a>
				</div>
			{/if}
		</div>
	</section>

	<!-- ═══════════ PANEL BACA ═══════════ -->
	<section class="readpane" class:full={readerFull} class:show-on-mobile={selected || loadingMsg}>
		{#if loadingMsg}
			<div class="rd-skeleton">
				<span class="sk sk-title"></span>
				<div class="sk-meta"><span class="sk sk-av2"></span><span class="sk sk-name"></span></div>
				<span class="sk sk-block"></span>
				<span class="sk sk-line"></span>
				<span class="sk sk-line"></span>
				<span class="sk sk-line short"></span>
			</div>
		{:else if selected}
			<div class="rd-toolbar">
				<div class="rd-nav">
					<button class="icon-btn only-mobile" onclick={closeReader} aria-label="Kembali"><ChevronLeft size={20} /></button>
					{#if selIndex >= 0}<span class="counter">{selIndex + 1} dari {data.total}</span>{/if}
					<button class="icon-btn" disabled={selIndex <= 0} onclick={() => goRel(-1)} aria-label="Sebelumnya"><ChevronLeft size={18} /></button>
					<button class="icon-btn" disabled={selIndex < 0 || selIndex >= data.messages.length - 1} onclick={() => goRel(1)} aria-label="Berikutnya"><ChevronRight size={18} /></button>
				</div>
				<div class="rd-tools">
					<button class="icon-btn" class:on={selected.flagged} onclick={() => toggleStar(selected)} aria-label="Bintang"><Star size={18} fill={selected.flagged ? 'currentColor' : 'none'} /></button>
					<form method="POST" action="?/archive" use:enhance={() => { return async ({ update }) => { closeReader(); await update(); }; }}>
						<input type="hidden" name="uid" value={selected.uid} /><input type="hidden" name="folder_path" value={data.folderPath} /><input type="hidden" name="folder_key" value={data.folderKey} />
						<button class="icon-btn" aria-label="Arsipkan"><Archive size={18} /></button>
					</form>
					<form method="POST" action="?/trash" use:enhance={() => { return async ({ update }) => { closeReader(); await update(); }; }}>
						<input type="hidden" name="uid" value={selected.uid} /><input type="hidden" name="folder_path" value={data.folderPath} /><input type="hidden" name="folder_key" value={data.folderKey} />
						<button class="icon-btn" aria-label="Hapus"><Trash2 size={18} /></button>
					</form>
					<button class="icon-btn only-desktop" onclick={() => window.print()} aria-label="Cetak"><Printer size={18} /></button>
					<button class="icon-btn only-desktop" onclick={() => (readerFull = !readerFull)} aria-label="Layar penuh">
						{#if readerFull}<Minimize2 size={17} />{:else}<Maximize2 size={17} />{/if}
					</button>
				</div>
			</div>

			<div class="rd-scroll">
				<h1 class="rd-subject">{selected.subject}</h1>
				<div class="rd-sender">
					<span class="avatar lg" style="background:{avColor(selected.fromAddr)}">{initial(selected.fromName)}</span>
					<div class="rs-info">
						<div class="rs-name">{selected.fromName} <BadgeCheck size={15} class="verified" /></div>
						<div class="rs-addr">{selected.fromAddr}</div>
					</div>
					<div class="rs-date">{fmtFull(selected.date)}</div>
				</div>
				{#if selected.to}<div class="rd-to">ke {selected.to}</div>{/if}

				{#if selected.attachments.filter((a: any) => !a.inline).length}
					<div class="attachments">
						{#each selected.attachments.filter((a: any) => !a.inline) as a}
							<a class="att" href={attUrl(selected.uid, a.index)} title="Unduh {a.filename}">
								{#if isImage(a.contentType)}<img class="att-thumb" src={attUrl(selected.uid, a.index, true)} alt={a.filename} />
								{:else}<span class="att-ico"><Paperclip size={16} /></span>{/if}
								<span class="att-info"><span class="att-name">{a.filename}</span><span class="att-size">{fmtSize(a.size)}</span></span>
								<Download size={15} />
							</a>
						{/each}
					</div>
				{/if}

				<div class="rd-body">
					{#if selected.html}
						<iframe title="Isi email" sandbox="" srcdoc={selected.html} class="htmlframe"></iframe>
					{:else}
						<pre class="textbody">{selected.text || '(pesan kosong)'}</pre>
					{/if}
				</div>

				{#if thread.length}
					<div class="thread">
						<div class="thread-h">Percakapan ini ({thread.length + 1})</div>
						{#each thread as t (t.uid)}
							<button class="thread-item" onclick={() => openMessage(t)}>
								<span class="avatar sm" style="background:{avColor(t.fromAddr)}">{initial(t.fromName)}</span>
								<span class="ti-who">{t.fromName}</span>
								<span class="ti-date">{fmtDate(t.date)}</span>
							</button>
						{/each}
					</div>
				{/if}
			</div>

			<div class="rd-actionbar">
				<button class="pill primary" onclick={() => replyTo(selected)}><Reply size={16} /> Balas</button>
				<button class="pill" onclick={() => forwardMsg(selected)}><Forward size={16} /> Teruskan</button>
				<button class="pill ghost" onclick={() => markUnread(selected)}><MailIcon size={16} /> Tandai belum dibaca</button>
			</div>
		{:else}
			<div class="empty">
				<div class="empty-ill"><MailOpen size={52} /></div>
				<h3>Pilih email untuk dibaca</h3>
				<p>Klik salah satu pesan di daftar, atau tulis email baru.</p>
				<button class="pill primary" onclick={newMail}><PenSquare size={16} /> Tulis email</button>
			</div>
		{/if}
	</section>
</div>

{#if !selected && !loadingMsg && !composeOpen}
	<button class="fab only-mobile" onclick={newMail} aria-label="Tulis email"><PenSquare size={22} /></button>
{/if}

<!-- ═══════════ COMPOSER ═══════════ -->
{#if composeOpen}
	<div class="composer" class:min={composeMin} class:full={composeFull}>
		<header class="cp-head">
			<span><MailIcon size={15} /> Pesan baru</span>
			<div class="cp-hbtns">
				<button class="cp-hbtn" onclick={() => (composeMin = !composeMin)} aria-label="Kecilkan">
					{#if composeMin}<ChevronRight size={15} style="transform:rotate(-90deg)" />{:else}<ChevronRight size={15} style="transform:rotate(90deg)" />{/if}
				</button>
				<button class="cp-hbtn" onclick={() => { composeFull = !composeFull; composeMin = false; }} aria-label="Layar penuh">
					{#if composeFull}<Minimize2 size={14} />{:else}<Maximize2 size={14} />{/if}
				</button>
				<button class="cp-hbtn" onclick={() => (composeOpen = false)} aria-label="Tutup"><X size={16} /></button>
			</div>
		</header>
		{#if !composeMin}
			<form
				method="POST"
				action="?/send"
				enctype="multipart/form-data"
				use:enhance={({ formData, cancel }) => {
					if (!compose.to.trim()) { cancel(); return; }
					formData.set('html', editorEl?.innerHTML ?? '');
					formData.set('body', editorEl?.innerText ?? '');
					for (const file of files) formData.append('files', file);
					sending = true;
					return async ({ result, update }) => {
						sending = false;
						if (result.type === 'success' && (result.data as any)?.sent) {
							composeOpen = false;
							files = [];
							toast('Email terkirim');
						} else {
							await update({ reset: false });
						}
					};
				}}
			>
				<div class="cp-field">
					<label>Ke</label>
					<input name="to" bind:value={compose.to} placeholder="penerima@contoh.com" required />
					<div class="cp-ccbtns">
						{#if !showCc}<button type="button" onclick={() => (showCc = true)}>Cc</button>{/if}
						{#if !showBcc}<button type="button" onclick={() => (showBcc = true)}>Bcc</button>{/if}
					</div>
				</div>
				{#if showCc}<div class="cp-field"><label>Cc</label><input name="cc" bind:value={compose.cc} /></div>{/if}
				{#if showBcc}<div class="cp-field"><label>Bcc</label><input name="bcc" bind:value={compose.bcc} /></div>{/if}
				<div class="cp-field"><label>Subjek</label><input name="subject" bind:value={compose.subject} placeholder="Subjek" /></div>

				<div class="cp-toolbar">
					<button type="button" title="Tebal" onclick={() => exec('bold')}><Bold size={16} /></button>
					<button type="button" title="Miring" onclick={() => exec('italic')}><Italic size={16} /></button>
					<button type="button" title="Garis bawah" onclick={() => exec('underline')}><Underline size={16} /></button>
					<button type="button" title="Daftar" onclick={() => exec('insertUnorderedList')}><ListIcon size={16} /></button>
					<button type="button" title="Tautan" onclick={insertLink}><Link2 size={16} /></button>
					<button type="button" title="Lampirkan" onclick={() => fileInput?.click()}><Paperclip size={16} /></button>
				</div>

				<div class="cp-editor" contenteditable="true" bind:this={editorEl} role="textbox" tabindex="0" aria-label="Isi pesan"></div>
				<input type="file" multiple bind:this={fileInput} onchange={addFiles} hidden />

				{#if files.length}
					<div class="cp-atts">
						{#each files as file, i}
							<span class="cp-att"><Paperclip size={13} /> {file.name} <span class="sz">{fmtSize(file.size)}</span>
								<button type="button" onclick={() => removeFile(i)} aria-label="Hapus"><X size={13} /></button></span>
						{/each}
					</div>
				{/if}

				<input type="hidden" name="in_reply_to" value={compose.in_reply_to} />
				<input type="hidden" name="references" value={compose.references} />
				{#if (form as any)?.sendError}<p class="cp-err">{(form as any).sendError}</p>{/if}

				<div class="cp-foot">
					<button class="cp-send" disabled={sending}>
						{#if sending}<span class="spin"></span>{:else}<Send size={16} /> Kirim{/if}
					</button>
					<button type="button" class="cp-icon" title="Lampirkan" onclick={() => fileInput?.click()}><Paperclip size={17} /></button>
					<button type="button" class="cp-icon" title="Sisipkan gambar" onclick={() => fileInput?.click()}><ImageIcon size={17} /></button>
					<button type="button" class="cp-icon danger" title="Buang" onclick={() => (composeOpen = false)}><Trash size={17} /></button>
				</div>
			</form>
		{/if}
	</div>
{/if}

<!-- ═══════════ PENGATURAN ═══════════ -->
{#if settingsOpen}
	<div class="modal-bg" onclick={() => (settingsOpen = false)} role="presentation">
		<div class="modal" onclick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
			<header class="mh"><b>Pengaturan</b><button onclick={() => (settingsOpen = false)} aria-label="Tutup"><X size={16} /></button></header>
			<label class="ml">Tanda tangan</label>
			<textarea class="mt" bind:value={signature} placeholder="Nama, jabatan, dsb — ditambahkan otomatis di email baru."></textarea>
			<div class="mf"><button class="cp-send" onclick={saveSignature}>Simpan</button></div>
		</div>
	</div>
{/if}

{#if toastMsg}<div class="mail-toast">{toastMsg}</div>{/if}

<style>
	:global(body) {
		background: #f1f3f6;
	}
	.app {
		display: grid;
		grid-template-columns: 256px 384px 1fr;
		width: 100%;
		height: calc(100dvh - 56px);
		background: #f1f3f6;
		transition: grid-template-columns 0.22s cubic-bezier(0.4, 0, 0.2, 1);
	}
	.app.collapsed {
		grid-template-columns: 74px 384px 1fr;
	}

	/* progress bar atas */
	.topbar-progress {
		position: fixed;
		top: 0;
		left: 0;
		height: 3px;
		width: 100%;
		z-index: 200;
		background: linear-gradient(90deg, #1f6feb, #8430ce, #1f6feb);
		background-size: 200% 100%;
		animation: progress 1.1s linear infinite;
	}
	@keyframes -global-progress {
		0% { background-position: 200% 0; }
		100% { background-position: -200% 0; }
	}

	/* ═══ SIDEBAR ═══ */
	.sb {
		display: flex;
		flex-direction: column;
		gap: 4px;
		padding: 12px 12px 14px;
		min-height: 0;
		overflow: hidden;
	}
	.sb-head {
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 6px 6px 10px;
	}
	.hamb {
		display: grid;
		place-items: center;
		width: 40px;
		height: 40px;
		border: 0;
		border-radius: 50%;
		background: transparent;
		color: #444a54;
		cursor: pointer;
		flex: none;
		transition: background 0.13s;
	}
	.hamb:hover {
		background: #e4e8ee;
	}
	.sb-brand {
		font-weight: 800;
		font-size: 1.15rem;
		letter-spacing: -0.02em;
		color: #202124;
		white-space: nowrap;
	}
	.sb-brand b {
		color: #1f6feb;
	}
	.compose {
		display: inline-flex;
		align-items: center;
		gap: 12px;
		align-self: flex-start;
		padding: 14px 22px;
		border: 0;
		border-radius: 16px;
		background: #1f6feb;
		color: #fff;
		font-weight: 600;
		font-size: 0.95rem;
		cursor: pointer;
		box-shadow: 0 4px 14px -4px rgba(31, 111, 235, 0.7);
		margin: 2px 2px 12px;
		transition: transform 0.12s, box-shadow 0.15s;
	}
	.compose:hover {
		box-shadow: 0 6px 18px -4px rgba(31, 111, 235, 0.85);
		transform: translateY(-1px);
	}
	.compose:active {
		transform: translateY(0);
	}
	.folders {
		display: flex;
		flex-direction: column;
		gap: 1px;
		overflow-y: auto;
		min-height: 0;
	}
	.fitem {
		display: flex;
		align-items: center;
		gap: 16px;
		padding: 10px 14px;
		border-radius: 12px;
		color: #444a54;
		text-decoration: none;
		font-size: 0.92rem;
		font-weight: 500;
		white-space: nowrap;
		transition: background 0.13s, color 0.13s;
	}
	.fico {
		display: grid;
		place-items: center;
		flex: none;
	}
	.fitem:hover {
		background: #e4e8ee;
	}
	.fitem.active {
		background: #dbe8ff;
		color: #0b57d0;
		font-weight: 700;
	}
	.fitem .count {
		margin-left: auto;
		font-size: 0.76rem;
		background: rgba(11, 87, 208, 0.12);
		color: #0b57d0;
		padding: 1px 8px;
		border-radius: 10px;
	}
	.sb-foot {
		margin-top: auto;
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 10px 8px 0;
		border-top: 1px solid #e2e6ec;
	}
	.me {
		flex: 1;
		font-size: 0.78rem;
		color: #5f6368;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.gear {
		display: grid;
		place-items: center;
		width: 34px;
		height: 34px;
		border: 0;
		border-radius: 50%;
		background: transparent;
		color: #5f6368;
		cursor: pointer;
		flex: none;
		transition: background 0.13s;
	}
	.gear:hover {
		background: #e4e8ee;
	}
	/* collapsed: sembunyikan label */
	.app.collapsed .lbl,
	.app.collapsed .count,
	.app.collapsed .sb-brand {
		display: none;
	}
	.app.collapsed .compose {
		padding: 14px;
		border-radius: 50%;
		align-self: center;
	}
	.app.collapsed .fitem {
		justify-content: center;
		gap: 0;
		padding: 10px;
	}
	.app.collapsed .sb-foot {
		justify-content: center;
	}
	.app.collapsed .me {
		display: none;
	}
	.app.collapsed .gear {
		display: none;
	}

	/* ═══ DAFTAR ═══ */
	.listpane {
		display: flex;
		flex-direction: column;
		min-height: 0;
		background: #fff;
		border-radius: 16px 0 0 0;
		margin-top: 8px;
		box-shadow: 0 0 0 1px #e6e9ef;
		overflow: hidden;
	}
	.lp-head {
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 12px 14px 8px;
	}
	.segs {
		display: flex;
		gap: 4px;
		flex: 1;
	}
	.segs button {
		border: 0;
		background: transparent;
		color: #5f6368;
		font-size: 0.85rem;
		font-weight: 600;
		padding: 6px 12px;
		border-radius: 999px;
		cursor: pointer;
		transition: background 0.13s, color 0.13s;
	}
	.segs button:hover {
		background: #f0f2f5;
	}
	.segs button.on {
		background: #dbe8ff;
		color: #0b57d0;
	}
	.chk {
		display: grid;
		place-items: center;
		width: 20px;
		height: 20px;
		border: 2px solid #c3c9d4;
		border-radius: 6px;
		background: #fff;
		color: #fff;
		cursor: pointer;
		flex: none;
		font-size: 0.7rem;
		transition: background 0.12s, border-color 0.12s;
	}
	.chk.on {
		background: #1f6feb;
		border-color: #1f6feb;
	}
	.chk .tick {
		line-height: 1;
	}
	.lp-search {
		display: flex;
		align-items: center;
		gap: 10px;
		margin: 0 14px 8px;
		padding: 9px 14px;
		background: #eef1f6;
		border-radius: 12px;
		color: #5f6368;
	}
	.lp-search:focus-within {
		background: #fff;
		box-shadow: 0 0 0 2px #1f6feb;
	}
	.lp-search input {
		flex: 1;
		border: 0;
		background: transparent;
		font: inherit;
		outline: none;
	}
	.bulkbar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin: 0 12px 6px;
		padding: 7px 12px;
		background: #eef4ff;
		border: 1px solid #cfe0ff;
		border-radius: 10px;
		font-size: 0.82rem;
		color: #0b57d0;
		font-weight: 600;
	}
	.bulk-actions {
		display: flex;
		align-items: center;
		gap: 4px;
	}
	.bulk-actions button {
		display: grid;
		place-items: center;
		width: 30px;
		height: 30px;
		border: 0;
		border-radius: 8px;
		background: transparent;
		color: #0b57d0;
		cursor: pointer;
	}
	.bulk-actions button:hover {
		background: #d7e6ff;
	}
	.lp-scroll {
		flex: 1;
		overflow-y: auto;
		min-height: 0;
		padding-bottom: 8px;
	}
	.grp-h {
		position: sticky;
		top: 0;
		z-index: 2;
		padding: 10px 16px 6px;
		font-size: 0.72rem;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: #80868b;
		background: linear-gradient(#fff 70%, rgba(255, 255, 255, 0));
	}
	.row {
		display: grid;
		grid-template-columns: 20px 24px 40px 1fr;
		align-items: center;
		gap: 10px;
		padding: 9px 16px;
		cursor: pointer;
		border-left: 3px solid transparent;
		transition: background 0.12s;
	}
	.row:hover {
		background: #f5f7fa;
	}
	.row .chk {
		opacity: 0;
		transition: opacity 0.12s;
	}
	.row:hover .chk,
	.row.checked .chk {
		opacity: 1;
	}
	.row.sel {
		background: #eaf1fe;
		border-left-color: #1f6feb;
	}
	.row.checked {
		background: #eef4ff;
	}
	.star {
		display: grid;
		place-items: center;
		width: 24px;
		height: 24px;
		border: 0;
		background: transparent;
		color: #c3c7cf;
		cursor: pointer;
		transition: color 0.13s, transform 0.1s;
	}
	.star:hover {
		color: #9aa0a6;
	}
	.star.on {
		color: #f4b400;
	}
	.star:active {
		transform: scale(1.25);
	}
	.avatar {
		display: grid;
		place-items: center;
		width: 40px;
		height: 40px;
		border-radius: 50%;
		background: #1f6feb;
		color: #fff;
		font-weight: 700;
		flex: none;
	}
	.avatar.sm {
		width: 32px;
		height: 32px;
		font-size: 0.82rem;
	}
	.avatar.lg {
		width: 44px;
		height: 44px;
	}
	.rbody {
		min-width: 0;
	}
	.rline1 {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		gap: 8px;
	}
	.who {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		font-size: 0.9rem;
		color: #202124;
	}
	.row.unseen .who {
		font-weight: 700;
	}
	.date {
		font-size: 0.74rem;
		color: #80868b;
		white-space: nowrap;
		flex: none;
	}
	.rline2 {
		display: flex;
		align-items: center;
		gap: 6px;
		margin-top: 1px;
	}
	.dot {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		background: #1f6feb;
		flex: none;
	}
	.subj {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		font-size: 0.83rem;
		color: #5f6368;
		flex: 1;
	}
	.row.unseen .subj {
		color: #3c4043;
		font-weight: 500;
	}
	:global(.clip) {
		color: #9aa0a6;
		flex: none;
	}
	.pager {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 12px;
		padding: 14px;
		color: #5f6368;
		font-size: 0.84rem;
	}

	/* ═══ PANEL BACA ═══ */
	.readpane {
		display: flex;
		flex-direction: column;
		min-height: 0;
		background: #fff;
		margin: 8px 8px 0 8px;
		border-radius: 16px;
		box-shadow: 0 0 0 1px #e6e9ef;
		overflow: hidden;
	}
	.readpane.full {
		position: fixed;
		inset: 56px 0 0 0;
		margin: 0;
		border-radius: 0;
		z-index: 40;
	}
	.rd-toolbar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 10px 14px;
		border-bottom: 1px solid #eef1f5;
		flex: none;
	}
	.rd-nav,
	.rd-tools {
		display: flex;
		align-items: center;
		gap: 2px;
	}
	.rd-tools form {
		margin: 0;
		display: inline-flex;
	}
	.counter {
		font-size: 0.8rem;
		color: #5f6368;
		margin-right: 6px;
		white-space: nowrap;
	}
	.icon-btn {
		display: grid;
		place-items: center;
		width: 38px;
		height: 38px;
		border-radius: 50%;
		border: 0;
		background: transparent;
		color: #5f6368;
		cursor: pointer;
		text-decoration: none;
		transition: background 0.13s, color 0.13s;
	}
	.icon-btn:hover {
		background: #f0f2f5;
	}
	.icon-btn.on {
		color: #f4b400;
	}
	.icon-btn:disabled {
		opacity: 0.3;
		pointer-events: none;
	}
	.icon-btn.disabled {
		opacity: 0.3;
		pointer-events: none;
	}
	.rd-scroll {
		flex: 1;
		overflow-y: auto;
		min-height: 0;
		padding: 22px 30px;
	}
	.rd-subject {
		font-size: 1.5rem;
		font-weight: 500;
		margin: 0 0 18px;
		line-height: 1.3;
		color: #202124;
	}
	.rd-sender {
		display: flex;
		align-items: center;
		gap: 12px;
	}
	.rs-info {
		min-width: 0;
	}
	.rs-name {
		display: flex;
		align-items: center;
		gap: 5px;
		font-weight: 600;
		font-size: 0.95rem;
		color: #202124;
	}
	:global(.verified) {
		color: #1f6feb;
	}
	.rs-addr {
		font-size: 0.83rem;
		color: #5f6368;
	}
	.rs-date {
		margin-left: auto;
		font-size: 0.8rem;
		color: #5f6368;
		white-space: nowrap;
	}
	.rd-to {
		font-size: 0.82rem;
		color: #5f6368;
		margin: 6px 0 0 56px;
	}
	.attachments {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		margin: 18px 0 4px;
	}
	.att {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		padding: 8px 12px;
		border: 1px solid #e0e3e8;
		border-radius: 10px;
		font-size: 0.82rem;
		color: #3c4043;
		text-decoration: none;
		background: #fff;
		max-width: 260px;
		transition: background 0.13s, border-color 0.13s;
	}
	.att:hover {
		background: #f8fafd;
		border-color: #c9d2e0;
	}
	.att-thumb {
		width: 38px;
		height: 38px;
		object-fit: cover;
		border-radius: 6px;
		flex: none;
	}
	.att-ico {
		display: grid;
		place-items: center;
		width: 38px;
		height: 38px;
		border-radius: 6px;
		background: #eef3fb;
		color: #1f6feb;
		flex: none;
	}
	.att-info {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}
	.att-name {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		font-weight: 500;
	}
	.att-size {
		color: #80868b;
		font-size: 0.74rem;
	}
	.rd-body {
		margin-top: 18px;
		min-height: 260px;
	}
	.htmlframe {
		width: 100%;
		height: 62vh;
		min-height: 320px;
		border: 0;
		background: #fff;
	}
	.readpane.full .htmlframe {
		height: 72vh;
	}
	.textbody {
		white-space: pre-wrap;
		word-break: break-word;
		font-family: inherit;
		font-size: 0.94rem;
		line-height: 1.65;
		margin: 0;
		color: #202124;
	}
	.thread {
		margin-top: 22px;
		border-top: 1px solid #eef1f5;
		padding-top: 12px;
	}
	.thread-h {
		font-size: 0.78rem;
		color: #80868b;
		font-weight: 700;
		margin-bottom: 8px;
	}
	.thread-item {
		display: flex;
		width: 100%;
		align-items: center;
		gap: 12px;
		padding: 8px 10px;
		border: 0;
		background: transparent;
		border-radius: 10px;
		cursor: pointer;
		color: #3c4043;
		font-size: 0.86rem;
		text-align: left;
	}
	.thread-item:hover {
		background: #f5f7fa;
	}
	.ti-who {
		flex: 1;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.ti-date {
		color: #80868b;
		font-size: 0.78rem;
	}
	.rd-actionbar {
		display: flex;
		gap: 10px;
		padding: 12px 18px;
		border-top: 1px solid #eef1f5;
		flex: none;
		flex-wrap: wrap;
	}
	.pill {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 9px 18px;
		border: 1px solid #d5dae2;
		border-radius: 20px;
		background: #fff;
		color: #3c4043;
		font-weight: 500;
		font-size: 0.88rem;
		cursor: pointer;
		transition: background 0.13s, box-shadow 0.13s, transform 0.1s;
	}
	.pill:hover {
		background: #f5f7fa;
	}
	.pill:active {
		transform: translateY(1px);
	}
	.pill.primary {
		background: #1f6feb;
		border-color: #1f6feb;
		color: #fff;
	}
	.pill.primary:hover {
		background: #1a5fd0;
	}
	.pill.ghost {
		border-color: transparent;
		color: #5f6368;
	}
	/* empty state */
	.empty {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 10px;
		color: #80868b;
		text-align: center;
		padding: 40px;
	}
	.empty.small {
		padding: 70px 20px;
	}
	.empty-ill {
		display: grid;
		place-items: center;
		width: 104px;
		height: 104px;
		border-radius: 50%;
		background: #eef3fb;
		color: #1f6feb;
		margin-bottom: 6px;
	}
	.empty h3 {
		margin: 0;
		color: #3c4043;
		font-size: 1.1rem;
	}
	.empty p {
		margin: 0 0 10px;
		font-size: 0.88rem;
	}

	/* ═══ SKELETON ═══ */
	.sk {
		display: block;
		background: linear-gradient(90deg, #eceff3 25%, #f6f8fb 37%, #eceff3 63%);
		background-size: 400% 100%;
		animation: shimmer 1.3s ease infinite;
		border-radius: 8px;
	}
	@keyframes -global-shimmer {
		0% { background-position: 100% 0; }
		100% { background-position: -100% 0; }
	}
	.sk-row {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 12px 16px;
	}
	.sk-av {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		flex: none;
	}
	.sk-lines {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 7px;
	}
	.sk-l1 {
		height: 10px;
		width: 60%;
	}
	.sk-l2 {
		height: 9px;
		width: 85%;
	}
	.rd-skeleton {
		padding: 26px 30px;
		display: flex;
		flex-direction: column;
		gap: 14px;
	}
	.sk-title {
		height: 26px;
		width: 55%;
	}
	.sk-meta {
		display: flex;
		align-items: center;
		gap: 12px;
	}
	.sk-av2 {
		width: 44px;
		height: 44px;
		border-radius: 50%;
	}
	.sk-name {
		height: 12px;
		width: 180px;
	}
	.sk-block {
		height: 180px;
		width: 100%;
		margin-top: 8px;
	}
	.sk-line {
		height: 11px;
		width: 100%;
	}
	.sk-line.short {
		width: 45%;
	}

	/* ═══ COMPOSER ═══ */
	.composer {
		position: fixed;
		right: 24px;
		bottom: 0;
		width: min(94vw, 560px);
		background: #fff;
		border-radius: 14px 14px 0 0;
		box-shadow: 0 -2px 32px rgba(0, 0, 0, 0.32);
		z-index: 120;
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
	.composer.min {
		width: 300px;
	}
	.composer.full {
		right: 50%;
		transform: translateX(50%);
		bottom: 3vh;
		width: min(96vw, 880px);
		height: 92vh;
		border-radius: 14px;
	}
	.composer.full form {
		flex: 1;
		min-height: 0;
	}
	.composer.full .cp-editor {
		flex: 1;
		max-height: none;
	}
	.cp-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px 12px 12px 18px;
		background: #202124;
		color: #fff;
		font-weight: 600;
		font-size: 0.88rem;
	}
	.cp-head span {
		display: inline-flex;
		align-items: center;
		gap: 8px;
	}
	.cp-hbtns {
		display: flex;
		gap: 2px;
	}
	.cp-hbtn {
		display: grid;
		place-items: center;
		width: 28px;
		height: 28px;
		border: 0;
		border-radius: 7px;
		background: transparent;
		cursor: pointer;
		color: #cdd0d5;
		transition: background 0.12s;
	}
	.cp-hbtn:hover {
		background: rgba(255, 255, 255, 0.15);
	}
	.composer form {
		display: flex;
		flex-direction: column;
		padding: 4px 18px 14px;
	}
	.cp-field {
		display: flex;
		align-items: center;
		gap: 10px;
		border-bottom: 1px solid #eef1f5;
	}
	.cp-field label {
		width: 44px;
		color: #5f6368;
		font-size: 0.85rem;
	}
	.cp-field input {
		flex: 1;
		border: 0;
		padding: 11px 0;
		font: inherit;
		outline: none;
	}
	.cp-ccbtns {
		display: flex;
		gap: 8px;
	}
	.cp-ccbtns button {
		border: 0;
		background: transparent;
		color: #5f6368;
		font-size: 0.82rem;
		cursor: pointer;
		font-weight: 600;
	}
	.cp-ccbtns button:hover {
		color: #1f6feb;
	}
	.cp-toolbar {
		display: flex;
		gap: 2px;
		padding: 8px 0 6px;
	}
	.cp-toolbar button {
		display: grid;
		place-items: center;
		width: 32px;
		height: 32px;
		border: 0;
		border-radius: 8px;
		background: transparent;
		color: #5f6368;
		cursor: pointer;
		transition: background 0.12s;
	}
	.cp-toolbar button:hover {
		background: #eef1f5;
		color: #202124;
	}
	.cp-editor {
		min-height: 190px;
		max-height: 42vh;
		overflow-y: auto;
		padding: 6px 2px;
		font-size: 0.92rem;
		line-height: 1.55;
		outline: none;
	}
	.cp-editor:empty::before {
		content: 'Tulis pesan…';
		color: #9aa0a6;
	}
	.cp-atts {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
		padding: 8px 0;
	}
	.cp-att {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 5px 8px;
		background: #eef3fb;
		border-radius: 8px;
		font-size: 0.78rem;
		color: #3c4043;
	}
	.cp-att .sz {
		color: #80868b;
	}
	.cp-att button {
		display: grid;
		place-items: center;
		border: 0;
		background: transparent;
		cursor: pointer;
		color: #5f6368;
		padding: 0;
	}
	.cp-err {
		color: #c0392b;
		font-size: 0.83rem;
		margin: 6px 0 0;
	}
	.cp-foot {
		display: flex;
		align-items: center;
		gap: 6px;
		margin-top: 10px;
	}
	.cp-send {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		min-width: 116px;
		min-height: 44px;
		padding: 0 26px;
		border: 0;
		border-radius: 24px;
		background: #1f6feb;
		color: #fff;
		font-weight: 600;
		cursor: pointer;
		transition: background 0.13s;
	}
	.cp-send:hover {
		background: #1a5fd0;
	}
	.cp-send:disabled {
		opacity: 0.7;
		cursor: default;
	}
	.cp-icon {
		display: grid;
		place-items: center;
		width: 40px;
		height: 40px;
		border: 0;
		border-radius: 50%;
		background: transparent;
		color: #5f6368;
		cursor: pointer;
		transition: background 0.12s, color 0.12s;
	}
	.cp-icon:hover {
		background: #f0f2f5;
	}
	.cp-icon.danger {
		margin-left: auto;
	}
	.cp-icon.danger:hover {
		background: #fdecea;
		color: #c0392b;
	}

	/* ═══ MODAL / TOAST ═══ */
	.modal-bg {
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, 0.42);
		display: grid;
		place-items: center;
		z-index: 140;
	}
	.modal {
		width: min(92vw, 440px);
		background: #fff;
		border-radius: 16px;
		padding: 18px 20px 20px;
		box-shadow: 0 24px 60px rgba(0, 0, 0, 0.32);
	}
	.mh {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 14px;
	}
	.mh button {
		border: 0;
		background: transparent;
		cursor: pointer;
		color: #5f6368;
	}
	.ml {
		display: block;
		font-size: 0.82rem;
		font-weight: 600;
		color: #3c4043;
		margin-bottom: 6px;
	}
	.mt {
		width: 100%;
		min-height: 110px;
		border: 1px solid #d5dae2;
		border-radius: 10px;
		padding: 10px 12px;
		font: inherit;
		resize: vertical;
		outline: none;
	}
	.mt:focus {
		border-color: #1f6feb;
	}
	.mf {
		display: flex;
		justify-content: flex-end;
		margin-top: 14px;
	}
	.mail-toast {
		position: fixed;
		left: 24px;
		bottom: 24px;
		background: #202124;
		color: #fff;
		padding: 12px 20px;
		border-radius: 12px;
		font-size: 0.88rem;
		z-index: 160;
		box-shadow: 0 10px 30px rgba(0, 0, 0, 0.32);
		animation: toastin 0.25s ease;
	}
	@keyframes -global-toastin {
		from { opacity: 0; transform: translateY(10px); }
		to { opacity: 1; transform: translateY(0); }
	}
	.spin {
		display: inline-block;
		width: 18px;
		height: 18px;
		border-radius: 50%;
		border: 2.4px solid rgba(255, 255, 255, 0.4);
		border-top-color: #fff;
		animation: spinbtn 0.7s linear infinite;
	}
	.spin.dark {
		border-color: rgba(11, 87, 208, 0.3);
		border-top-color: #0b57d0;
	}
	@keyframes -global-spinbtn {
		to { transform: rotate(360deg); }
	}

	.only-mobile {
		display: none;
	}
	.sb-backdrop {
		display: none;
	}
	.fab {
		position: fixed;
		right: 18px;
		bottom: 18px;
		z-index: 55;
		width: 58px;
		height: 58px;
		border: 0;
		border-radius: 50%;
		background: #1f6feb;
		color: #fff;
		place-items: center;
		cursor: pointer;
		box-shadow: 0 8px 22px -4px rgba(31, 111, 235, 0.8);
	}

	/* ═══ RESPONSIVE ═══ */
	@media (max-width: 1080px) {
		.app {
			grid-template-columns: 74px 340px 1fr;
		}
		.app:not(.collapsed) .lbl,
		.app:not(.collapsed) .count,
		.app:not(.collapsed) .sb-brand {
			display: none;
		}
		.app:not(.collapsed) .fitem {
			justify-content: center;
			gap: 0;
			padding: 10px;
		}
	}
	@media (max-width: 820px) {
		.app,
		.app.collapsed {
			grid-template-columns: 1fr;
			height: calc(100dvh - 56px);
			position: relative;
		}
		/* sidebar jadi drawer overlay */
		.sb {
			position: fixed;
			top: 56px;
			left: 0;
			bottom: 0;
			width: 260px;
			background: #fff;
			z-index: 130;
			box-shadow: 0 8px 40px rgba(0, 0, 0, 0.25);
			transform: translateX(-100%);
			transition: transform 0.22s ease;
		}
		.app:not(.collapsed) .sb {
			transform: translateX(0);
		}
		.app:not(.collapsed) .lbl,
		.app:not(.collapsed) .count,
		.app:not(.collapsed) .sb-brand {
			display: inline;
		}
		.app:not(.collapsed) .fitem {
			justify-content: flex-start;
			gap: 16px;
			padding: 11px 16px;
		}
		.app:not(.collapsed) .me,
		.app:not(.collapsed) .gear {
			display: inline-flex;
		}
		.app:not(.collapsed) .compose {
			padding: 14px 22px;
			border-radius: 16px;
			align-self: flex-start;
		}
		.listpane {
			margin: 0;
			border-radius: 0;
			box-shadow: none;
		}
		.hide-on-mobile {
			display: none;
		}
		.readpane {
			margin: 0;
			border-radius: 0;
			box-shadow: none;
			display: none;
		}
		.readpane.show-on-mobile {
			display: flex;
			position: fixed;
			inset: 56px 0 0 0;
			z-index: 60;
		}
		.only-mobile {
			display: grid;
		}
		.only-desktop {
			display: none;
		}
		.sb-backdrop {
			display: block;
			position: fixed;
			inset: 56px 0 0 0;
			background: rgba(0, 0, 0, 0.42);
			border: 0;
			z-index: 125;
			animation: toastin 0.2s ease;
		}
		.rd-scroll {
			padding: 16px 18px;
		}
		.rd-subject {
			font-size: 1.25rem;
		}
		.composer,
		.composer.full,
		.composer.min {
			right: 0;
			left: 0;
			bottom: 0;
			width: 100%;
			height: 100dvh;
			border-radius: 0;
			transform: none;
		}
		.composer form {
			flex: 1;
			min-height: 0;
		}
		.composer .cp-editor {
			flex: 1;
			max-height: none;
		}
	}
</style>
