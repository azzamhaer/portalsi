<script lang="ts">
	import { enhance } from '$app/forms';
	import {
		Inbox,
		Send,
		FileText,
		ShieldAlert,
		Trash2,
		Search,
		RefreshCw,
		ArrowLeft,
		Reply,
		Paperclip,
		X,
		ChevronLeft,
		ChevronRight,
		Pencil,
		MailOpen,
		Mail as MailIcon
	} from '@lucide/svelte';

	let { data, form } = $props();

	const folderIcon: Record<string, any> = {
		inbox: Inbox,
		sent: Send,
		drafts: FileText,
		junk: ShieldAlert,
		trash: Trash2
	};

	let composeOpen = $state(false);
	let showCc = $state(false);
	let sending = $state(false);
	let toast = $state('');
	let compose = $state({ to: '', cc: '', subject: '', body: '', in_reply_to: '', references: '' });

	function openCompose() {
		compose = { to: '', cc: '', subject: '', body: '', in_reply_to: '', references: '' };
		showCc = false;
		composeOpen = true;
	}
	function replyTo(m: any) {
		compose = {
			to: m.fromAddr,
			cc: '',
			subject: m.subject?.startsWith('Re:') ? m.subject : `Re: ${m.subject}`,
			body: `\n\n──────────\nPada ${fmtFull(m.date)}, ${m.fromName} <${m.fromAddr}> menulis:\n${(m.text || '').replace(/^/gm, '> ')}`,
			in_reply_to: m.messageId || '',
			references: (m.references ? `${m.references} ` : '') + (m.messageId || '')
		};
		showCc = false;
		composeOpen = true;
	}

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
	function initial(name: string): string {
		return (name || '?').trim().charAt(0).toUpperCase();
	}
</script>

