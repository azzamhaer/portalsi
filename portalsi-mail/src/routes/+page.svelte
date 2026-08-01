<script lang="ts">
	import { enhance } from '$app/forms';
	import { tick } from 'svelte';
	import {
		Inbox,
		Star,
		Send,
		FileText,
		Archive,
		ShieldAlert,
		Trash2,
		Search,
		RefreshCw,
		ArrowLeft,
		Reply,
		Forward,
		Paperclip,
		X,
		ChevronLeft,
		ChevronRight,
		Pencil,
		MailOpen,
		Mail as MailIcon,
		MailCheck,
		Bold,
		Italic,
		Underline,
		List as ListIcon,
		Link2,
		Download,
		Settings,
		Trash
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

	// ── state ──
	let composeOpen = $state(false);
	let composeMin = $state(false);
	let showCc = $state(false);
	let showBcc = $state(false);
	let sending = $state(false);
	let toastMsg = $state('');
	let settingsOpen = $state(false);
	let signature = $state('');

	let compose = $state({
		to: '',
		cc: '',
		bcc: '',
		subject: '',
		in_reply_to: '',
		references: ''
	});
	let files = $state<File[]>([]);
	let editorEl: HTMLDivElement | null = null;
	let fileInput: HTMLInputElement | null = null;

	let starOverride = $state<Record<number, boolean>>({});
	let seenOverride = $state<Record<number, boolean>>({});

	// reset override optimistik saat data folder/halaman berganti
	let dataKey = $derived(`${data.folderKey}|${data.page}|${data.q}|${data.message?.uid ?? ''}`);
	$effect(() => {
		dataKey;
		starOverride = {};
		seenOverride = {};
	});

	// muat tanda tangan
	$effect(() => {
		if (typeof localStorage !== 'undefined') signature = localStorage.getItem('ps_mail_sig') || '';
	});

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
			await fetch(`${location.pathname}?/${action}`, { method: 'POST', body: fd });
		} catch {
			/* diamkan */
		}
	}

	function toggleStar(m: any, e?: Event) {
		e?.preventDefault();
		e?.stopPropagation();
		const cur = starOf(m);
		starOverride = { ...starOverride, [m.uid]: !cur };
		postAction('star', { uid: m.uid, folder_path: data.folderPath, on: !cur ? 1 : 0 });
	}
	function toggleReadRow(m: any, e?: Event) {
		e?.preventDefault();
		e?.stopPropagation();
		const cur = seenOf(m);
		seenOverride = { ...seenOverride, [m.uid]: !cur };
		postAction('toggleRead', { uid: m.uid, folder_path: data.folderPath, seen: !cur ? 1 : 0 });
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
		if (input.files) files = [...files, ...Array.from(input.files)];
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
	const avatarColors = ['#1f6feb', '#0b8043', '#d93025', '#e37400', '#8430ce', '#0b7285', '#c2185b'];
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
</script>

<div class="mail">
	<!-- Sidebar -->
	<aside class="side">
		<button class="compose" onclick={newMail}><Pencil size={18} /> <span>Tulis</span></button>
		<nav class="folders">
			{#each data.folders as f (f.key)}
				{@const Icon = folderIcon[f.key] ?? Inbox}
				<a href="/?folder={f.key}" class="fitem" class:active={f.key === data.folderKey}>
					<Icon size={18} />
					<span class="flabel">{f.label}</span>
					{#if f.unseen}<b class="count">{f.unseen}</b>{/if}
				</a>
			{/each}
		</nav>
		<div class="side-foot">
			<span class="me" title={data.account?.email}>{data.account?.email}</span>
			<button class="gear" title="Pengaturan" aria-label="Pengaturan" onclick={() => (settingsOpen = true)}>
				<Settings size={16} />
			</button>
		</div>
	</aside>

	<!-- Main -->
	<section class="main">
		<div class="toolbar">
			{#if data.message}
				<a class="icon-btn" href="/?folder={data.folderKey}{qStr}" title="Kembali"><ArrowLeft size={18} /></a>
			{/if}
			<form class="search" method="GET">
				<Search size={16} />
				<input name="q" value={data.q} placeholder="Cari email…" />
				<input type="hidden" name="folder" value={data.folderKey} />
			</form>
			<a class="icon-btn" href="/?folder={data.folderKey}{qStr}" title="Muat ulang"><RefreshCw size={18} /></a>
		</div>

		{#if data.message}
			<!-- ═══ Baca pesan ═══ -->
			<article class="reader">
				<div class="rd-top">
					<h1>{data.message.subject}</h1>
					<div class="rd-actions">
						<button class="icon-btn" title="Balas" onclick={() => replyTo(data.message)}><Reply size={18} /></button>
						<button class="icon-btn" title="Teruskan" onclick={() => forwardMsg(data.message)}><Forward size={18} /></button>
						<button
							class="icon-btn"
							class:on={data.message.flagged}
							title="Bintang"
							onclick={() => toggleStar(data.message)}
						>
							<Star size={18} fill={data.message.flagged ? 'currentColor' : 'none'} />
						</button>
						<form method="POST" action="?/archive" use:enhance>
							<input type="hidden" name="uid" value={data.message.uid} />
							<input type="hidden" name="folder_path" value={data.folderPath} />
							<input type="hidden" name="folder_key" value={data.folderKey} />
							<button class="icon-btn" title="Arsipkan"><Archive size={18} /></button>
						</form>
						<form method="POST" action="?/trash" use:enhance>
							<input type="hidden" name="uid" value={data.message.uid} />
							<input type="hidden" name="folder_path" value={data.folderPath} />
							<input type="hidden" name="folder_key" value={data.folderKey} />
							<button class="icon-btn" title="Hapus"><Trash2 size={18} /></button>
						</form>
					</div>
				</div>

				<div class="rd-meta">
					<span class="avatar" style="background:{avColor(data.message.fromAddr)}">{initial(data.message.fromName)}</span>
					<div class="rd-from">
						<b>{data.message.fromName}</b>
						<span class="addr">&lt;{data.message.fromAddr}&gt;</span>
						<div class="rd-to">ke {data.message.to || 'saya'}</div>
					</div>
					<time>{fmtFull(data.message.date)}</time>
				</div>

				{#if data.message.attachments.filter((a) => !a.inline).length}
					<div class="attachments">
						{#each data.message.attachments.filter((a) => !a.inline) as a}
							<a class="att" href={attUrl(data.message.uid, a.index)} title="Unduh {a.filename}">
								{#if isImage(a.contentType)}
									<img class="att-thumb" src={attUrl(data.message.uid, a.index, true)} alt={a.filename} />
								{:else}
									<span class="att-ico"><Paperclip size={16} /></span>
								{/if}
								<span class="att-info">
									<span class="att-name">{a.filename}</span>
									<span class="att-size">{fmtSize(a.size)}</span>
								</span>
								<Download size={15} />
							</a>
						{/each}
					</div>
				{/if}

				<div class="rd-body">
					{#if data.message.html}
						<iframe title="Isi email" sandbox="" srcdoc={data.message.html} class="htmlframe"></iframe>
					{:else}
						<pre class="textbody">{data.message.text || '(pesan kosong)'}</pre>
					{/if}
				</div>

				<div class="rd-reply-bar">
					<button class="ghost-btn" onclick={() => replyTo(data.message)}><Reply size={16} /> Balas</button>
					<button class="ghost-btn" onclick={() => forwardMsg(data.message)}><Forward size={16} /> Teruskan</button>
				</div>

				{#if data.thread && data.thread.length}
					<div class="thread">
						<div class="thread-h">Percakapan ini ({data.thread.length + 1})</div>
						{#each data.thread as t (t.uid)}
							<a class="thread-item" href="/?folder={data.folderKey}&uid={t.uid}">
								<span class="avatar sm" style="background:{avColor(t.fromAddr)}">{initial(t.fromName)}</span>
								<span class="ti-who">{t.fromName}</span>
								<span class="ti-date">{fmtDate(t.date)}</span>
							</a>
						{/each}
					</div>
				{/if}
			</article>
		{:else}
			<!-- ═══ Daftar pesan ═══ -->
			<div class="list">
				{#if data.messages.length === 0}
					<div class="empty">
						<MailOpen size={44} />
						<p>{data.q ? 'Tidak ada hasil untuk pencarian ini.' : 'Folder ini kosong.'}</p>
					</div>
				{:else}
					{#each data.messages as m (m.uid)}
						<div class="row" class:unseen={!seenOf(m)}>
							<button
								class="star"
								class:on={starOf(m)}
								title={starOf(m) ? 'Hapus bintang' : 'Beri bintang'}
								onclick={(e) => toggleStar(m, e)}
							>
								<Star size={17} fill={starOf(m) ? 'currentColor' : 'none'} />
							</button>
							<a class="rmain" href="/?folder={data.folderKey}&uid={m.uid}">
								<span class="avatar sm" style="background:{avColor(m.fromAddr)}">{initial(m.fromName)}</span>
								<span class="who">{data.folderKey === 'sent' ? m.to : m.fromName}</span>
								<span class="subj">{m.subject}</span>
							</a>
							<div class="rmeta">
								<div class="rdate">
									{#if m.attachments}<Paperclip size={13} />{/if}
									<time>{fmtDate(m.date)}</time>
								</div>
								<div class="ractions">
									<form method="POST" action="?/archive" use:enhance>
										<input type="hidden" name="uid" value={m.uid} />
										<input type="hidden" name="folder_path" value={data.folderPath} />
										<input type="hidden" name="folder_key" value={data.folderKey} />
										<button class="ra" title="Arsipkan"><Archive size={16} /></button>
									</form>
									<form method="POST" action="?/trash" use:enhance>
										<input type="hidden" name="uid" value={m.uid} />
										<input type="hidden" name="folder_path" value={data.folderPath} />
										<input type="hidden" name="folder_key" value={data.folderKey} />
										<button class="ra" title="Hapus"><Trash2 size={16} /></button>
									</form>
									<button class="ra" title={seenOf(m) ? 'Tandai belum dibaca' : 'Tandai dibaca'} onclick={(e) => toggleReadRow(m, e)}>
										{#if seenOf(m)}<MailIcon size={16} />{:else}<MailCheck size={16} />{/if}
									</button>
								</div>
							</div>
						</div>
					{/each}
				{/if}

				{#if data.pages > 1}
					<div class="pager">
						<a class="icon-btn" class:disabled={data.page <= 1} href="/?folder={data.folderKey}&page={data.page - 1}{qStr}"><ChevronLeft size={18} /></a>
						<span>{data.page} / {data.pages}</span>
						<a class="icon-btn" class:disabled={data.page >= data.pages} href="/?folder={data.folderKey}&page={data.page + 1}{qStr}"><ChevronRight size={18} /></a>
					</div>
				{/if}
			</div>
		{/if}
	</section>
</div>

<!-- ═══ Composer ═══ -->
{#if composeOpen}
	<div class="composer" class:min={composeMin}>
		<header class="cp-head">
			<span><MailIcon size={15} /> Pesan baru</span>
			<div class="cp-hbtns">
				<button class="cp-hbtn" onclick={() => (composeMin = !composeMin)} aria-label="Kecilkan">
					{#if composeMin}<ChevronRight size={15} style="transform:rotate(-90deg)" />{:else}<ChevronRight size={15} style="transform:rotate(90deg)" />{/if}
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
					if (!compose.to.trim()) {
						cancel();
						return;
					}
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
				{#if showCc}
					<div class="cp-field"><label>Cc</label><input name="cc" bind:value={compose.cc} /></div>
				{/if}
				{#if showBcc}
					<div class="cp-field"><label>Bcc</label><input name="bcc" bind:value={compose.bcc} /></div>
				{/if}
				<div class="cp-field"><label>Subjek</label><input name="subject" bind:value={compose.subject} placeholder="Subjek" /></div>

				<div class="cp-toolbar">
					<button type="button" title="Tebal" onclick={() => exec('bold')}><Bold size={16} /></button>
					<button type="button" title="Miring" onclick={() => exec('italic')}><Italic size={16} /></button>
					<button type="button" title="Garis bawah" onclick={() => exec('underline')}><Underline size={16} /></button>
					<button type="button" title="Daftar" onclick={() => exec('insertUnorderedList')}><ListIcon size={16} /></button>
					<button type="button" title="Tautan" onclick={insertLink}><Link2 size={16} /></button>
					<button type="button" title="Lampirkan berkas" onclick={() => fileInput?.click()}><Paperclip size={16} /></button>
				</div>

				<div class="cp-editor" contenteditable="true" bind:this={editorEl} role="textbox" tabindex="0" aria-label="Isi pesan"></div>
				<input type="file" multiple bind:this={fileInput} onchange={addFiles} hidden />

				{#if files.length}
					<div class="cp-atts">
						{#each files as file, i}
							<span class="cp-att">
								<Paperclip size={13} /> {file.name} <span class="sz">{fmtSize(file.size)}</span>
								<button type="button" onclick={() => removeFile(i)} aria-label="Hapus lampiran"><X size={13} /></button>
							</span>
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
					<button type="button" class="cp-attach" title="Lampirkan" onclick={() => fileInput?.click()}><Paperclip size={17} /></button>
					<button type="button" class="cp-discard" title="Buang" onclick={() => (composeOpen = false)}><Trash size={17} /></button>
				</div>
			</form>
		{/if}
	</div>
{/if}

<!-- ═══ Pengaturan (tanda tangan) ═══ -->
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
		background: #f6f8fc;
	}
	.mail {
		display: grid;
		grid-template-columns: 248px 1fr;
		width: 100%;
		max-width: 1320px;
		height: calc(100vh - 63px);
		margin: 0 auto;
	}
	/* ── sidebar ── */
	.side {
		display: flex;
		flex-direction: column;
		gap: 4px;
		padding: 16px 10px;
		border-right: 1px solid #e6eaf0;
		min-height: 0;
	}
	.compose {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		align-self: flex-start;
		padding: 13px 24px;
		border: 0;
		border-radius: 16px;
		background: #1f6feb;
		color: #fff;
		font-weight: 600;
		font-size: 0.95rem;
		cursor: pointer;
		box-shadow: 0 3px 10px -3px rgba(31, 111, 235, 0.6);
		margin-bottom: 12px;
		transition: box-shadow 0.15s;
	}
	.compose:hover {
		box-shadow: 0 4px 14px -3px rgba(31, 111, 235, 0.7);
	}
	.folders {
		display: flex;
		flex-direction: column;
		gap: 1px;
		overflow-y: auto;
	}
	.fitem {
		display: flex;
		align-items: center;
		gap: 16px;
		padding: 9px 16px;
		border-radius: 0 999px 999px 0;
		color: #444a54;
		text-decoration: none;
		font-size: 0.9rem;
		font-weight: 500;
	}
	.fitem:hover {
		background: #eaecef;
	}
	.fitem.active {
		background: #d3e3fd;
		color: #0b57d0;
		font-weight: 700;
	}
	.fitem .count {
		margin-left: auto;
		font-size: 0.78rem;
	}
	.side-foot {
		margin-top: auto;
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 10px 12px 0;
		border-top: 1px solid #eef1f5;
	}
	.me {
		flex: 1;
		font-size: 0.78rem;
		color: #6a7280;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.gear {
		display: grid;
		place-items: center;
		width: 30px;
		height: 30px;
		border: 0;
		border-radius: 50%;
		background: transparent;
		color: #6a7280;
		cursor: pointer;
	}
	.gear:hover {
		background: #eef1f5;
	}
	/* ── main ── */
	.main {
		display: flex;
		flex-direction: column;
		min-height: 0;
		background: #fff;
		border-radius: 16px 0 0 0;
		margin: 8px 0 0 0;
		overflow: hidden;
		box-shadow: 0 0 0 1px #eef1f5;
	}
	.toolbar {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 10px 14px;
		border-bottom: 1px solid #eef1f5;
	}
	.search {
		flex: 1;
		display: flex;
		align-items: center;
		gap: 10px;
		max-width: 680px;
		padding: 10px 16px;
		background: #eef3fb;
		border-radius: 12px;
		color: #5f6368;
	}
	.search:focus-within {
		background: #fff;
		box-shadow: 0 1px 6px rgba(32, 33, 36, 0.18);
	}
	.search input {
		flex: 1;
		border: 0;
		background: transparent;
		font: inherit;
		outline: none;
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
	}
	.icon-btn:hover {
		background: #f0f2f5;
	}
	.icon-btn.on {
		color: #f4b400;
	}
	.icon-btn.disabled {
		opacity: 0.35;
		pointer-events: none;
	}
	/* ── list ── */
	.list {
		flex: 1;
		overflow-y: auto;
		min-height: 0;
	}
	.row {
		display: grid;
		grid-template-columns: 36px 1fr auto;
		align-items: center;
		gap: 6px;
		padding: 0 14px 0 6px;
		height: 46px;
		border-bottom: 1px solid #f3f4f6;
		font-size: 0.9rem;
	}
	.row:hover {
		box-shadow: inset 1px 0 0 #dadce0, inset -1px 0 0 #dadce0, 0 1px 2px rgba(60, 64, 67, 0.16);
		z-index: 1;
		border-bottom-color: transparent;
	}
	.row.unseen {
		background: #fff;
	}
	.row.unseen .who,
	.row.unseen .subj {
		font-weight: 700;
		color: #202124;
	}
	.row:not(.unseen) {
		background: #f8fafd;
	}
	.row:not(.unseen) .who,
	.row:not(.unseen) .subj {
		color: #5f6368;
	}
	.star {
		display: grid;
		place-items: center;
		width: 30px;
		height: 30px;
		border: 0;
		background: transparent;
		color: #c3c7cf;
		cursor: pointer;
	}
	.star:hover {
		color: #9aa0a6;
	}
	.star.on {
		color: #f4b400;
	}
	.rmain {
		display: grid;
		grid-template-columns: 34px 168px 1fr;
		align-items: center;
		gap: 12px;
		min-width: 0;
		height: 100%;
		text-decoration: none;
		color: inherit;
	}
	.avatar {
		display: grid;
		place-items: center;
		width: 38px;
		height: 38px;
		border-radius: 50%;
		background: #1f6feb;
		color: #fff;
		font-weight: 700;
		flex: none;
	}
	.avatar.sm {
		width: 34px;
		height: 34px;
		font-size: 0.9rem;
	}
	.who {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.subj {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.rmeta {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		min-width: 92px;
	}
	.rdate {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		color: #5f6368;
		font-size: 0.78rem;
	}
	.ractions {
		display: none;
		align-items: center;
		gap: 2px;
	}
	.row:hover .rdate {
		display: none;
	}
	.row:hover .ractions {
		display: inline-flex;
	}
	.ra {
		display: grid;
		place-items: center;
		width: 32px;
		height: 32px;
		border: 0;
		border-radius: 50%;
		background: transparent;
		color: #5f6368;
		cursor: pointer;
	}
	.ra:hover {
		background: #e8eaed;
		color: #202124;
	}
	.ractions form {
		margin: 0;
		display: inline-flex;
	}
	.empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 12px;
		padding: 90px 20px;
		color: #9aa0a6;
	}
	.pager {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 12px;
		padding: 16px;
		color: #5f6368;
		font-size: 0.85rem;
	}
	/* ── reader ── */
	.reader {
		flex: 1;
		display: flex;
		flex-direction: column;
		min-height: 0;
		padding: 20px 28px 28px;
		overflow-y: auto;
	}
	.rd-top {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 16px;
	}
	.rd-top h1 {
		font-size: 1.4rem;
		font-weight: 500;
		margin: 0;
		line-height: 1.35;
	}
	.rd-actions {
		display: flex;
		gap: 2px;
		flex: none;
	}
	.rd-actions form {
		margin: 0;
	}
	.rd-meta {
		display: flex;
		align-items: center;
		gap: 12px;
		margin: 18px 0 14px;
	}
	.rd-from {
		display: flex;
		flex-direction: column;
		font-size: 0.88rem;
		min-width: 0;
	}
	.rd-from b {
		font-size: 0.95rem;
	}
	.rd-from .addr {
		color: #5f6368;
	}
	.rd-to {
		color: #5f6368;
		font-size: 0.82rem;
	}
	.rd-meta time {
		margin-left: auto;
		color: #5f6368;
		font-size: 0.82rem;
		white-space: nowrap;
	}
	.attachments {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		margin-bottom: 16px;
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
		flex: 1;
		min-height: 320px;
	}
	.htmlframe {
		width: 100%;
		height: 100%;
		min-height: 420px;
		border: 0;
		background: #fff;
	}
	.textbody {
		white-space: pre-wrap;
		word-break: break-word;
		font-family: inherit;
		font-size: 0.94rem;
		line-height: 1.6;
		margin: 0;
		color: #202124;
	}
	.rd-reply-bar {
		display: flex;
		gap: 10px;
		margin-top: 18px;
	}
	.ghost-btn {
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
	}
	.ghost-btn:hover {
		background: #f5f7fa;
	}
	.thread {
		margin-top: 22px;
		border-top: 1px solid #eef1f5;
		padding-top: 12px;
	}
	.thread-h {
		font-size: 0.8rem;
		color: #80868b;
		font-weight: 600;
		margin-bottom: 8px;
	}
	.thread-item {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 8px 10px;
		border-radius: 10px;
		text-decoration: none;
		color: #3c4043;
		font-size: 0.86rem;
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
	/* ── composer ── */
	.composer {
		position: fixed;
		right: 24px;
		bottom: 0;
		width: min(94vw, 560px);
		background: #fff;
		border-radius: 12px 12px 0 0;
		box-shadow: 0 -2px 28px rgba(0, 0, 0, 0.3);
		z-index: 60;
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
	.composer.min {
		width: 300px;
	}
	.cp-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 11px 12px 11px 16px;
		background: #f2f6fc;
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
		width: 26px;
		height: 26px;
		border: 0;
		border-radius: 6px;
		background: transparent;
		cursor: pointer;
		color: #5f6368;
	}
	.cp-hbtn:hover {
		background: #e3e9f2;
	}
	.composer form {
		display: flex;
		flex-direction: column;
		padding: 4px 16px 12px;
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
		padding: 10px 0;
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
		padding: 6px 0;
		margin-top: 4px;
	}
	.cp-toolbar button {
		display: grid;
		place-items: center;
		width: 30px;
		height: 30px;
		border: 0;
		border-radius: 6px;
		background: transparent;
		color: #5f6368;
		cursor: pointer;
	}
	.cp-toolbar button:hover {
		background: #eef1f5;
		color: #202124;
	}
	.cp-editor {
		min-height: 180px;
		max-height: 40vh;
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
		gap: 8px;
		margin-top: 10px;
	}
	.cp-send {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		min-width: 110px;
		min-height: 42px;
		padding: 0 24px;
		border: 0;
		border-radius: 22px;
		background: #1f6feb;
		color: #fff;
		font-weight: 600;
		cursor: pointer;
	}
	.cp-send:hover {
		background: #1a5fd0;
	}
	.cp-send:disabled {
		opacity: 0.7;
		cursor: default;
	}
	.cp-attach,
	.cp-discard {
		display: grid;
		place-items: center;
		width: 38px;
		height: 38px;
		border: 0;
		border-radius: 50%;
		background: transparent;
		color: #5f6368;
		cursor: pointer;
	}
	.cp-attach:hover,
	.cp-discard:hover {
		background: #f0f2f5;
	}
	.cp-discard {
		margin-left: auto;
	}
	/* ── modal ── */
	.modal-bg {
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, 0.4);
		display: grid;
		place-items: center;
		z-index: 80;
	}
	.modal {
		width: min(92vw, 440px);
		background: #fff;
		border-radius: 14px;
		padding: 18px 20px 20px;
		box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
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
		padding: 12px 18px;
		border-radius: 10px;
		font-size: 0.88rem;
		z-index: 90;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
	}

	@media (max-width: 820px) {
		.mail {
			grid-template-columns: 1fr;
			height: calc(100vh - 60px);
		}
		.side {
			flex-direction: row;
			align-items: center;
			overflow-x: auto;
			border-right: 0;
			border-bottom: 1px solid #e6eaf0;
			padding: 8px;
			gap: 4px;
		}
		.compose {
			margin: 0;
			padding: 10px 16px;
		}
		.compose span {
			display: none;
		}
		.folders {
			flex-direction: row;
		}
		.fitem {
			border-radius: 999px;
			padding: 8px 12px;
			white-space: nowrap;
		}
		.fitem .flabel {
			display: none;
		}
		.fitem.active .flabel {
			display: inline;
		}
		.side-foot {
			display: none;
		}
		.main {
			border-radius: 0;
			margin: 0;
			box-shadow: none;
		}
		.rmain {
			grid-template-columns: 34px 1fr;
		}
		.who {
			display: none;
		}
		.composer {
			right: 0;
			left: 0;
			width: 100%;
			border-radius: 12px 12px 0 0;
		}
	}
</style>