<div class="mail">
	<!-- Sidebar -->
	<aside class="side">
		<button class="compose" onclick={openCompose}><Pencil size={18} /> Tulis</button>
		<nav class="folders">
			{#each data.folders as f (f.key)}
				{@const Icon = folderIcon[f.key] ?? Inbox}
				<a href="/?folder={f.key}" class="fitem" class:active={f.key === data.folderKey}>
					<Icon size={18} />
					<span>{f.label}</span>
					{#if f.unseen}<b class="count">{f.unseen}</b>{/if}
				</a>
			{/each}
		</nav>
		<div class="side-foot">
			<span class="me">{data.account?.email}</span>
		</div>
	</aside>

	<!-- Main -->
	<section class="main">
		<div class="toolbar">
			{#if data.message}
				<a class="icon-btn" href="/?folder={data.folderKey}" title="Kembali"><ArrowLeft size={18} /></a>
			{/if}
			<form class="search" method="GET">
				<Search size={16} />
				<input name="q" value={data.q} placeholder="Cari email…" />
				<input type="hidden" name="folder" value={data.folderKey} />
			</form>
			<a class="icon-btn" href="/?folder={data.folderKey}{data.q ? `&q=${encodeURIComponent(data.q)}` : ''}" title="Muat ulang"><RefreshCw size={18} /></a>
		</div>

		{#if data.message}
			<!-- Baca pesan -->
			<article class="reader">
				<header class="rd-head">
					<h1>{data.message.subject}</h1>
					<div class="rd-actions">
						<button class="icon-btn" title="Balas" onclick={() => replyTo(data.message)}><Reply size={18} /></button>
						<form method="POST" action="?/trash" use:enhance>
							<input type="hidden" name="uid" value={data.message.uid} />
							<input type="hidden" name="folder_path" value={data.folderPath} />
							<input type="hidden" name="folder_key" value={data.folderKey} />
							<button class="icon-btn" title="Hapus"><Trash2 size={18} /></button>
						</form>
					</div>
				</header>
				<div class="rd-meta">
					<span class="avatar">{initial(data.message.fromName)}</span>
					<div class="rd-from">
						<b>{data.message.fromName}</b>
						<span>&lt;{data.message.fromAddr}&gt;</span>
						<div class="rd-to">ke {data.message.to || 'saya'}</div>
					</div>
					<time>{fmtFull(data.message.date)}</time>
				</div>

				{#if data.message.attachments.length}
					<div class="attachments">
						{#each data.message.attachments as a}
							<span class="att"><Paperclip size={14} /> {a.filename}</span>
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
			</article>
		{:else}
			<!-- Daftar pesan -->
			<div class="list">
				{#if data.messages.length === 0}
					<div class="empty">
						<MailOpen size={40} />
						<p>{data.q ? 'Tidak ada hasil untuk pencarian ini.' : 'Folder ini kosong.'}</p>
					</div>
				{:else}
					{#each data.messages as m (m.uid)}
						<a class="row" class:unseen={!m.seen} href="/?folder={data.folderKey}&uid={m.uid}">
							<span class="avatar sm">{initial(m.fromName)}</span>
							<span class="who">{data.folderKey === 'sent' ? m.to : m.fromName}</span>
							<span class="subj">
								<b>{m.subject}</b>
							</span>
							<span class="meta">
								{#if m.attachments}<Paperclip size={13} />{/if}
								<time>{fmtDate(m.date)}</time>
							</span>
						</a>
					{/each}
				{/if}

				{#if data.pages > 1}
					<div class="pager">
						<a class="icon-btn" class:disabled={data.page <= 1} href="/?folder={data.folderKey}&page={data.page - 1}{data.q ? `&q=${encodeURIComponent(data.q)}` : ''}"><ChevronLeft size={18} /></a>
						<span>{data.page} / {data.pages}</span>
						<a class="icon-btn" class:disabled={data.page >= data.pages} href="/?folder={data.folderKey}&page={data.page + 1}{data.q ? `&q=${encodeURIComponent(data.q)}` : ''}"><ChevronRight size={18} /></a>
					</div>
				{/if}
			</div>
		{/if}
	</section>
</div>

<!-- Compose -->
{#if composeOpen}
	<div class="composer">
		<header class="cp-head">
			<span><MailIcon size={15} /> Pesan baru</span>
			<button class="cp-x" onclick={() => (composeOpen = false)} aria-label="Tutup"><X size={16} /></button>
		</header>
		<form
			method="POST"
			action="?/send"
			use:enhance={() => {
				sending = true;
				return async ({ result, update }) => {
					sending = false;
					if (result.type === 'success' && (result.data as any)?.sent) {
						composeOpen = false;
						toast = 'Email terkirim';
						setTimeout(() => (toast = ''), 3000);
					}
					await update({ reset: false });
				};
			}}
		>
			<div class="cp-field">
				<label>Ke</label>
				<input name="to" bind:value={compose.to} placeholder="penerima@contoh.com" required />
				{#if !showCc}<button type="button" class="cc-toggle" onclick={() => (showCc = true)}>Cc</button>{/if}
			</div>
			{#if showCc}
				<div class="cp-field"><label>Cc</label><input name="cc" bind:value={compose.cc} /></div>
			{/if}
			<div class="cp-field"><label>Subjek</label><input name="subject" bind:value={compose.subject} placeholder="Subjek" /></div>
			<textarea name="body" bind:value={compose.body} placeholder="Tulis pesan…"></textarea>
			<input type="hidden" name="in_reply_to" value={compose.in_reply_to} />
			<input type="hidden" name="references" value={compose.references} />
			{#if (form as any)?.sendError}<p class="cp-err">{(form as any).sendError}</p>{/if}
			<div class="cp-foot">
				<button class="cp-send" disabled={sending}>
					{#if sending}<span class="spin"></span>{:else}<Send size={16} /> Kirim{/if}
				</button>
			</div>
		</form>
	</div>
{/if}

{#if toast}<div class="mail-toast">{toast}</div>{/if}

<style>
	:global(body) {
		background: #f6f8fc;
	}
	.mail {
		display: grid;
		grid-template-columns: 240px 1fr;
		gap: 0;
		width: 100%;
		max-width: 1280px;
		height: calc(100vh - 63px);
		margin: 0 auto;
	}
	/* sidebar */
	.side {
		display: flex;
		flex-direction: column;
		gap: 6px;
		padding: 16px 12px;
		border-right: 1px solid #e6e6e6;
		min-height: 0;
	}
	.compose {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		align-self: flex-start;
		padding: 12px 22px;
		border: 0;
		border-radius: 16px;
		background: #1f6feb;
		color: #fff;
		font-weight: 600;
		font-size: 0.95rem;
		cursor: pointer;
		box-shadow: 0 2px 8px -2px rgba(31, 111, 235, 0.5);
		margin-bottom: 10px;
	}
	.compose:hover {
		background: #1a5fd0;
	}
	.folders {
		display: flex;
		flex-direction: column;
		gap: 2px;
		overflow-y: auto;
	}
	.fitem {
		display: flex;
		align-items: center;
		gap: 14px;
		padding: 9px 16px;
		border-radius: 0 999px 999px 0;
		color: #444;
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
		padding: 10px 16px 0;
		border-top: 1px solid #eee;
	}
	.me {
		font-size: 0.78rem;
		color: #6a6155;
		word-break: break-all;
	}
	/* main */
	.main {
		display: flex;
		flex-direction: column;
		min-height: 0;
		background: #fff;
		border-radius: 16px 0 0 0;
		margin: 8px 0 0 0;
		overflow: hidden;
	}
	.toolbar {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 10px 14px;
		border-bottom: 1px solid #eee;
	}
	.search {
		flex: 1;
		display: flex;
		align-items: center;
		gap: 8px;
		max-width: 640px;
		padding: 9px 14px;
		background: #eaf1fb;
		border-radius: 12px;
		color: #5f6368;
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
	.icon-btn.disabled {
		opacity: 0.35;
		pointer-events: none;
	}
	/* list */
	.list {
		flex: 1;
		overflow-y: auto;
		min-height: 0;
	}
	.row {
		display: grid;
		grid-template-columns: 40px 180px 1fr auto;
		align-items: center;
		gap: 12px;
		padding: 10px 16px;
		border-bottom: 1px solid #f1f1f1;
		text-decoration: none;
		color: #202124;
		font-size: 0.9rem;
	}
	.row:hover {
		box-shadow: inset 0 0 0 9999px rgba(0, 0, 0, 0.015);
		z-index: 1;
	}
	.row.unseen {
		background: #fff;
		font-weight: 700;
	}
	.row:not(.unseen) {
		background: #f7f9fc;
		color: #5f6368;
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
	.subj b {
		font-weight: inherit;
	}
	.meta {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		color: #5f6368;
		font-size: 0.78rem;
		font-weight: 400;
	}
	.empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 12px;
		padding: 80px 20px;
		color: #9aa0a6;
	}
	.pager {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 12px;
		padding: 14px;
		color: #5f6368;
		font-size: 0.85rem;
	}
	/* reader */
	.reader {
		flex: 1;
		display: flex;
		flex-direction: column;
		min-height: 0;
		padding: 18px 26px;
		overflow-y: auto;
	}
	.rd-head {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 16px;
	}
	.rd-head h1 {
		font-size: 1.35rem;
		font-weight: 500;
		margin: 0;
	}
	.rd-actions {
		display: flex;
		gap: 4px;
	}
	.rd-actions form {
		margin: 0;
	}
	.rd-meta {
		display: flex;
		align-items: center;
		gap: 12px;
		margin: 16px 0;
	}
	.rd-from {
		display: flex;
		flex-direction: column;
		font-size: 0.88rem;
	}
	.rd-from b {
		font-size: 0.95rem;
	}
	.rd-from span {
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
		gap: 8px;
		margin-bottom: 14px;
	}
	.att {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 6px 12px;
		border: 1px solid #e0e0e0;
		border-radius: 8px;
		font-size: 0.82rem;
		color: #444;
	}
	.rd-body {
		flex: 1;
		min-height: 0;
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
	}
	/* composer */
	.composer {
		position: fixed;
		right: 24px;
		bottom: 0;
		width: min(92vw, 520px);
		background: #fff;
		border-radius: 12px 12px 0 0;
		box-shadow: 0 -2px 24px rgba(0, 0, 0, 0.28);
		z-index: 60;
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
	.cp-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 11px 16px;
		background: #f2f6fc;
		font-weight: 600;
		font-size: 0.88rem;
	}
	.cp-head span {
		display: inline-flex;
		align-items: center;
		gap: 8px;
	}
	.cp-x {
		border: 0;
		background: transparent;
		cursor: pointer;
		color: #5f6368;
		display: grid;
		place-items: center;
	}
	.composer form {
		display: flex;
		flex-direction: column;
		padding: 6px 16px 14px;
	}
	.cp-field {
		display: flex;
		align-items: center;
		gap: 10px;
		border-bottom: 1px solid #eee;
	}
	.cp-field label {
		width: 46px;
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
	.cc-toggle {
		border: 0;
		background: transparent;
		color: #1f6feb;
		font-size: 0.82rem;
		cursor: pointer;
	}
	.composer textarea {
		min-height: 200px;
		max-height: 46vh;
		border: 0;
		padding: 12px 0;
		font: inherit;
		resize: vertical;
		outline: none;
	}
	.cp-err {
		color: #c0392b;
		font-size: 0.83rem;
		margin: 0 0 8px;
	}
	.cp-foot {
		display: flex;
	}
	.cp-send {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		min-width: 110px;
		min-height: 42px;
		padding: 0 22px;
		border: 0;
		border-radius: 22px;
		background: #1f6feb;
		color: #fff;
		font-weight: 600;
		cursor: pointer;
	}
	.cp-send:disabled {
		opacity: 0.7;
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
		z-index: 70;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
	}

	@media (max-width: 780px) {
		.mail {
			grid-template-columns: 1fr;
			height: calc(100vh - 60px);
		}
		.side {
			flex-direction: row;
			align-items: center;
			overflow-x: auto;
			border-right: 0;
			border-bottom: 1px solid #e6e6e6;
			padding: 8px;
		}
		.compose {
			margin: 0;
			padding: 10px 16px;
		}
		.folders {
			flex-direction: row;
		}
		.fitem {
			border-radius: 999px;
			padding: 8px 12px;
			white-space: nowrap;
		}
		.fitem span {
			display: none;
		}
		.fitem.active span {
			display: inline;
		}
		.side-foot {
			display: none;
		}
		.main {
			border-radius: 0;
			margin: 0;
		}
		.row {
			grid-template-columns: 36px 1fr auto;
		}
		.who {
			display: none;
		}
	}
</style>
