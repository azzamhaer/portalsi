<script lang="ts">
	import { enhance } from '$app/forms';
	import { navigating } from '$app/stores';
	import { tick, onMount } from 'svelte';
	import {
		Menu,
		Inbox,
		Star,
		Send,
		FileText,
		Archive,
		ArchiveRestore,
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
		ArrowLeft,
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
		BadgeCheck,
		Pin,
		Camera,
		Moon,
		Sun,
		KeyRound,
		ShieldCheck,
		UserRound,
		MoreVertical
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
	let hidden = $state<Set<number>>(new Set());
	let selectMode = $state(false);
	let refreshing = $state(false);
	let readerMenuOpen = $state(false);
	let readerSeen = $state(true);
	let trashDismissed = $state(false);
	// (aksi massal kini optimistik — tak perlu status sibuk terpisah)
	let bgCount = $state(0);
	let bgLabel = $state('');
	let pinned = $state<number[]>([]);
	let displayName = $state('');
	let nameFollow = $state(true);
	let density = $state<'comfort' | 'compact'>('comfort');
	let darkMode = $state(false);
	let settingsTab = $state<'profil' | 'keamanan' | 'tampilan'>('profil');
	let pwSending = $state(false);

	// live search
	let sq = $state('');
	let searchResults = $state<any[]>([]);
	let searchOpen = $state(false);
	let searchLoading = $state(false);
	let searchTimer: ReturnType<typeof setTimeout> | undefined;

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

	let compose = $state<{
		to: string;
		cc: string;
		bcc: string;
		subject: string;
		in_reply_to: string;
		references: string;
		draftUid: number | null;
	}>({ to: '', cc: '', bcc: '', subject: '', in_reply_to: '', references: '', draftUid: null });
	let fromAddr = $state<string>((data.addresses && data.addresses[0]) || '');
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
		hidden = new Set();
		selectMode = false;
		readerFull = false;
		readerMenuOpen = false;
		trashDismissed = false;
		live = null;
		prevTop = data.messages[0]?.uid ?? 0;
	});

	$effect(() => {
		if (typeof localStorage !== 'undefined') {
			signature = localStorage.getItem('ps_mail_sig') || '';
			displayName = localStorage.getItem('ps_mail_name') || '';
			nameFollow = localStorage.getItem('ps_mail_namefollow') !== '0';
			density = (localStorage.getItem('ps_mail_density') as any) || 'comfort';
			darkMode = localStorage.getItem('ps_mail_dark') === '1';
			try {
				pinned = JSON.parse(localStorage.getItem('ps_mail_pins') || '[]');
			} catch {
				pinned = [];
			}
			const sb = localStorage.getItem('ps_mail_sidebar');
			if (typeof window !== 'undefined' && window.innerWidth <= 820) sidebarOpen = false;
			else if (sb !== null) sidebarOpen = sb === '1';
		}
	});

	function persistPins() {
		if (typeof localStorage !== 'undefined') localStorage.setItem('ps_mail_pins', JSON.stringify(pinned));
	}
	function isPinned(uid: number) {
		return pinned.includes(uid);
	}
	function togglePin(uid: number) {
		if (pinned.includes(uid)) pinned = pinned.filter((u) => u !== uid);
		else {
			if (pinned.length >= 3) {
				toast('Maksimal 3 email disematkan.');
				return;
			}
			pinned = [...pinned, uid];
		}
		persistPins();
	}
	function toggleSidebar() {
		sidebarOpen = !sidebarOpen;
		if (typeof localStorage !== 'undefined') localStorage.setItem('ps_mail_sidebar', sidebarOpen ? '1' : '0');
	}

	// terapkan dark mode ke <html> tiap berubah
	$effect(() => {
		if (typeof document !== 'undefined') document.documentElement.classList.toggle('psdark', darkMode);
	});
	function toggleDark() {
		darkMode = !darkMode;
		if (typeof localStorage !== 'undefined') localStorage.setItem('ps_mail_dark', darkMode ? '1' : '0');
	}

	// ── refresh di LATAR (tak mengganggu email yang sedang dibuka) ──
	let live = $state<any>(null);
	let prevTop = 0;
	let folders = $derived(live?.folders ?? data.folders);
	let msgs = $derived(live?.messages ?? data.messages);
	let total = $derived(live?.total ?? data.total);
	let pages = $derived(live?.pages ?? data.pages);
	let curPage = $derived(live?.page ?? data.page);
	let curFolder = $derived(folders.find((f: any) => f.key === data.folderKey));
	let curLabel = $derived(curFolder?.label ?? 'Kotak Masuk');
	let pageTitle = $derived(
		(curFolder?.unseen ? `(${curFolder.unseen}) ` : '') + `${curLabel} — Portal SI Mail`
	);

	async function poll(manual = false) {
		if (manual) {
			if (refreshing) return;
			refreshing = true;
		}
		try {
			const r = await fetch(
				`/poll?folder=${encodeURIComponent(data.folderKey)}&page=${data.page}&q=${encodeURIComponent(data.q)}`
			);
			if (r.ok) {
				const d = await r.json();
				const top = d.messages?.[0]?.uid ?? 0;
				if (data.folderKey === 'inbox' && data.page === 1 && !data.q && prevTop && top > prevTop) {
					toast('Email baru masuk!');
				}
				prevTop = top;
				live = d;
				// realtime: bila semua aksi latar selesai, lepaskan override lokal → tampil status server
				if (bgCount === 0) {
					starOverride = {};
					seenOverride = {};
					hidden = new Set([...hidden].filter((u) => d.messages.some((m: any) => m.uid === u)));
				}
			}
		} catch {
			/* ignore */
		}
		if (manual) refreshing = false;
	}
	function refresh() {
		poll(true);
	}
	onMount(() => {
		prevTop = data.messages[0]?.uid ?? 0;
		const id = setInterval(() => {
			if (typeof document !== 'undefined' && document.visibilityState !== 'visible') return;
			if (composeOpen) return;
			poll();
		}, 8000);
		return () => clearInterval(id);
	});

	// ── live search ──
	function onSearchInput() {
		clearTimeout(searchTimer);
		const term = sq.trim();
		if (term.length < 2) {
			searchResults = [];
			searchOpen = term.length > 0;
			searchLoading = false;
			return;
		}
		searchOpen = true;
		searchLoading = true;
		searchTimer = setTimeout(async () => {
			try {
				const r = await fetch(`/search?folder=${encodeURIComponent(data.folderPath)}&q=${encodeURIComponent(term)}`);
				const d = await r.json();
				if (sq.trim() === term) searchResults = d.results ?? [];
			} catch {
				searchResults = [];
			} finally {
				searchLoading = false;
			}
		}, 260);
	}
	function openSearchResult(m: any) {
		searchOpen = false;
		sq = '';
		openMessage(m);
	}
	function clearSearch() {
		sq = '';
		searchResults = [];
		searchOpen = false;
	}

	function saveSettings() {
		if (typeof localStorage !== 'undefined') {
			localStorage.setItem('ps_mail_sig', signature);
			localStorage.setItem('ps_mail_name', displayName);
			localStorage.setItem('ps_mail_namefollow', nameFollow ? '1' : '0');
			localStorage.setItem('ps_mail_dark', darkMode ? '1' : '0');
		}
		settingsOpen = false;
		toast('Pengaturan disimpan');
	}

	// nama pengirim efektif (ikut akun atau kustom)
	let effName = $derived(nameFollow ? (data.user?.full_name || '').trim() : displayName.trim());

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

	// ── daftar: filter + pin + kelompok tanggal ──
	let visible = $derived(msgs.filter((m: any) => !hidden.has(m.uid)));
	let filtered = $derived(filter === 'unread' ? visible.filter((m: any) => !seenOf(m)) : visible);
	let pinnedItems = $derived(data.folderKey === 'inbox' ? filtered.filter((m: any) => isPinned(m.uid)) : []);
	let groups = $derived.by(() => {
		const order = ['Hari ini', 'Kemarin', '7 hari terakhir', 'Bulan ini', 'Lebih lama'];
		const pinnedSet = new Set(pinnedItems.map((m: any) => m.uid));
		const map = new Map<string, any[]>();
		for (const m of filtered) {
			if (pinnedSet.has(m.uid)) continue;
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
		if (data.folderKey === 'drafts') {
			openDraft(m);
			return;
		}
		if (selectedUid === m.uid && selected) return;
		selectedUid = m.uid;
		readerSeen = true;
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

	let selIndex = $derived(msgs.findIndex((m: any) => m.uid === selectedUid));
	function goRel(delta: number) {
		const i = selIndex + delta;
		if (i >= 0 && i < msgs.length) openMessage(msgs[i]);
	}

	function toggleStar(m: any, e?: Event) {
		e?.preventDefault();
		e?.stopPropagation();
		const cur = starOf(m);
		starOverride = { ...starOverride, [m.uid]: !cur };
		postAction('star', { uid: m.uid, folder_path: data.folderPath, on: !cur ? 1 : 0 });
	}
	// toggle baca/belum-dibaca untuk email yang dibuka
	function toggleReaderRead() {
		if (!selected) return;
		const target = !readerSeen; // status seen tujuan
		readerSeen = target;
		seenOverride = { ...seenOverride, [selected.uid]: target };
		postAction('toggleRead', { uid: selected.uid, folder_path: data.folderPath, seen: target ? 1 : 0 });
		toast(target ? 'Ditandai sudah dibaca' : 'Ditandai belum dibaca');
	}

	// ── aksi baris (arsip/hapus) via form enhance ──

	// ── seleksi massal ──
	function toggleCheck(uid: number, e?: Event) {
		e?.stopPropagation();
		selectMode = true;
		const s = new Set(checked);
		s.has(uid) ? s.delete(uid) : s.add(uid);
		checked = s;
	}
	function checkAll() {
		checked = allChecked && filtered.length ? new Set() : new Set(filtered.map((m: any) => m.uid));
	}
	// jalankan sekumpulan aksi di latar belakang + progress
	async function runBg(label: string, promises: Promise<any>[]) {
		bgLabel = label;
		bgCount = promises.length;
		for (const p of promises) p.finally(() => (bgCount = Math.max(0, bgCount - 1)));
		await Promise.allSettled(promises);
		bgLabel = '';
		bgCount = 0;
		toast(`${label} selesai`);
	}
	// hilangkan baris seketika, kerjakan di latar
	function optimisticRemove(uids: number[], serverAction: string, label: string) {
		const h = new Set(hidden);
		uids.forEach((u) => h.add(u));
		hidden = h;
		if (selectedUid && uids.includes(selectedUid)) closeReader();
		if (pinned.some((p) => uids.includes(p))) {
			pinned = pinned.filter((p) => !uids.includes(p));
			persistPins();
		}
		runBg(
			label,
			uids.map((uid) => postAction(serverAction, { uid, folder_path: data.folderPath, folder_key: data.folderKey }))
		);
	}
	// aksi kontekstual sesuai folder
	let inArchive = $derived(data.folderKey === 'archive');
	let inTrash = $derived(data.folderKey === 'trash');
	function doArchive(uids: number[]) {
		if (inArchive) optimisticRemove(uids, 'unarchive', 'Memindahkan ke Kotak Masuk');
		else optimisticRemove(uids, 'archive', 'Mengarsipkan');
	}
	function doTrash(uids: number[], confirmPurge = true) {
		if (inTrash) {
			if (confirmPurge && typeof window !== 'undefined' && !window.confirm('Hapus permanen email ini? Tidak bisa dikembalikan.')) return;
			optimisticRemove(uids, 'trash', 'Menghapus permanen');
		} else {
			optimisticRemove(uids, 'trash', 'Memindahkan ke sampah');
		}
	}
	function bulk(action: 'archive' | 'trash' | 'read') {
		if (!checked.size) return;
		const uids = [...checked];
		if (action === 'trash' && inTrash && typeof window !== 'undefined' && !window.confirm(`Hapus permanen ${uids.length} email? Tidak bisa dikembalikan.`)) return;
		checked = new Set();
		if (action === 'read') {
			const so = { ...seenOverride };
			uids.forEach((u) => (so[u] = true));
			seenOverride = so;
			runBg('Menandai dibaca', uids.map((uid) => postAction('toggleRead', { uid, folder_path: data.folderPath, seen: 1 })));
		} else if (action === 'archive') {
			doArchive(uids);
		} else {
			// bulk hapus di sampah = permanen (tanpa konfirmasi per item)
			doTrash(uids, false);
		}
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
	function pickFrom(recipients: string): string {
		const list = data.addresses ?? [];
		const low = (recipients || '').toLowerCase();
		return list.find((a: string) => low.includes(a.toLowerCase())) || list[0] || '';
	}
	function newMail() {
		compose = { to: '', cc: '', bcc: '', subject: '', in_reply_to: '', references: '', draftUid: null };
		fromAddr = data.addresses?.[0] || fromAddr;
		loadContacts();
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
			references: (m.references ? `${m.references} ` : '') + (m.messageId || ''),
			draftUid: null
		};
		fromAddr = pickFrom(m.to);
		loadContacts();
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
			references: '',
			draftUid: null
		};
		loadContacts();
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

	// ── avatar sesama @portalsi.com ──
	let avatars = $state<Record<string, string | null>>({});
	async function ensureAvatars(addrs: (string | undefined | null)[]) {
		const need = [...new Set(addrs.map((a) => (a || '').toLowerCase()).filter((a) => a && a.includes('@')))].filter(
			(a) => !(a in avatars)
		);
		if (!need.length) return;
		const pending = { ...avatars };
		need.forEach((a) => (pending[a] = null));
		avatars = pending;
		try {
			const r = await fetch('/avatars', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ emails: need })
			});
			if (r.ok) {
				const d = await r.json();
				const merged = { ...avatars };
				for (const [k, v] of Object.entries(d.avatars || {})) merged[k.toLowerCase()] = v as string;
				avatars = merged;
			}
		} catch {
			/* ignore */
		}
	}
	function avatarUrl(addr?: string | null): string | null {
		return addr ? avatars[addr.toLowerCase()] || null : null;
	}
	$effect(() => {
		const addrs: (string | undefined)[] = [];
		for (const m of msgs) addrs.push(m?.fromAddr);
		if (selected?.fromAddr) addrs.push(selected.fromAddr);
		for (const t of thread) addrs.push(t?.fromAddr);
		if (addrs.length) ensureAvatars(addrs);
	});

	// ── saran penerima (kontak dari interaksi terakhir) ──
	let contacts = $state<{ name: string; address: string }[]>([]);
	let contactsLoaded = $state(false);
	let toSuggest = $state<{ name: string; address: string }[]>([]);
	let toSuggestOpen = $state(false);
	async function loadContacts() {
		if (contactsLoaded) return;
		contactsLoaded = true;
		try {
			const r = await fetch('/contacts');
			if (r.ok) contacts = (await r.json()).contacts ?? [];
		} catch {
			/* ignore */
		}
	}
	function lastToken(s: string): string {
		const parts = s.split(',');
		return parts[parts.length - 1].trim().toLowerCase();
	}
	function onToInput() {
		const term = lastToken(compose.to);
		if (term.length < 1) {
			toSuggestOpen = false;
			return;
		}
		const chosen = compose.to
			.split(',')
			.slice(0, -1)
			.map((s) => s.trim().toLowerCase());
		toSuggest = contacts
			.filter(
				(c) =>
					!chosen.includes(c.address.toLowerCase()) &&
					(c.address.toLowerCase().includes(term) || c.name.toLowerCase().includes(term))
			)
			.slice(0, 6);
		toSuggestOpen = toSuggest.length > 0;
	}
	function pickContact(c: { name: string; address: string }) {
		const parts = compose.to.split(',');
		parts[parts.length - 1] = ' ' + c.address;
		compose.to = parts.join(',').replace(/^[\s,]+/, '') + ', ';
		toSuggestOpen = false;
	}

	// ── draf ──
	let savingDraft = $state(false);
	async function openDraft(m: any) {
		try {
			const r = await fetch(`/message?folder=${encodeURIComponent(data.folderPath)}&uid=${m.uid}`);
			const d = r.ok ? await r.json() : null;
			const msg = d?.message;
			compose = {
				to: (msg?.to || m.to || '').trim(),
				cc: '',
				bcc: '',
				subject: msg?.subject || m.subject || '',
				in_reply_to: '',
				references: '',
				draftUid: m.uid
			};
			fromAddr = data.addresses?.[0] || fromAddr;
			loadContacts();
			const html = msg?.html || (msg?.text ? `<div>${escapeHtml(msg.text).replace(/\n/g, '<br>')}</div>` : '');
			await openComposer(html);
		} catch {
			toast('Gagal membuka draf.');
		}
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

<svelte:head><title>{pageTitle}</title></svelte:head>

{#snippet avat(addr: string | undefined | null, name: string, cls: string)}
	{#if avatarUrl(addr)}
		<img class="avatar {cls}" src={avatarUrl(addr)} alt="" />
	{:else}
		<span class="avatar {cls}" style="background:{avColor(addr || '')}">{initial(name)}</span>
	{/if}
{/snippet}

{#if $navigating}
	<div class="topbar-progress"></div>
{/if}

<div class="app" class:collapsed={!sidebarOpen}>
	<!-- ═══════════ SIDEBAR ═══════════ -->
	<aside class="sb">
		<div class="sb-head">
			<button class="hamb" onclick={toggleSidebar} aria-label="Menu"><Menu size={20} /></button>
			<span class="sb-brand">Portal <b>SI</b> Mail</span>
		</div>

		<button class="compose" onclick={newMail}>
			<PenSquare size={18} />
			<span class="lbl">Tulis</span>
		</button>

		<nav class="folders">
			{#each folders as f (f.key)}
				{@const Icon = folderIcon[f.key] ?? Inbox}
				<a href="/?folder={f.key}" class="fitem" class:active={f.key === data.folderKey} title={f.label} onclick={() => { if (typeof window !== 'undefined' && window.innerWidth <= 820) sidebarOpen = false; }}>
					<span class="fico"><Icon size={19} /></span>
					<span class="lbl">{f.label}</span>
					{#if f.unseen}<b class="count">{f.unseen}</b>{/if}
				</a>
			{/each}
		</nav>

		<button class="sb-foot" onclick={() => { settingsTab = 'profil'; settingsOpen = true; }} title="Pengaturan akun">
			{#if data.user?.profile_picture_url}
				<img class="pp sm" src={data.user.profile_picture_url} alt="" />
			{:else}
				<span class="avatar sm" style="background:{avColor(data.account?.email || '')}">{initial(data.user?.full_name || data.account?.email || 'U')}</span>
			{/if}
			<span class="me lbl">
				<b class="me-name">{data.user?.full_name || data.user?.username || 'Akun'}</b>
				<span class="me-mail" title={data.account?.email}>{data.account?.email}</span>
			</span>
			<span class="gear lbl"><Settings size={17} /></span>
		</button>
	</aside>

	{#if sidebarOpen}
		<button class="sb-backdrop" onclick={toggleSidebar} aria-label="Tutup menu"></button>
	{/if}

	<!-- ═══════════ DAFTAR ═══════════ -->
	<section class="listpane" class:hide-on-mobile={selected || loadingMsg}>
		<div class="lp-head">
			<button class="hamb only-mobile" onclick={toggleSidebar} aria-label="Menu"><Menu size={20} /></button>
			<button class="chk head" class:on={selectMode} onclick={() => { selectMode = !selectMode; if (!selectMode) checked = new Set(); }} title="Pilih email" aria-label="Mode pilih">
				{#if selectMode}<span class="tick">✓</span>{/if}
			</button>
			<div class="segs">
				<button class:on={filter === 'all'} onclick={() => (filter = 'all')}>Semua</button>
				<button class:on={filter === 'unread'} onclick={() => (filter = 'unread')}>Belum dibaca</button>
			</div>
			<button class="icon-btn" class:spinning={refreshing} onclick={refresh} title="Muat ulang" aria-label="Muat ulang"><RefreshCw size={17} /></button>
		</div>

		<div class="lp-search-wrap">
			<form class="lp-search" method="GET" autocomplete="off">
				<Search size={16} />
				<input
					name="q"
					bind:value={sq}
					placeholder={`Cari ${curLabel.toLowerCase()}…`}
					oninput={onSearchInput}
					onfocus={() => { if (sq.trim().length) searchOpen = true; }}
				/>
				<input type="hidden" name="folder" value={data.folderKey} />
				{#if sq}<button type="button" class="s-clear" onclick={clearSearch} aria-label="Bersihkan"><X size={15} /></button>{/if}
			</form>
			{#if searchOpen && sq.trim().length >= 1}
				<button class="s-backdrop" onclick={() => (searchOpen = false)} aria-label="Tutup"></button>
				<div class="s-results">
					{#if searchLoading}
						<div class="s-loading"><span class="spin dark"></span> Mencari…</div>
					{:else if searchResults.length === 0}
						<div class="s-empty">{sq.trim().length < 2 ? 'Ketik minimal 2 huruf…' : `Tidak ada hasil untuk "${sq.trim()}".`}</div>
					{:else}
						{#each searchResults as m (m.uid)}
							<button class="s-item" onclick={() => openSearchResult(m)}>
								{@render avat(m.fromAddr, m.fromName, 'sm')}
								<span class="s-body">
									<span class="s-line1"><b class="s-who">{m.fromName}</b><span class="s-date">{fmtDate(m.date)}</span></span>
									<span class="s-subj">{m.subject}{#if m.attachments}<Paperclip size={12} class="clip" />{/if}</span>
								</span>
							</button>
						{/each}
						<a class="s-all" href="/?folder={data.folderKey}&q={encodeURIComponent(sq.trim())}">Lihat semua hasil →</a>
					{/if}
				</div>
			{/if}
		</div>

		{#if selectMode || checked.size}
			<div class="bulkbar">
				<button class="bb-close" onclick={() => { selectMode = false; checked = new Set(); }} aria-label="Selesai"><X size={16} /></button>
				<span>{checked.size} dipilih</span>
				<button class="bb-all" onclick={checkAll}>{allChecked && filtered.length ? 'Batalkan semua' : 'Pilih semua'}</button>
				<div class="bulk-actions">
					<button onclick={() => bulk('read')} disabled={!checked.size} title="Tandai dibaca"><MailCheck size={16} /></button>
					<button onclick={() => bulk('archive')} disabled={!checked.size} title="Arsipkan"><Archive size={16} /></button>
					<button onclick={() => bulk('trash')} disabled={!checked.size} title="Hapus"><Trash2 size={16} /></button>
				</div>
			</div>
		{/if}
		{#if bgCount > 0}
			<div class="bgbar"><span class="spin dark"></span> {bgLabel}… {bgCount} tersisa</div>
		{/if}
		{#if data.folderKey === 'trash' && msgs.length && !trashDismissed}
			<div class="trash-notice">
				<div class="tn-text"><Trash2 size={15} /> Pesan di Sampah dihapus permanen setelah 30 hari.</div>
				<div class="tn-actions">
					<button class="tn-close" onclick={() => (trashDismissed = true)} aria-label="Tutup"><X size={15} /></button>
					<form
						method="POST"
						action="?/emptyTrash"
						use:enhance={({ cancel }) => {
							if (typeof window !== 'undefined' && !window.confirm('Kosongkan Sampah sekarang? Semua pesan akan dihapus permanen dan tak bisa dikembalikan.')) {
								cancel();
								return;
							}
							return async ({ update }) => {
								live = null;
								await update();
								toast('Sampah dikosongkan');
							};
						}}
					>
						<button class="tn-btn">Bersihkan sampah sekarang</button>
					</form>
				</div>
			</div>
		{/if}

		{#snippet rowEl(m)}
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
				{@render avat(m.fromAddr, m.fromName, '')}
				<div class="rbody">
					<div class="rline1">
						<span class="who">{data.folderKey === 'sent' || data.folderKey === 'drafts' ? m.to || '(tanpa penerima)' : m.fromName}</span>
						<span class="date">{fmtDate(m.date)}</span>
					</div>
					<div class="rline2">
						{#if isPinned(m.uid)}<Pin size={12} class="clip pinmark" fill="currentColor" />{/if}
						{#if !seenOf(m)}<span class="dot"></span>{/if}
						<span class="subj">{m.subject}</span>
						{#if m.attachments}<Paperclip size={13} class="clip" />{/if}
					</div>
				</div>
			</div>
		{/snippet}

		<div class="lp-scroll" class:compact={density === 'compact'} class:selmode={selectMode}>
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
					<p>{data.q ? 'Tidak ada hasil.' : filter === 'unread' ? 'Semua telah terbaca.' : 'Tidak ada sesuatu disini.'}</p>
				</div>
			{:else}
				{#if pinnedItems.length}
					<div class="grp-h pin"><Pin size={12} fill="currentColor" /> Disematkan</div>
					{#each pinnedItems as m (m.uid)}{@render rowEl(m)}{/each}
				{/if}
				{#each groups as g (g.label)}
					<div class="grp-h">{g.label}</div>
					{#each g.items as m (m.uid)}{@render rowEl(m)}{/each}
				{/each}
			{/if}

			{#if pages > 1 && !$navigating}
				<div class="pager">
					<a class="icon-btn" class:disabled={curPage <= 1} href="/?folder={data.folderKey}&page={curPage - 1}{qStr}"><ChevronLeft size={18} /></a>
					<span>{curPage} / {pages}</span>
					<a class="icon-btn" class:disabled={curPage >= pages} href="/?folder={data.folderKey}&page={curPage + 1}{qStr}"><ChevronRight size={18} /></a>
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
					<button class="back-btn" onclick={closeReader}><ArrowLeft size={17} /> Kembali</button>
					{#if selIndex >= 0}<span class="counter">{selIndex + 1} dari {total}</span>{/if}
					<button class="icon-btn" disabled={selIndex <= 0} onclick={() => goRel(-1)} aria-label="Sebelumnya"><ChevronLeft size={18} /></button>
					<button class="icon-btn" disabled={selIndex < 0 || selIndex >= msgs.length - 1} onclick={() => goRel(1)} aria-label="Berikutnya"><ChevronRight size={18} /></button>
				</div>
				<div class="rd-tools">
					<button class="icon-btn only-desktop" class:on={selected.flagged} onclick={() => toggleStar(selected)} aria-label="Bintang"><Star size={18} fill={selected.flagged ? 'currentColor' : 'none'} /></button>
					{#if data.folderKey === 'inbox'}
						<button class="icon-btn only-desktop" class:pinon={isPinned(selected.uid)} onclick={() => togglePin(selected.uid)} aria-label="Sematkan"><Pin size={18} fill={isPinned(selected.uid) ? 'currentColor' : 'none'} /></button>
					{/if}
					<button class="icon-btn only-desktop" onclick={() => doArchive([selected.uid])} title={inArchive ? 'Batal arsip' : 'Arsipkan'} aria-label={inArchive ? 'Batal arsip' : 'Arsipkan'}>
						{#if inArchive}<ArchiveRestore size={18} />{:else}<Archive size={18} />{/if}
					</button>
					<button class="icon-btn only-desktop" onclick={() => doTrash([selected.uid])} title={inTrash ? 'Hapus permanen' : 'Pindahkan ke sampah'} aria-label={inTrash ? 'Hapus permanen' : 'Hapus'}><Trash2 size={18} /></button>
					<button class="icon-btn only-desktop" onclick={() => window.print()} aria-label="Cetak"><Printer size={18} /></button>
					<button class="icon-btn only-desktop" onclick={() => (readerFull = !readerFull)} aria-label="Layar penuh">
						{#if readerFull}<Minimize2 size={17} />{:else}<Maximize2 size={17} />{/if}
					</button>
					<div class="kebab only-mobile">
						<button class="icon-btn" onclick={() => (readerMenuOpen = !readerMenuOpen)} aria-label="Aksi lain"><MoreVertical size={20} /></button>
						{#if readerMenuOpen}
							<button class="menu-backdrop" onclick={() => (readerMenuOpen = false)} aria-label="Tutup"></button>
							<div class="rmenu">
								<button onclick={() => { toggleStar(selected); readerMenuOpen = false; }}><Star size={17} fill={selected.flagged ? 'currentColor' : 'none'} /> {selected.flagged ? 'Hapus bintang' : 'Beri bintang'}</button>
								<button onclick={() => { doArchive([selected.uid]); readerMenuOpen = false; }}>
									{#if inArchive}<ArchiveRestore size={17} /> Pindahkan ke Kotak Masuk{:else}<Archive size={17} /> Arsipkan{/if}
								</button>
								<button onclick={() => { toggleReaderRead(); readerMenuOpen = false; }}>
									{#if readerSeen}<MailIcon size={17} /> Tandai belum dibaca{:else}<MailOpen size={17} /> Tandai sudah dibaca{/if}
								</button>
								<button class="danger" onclick={() => { doTrash([selected.uid]); readerMenuOpen = false; }}>
									<Trash2 size={17} /> {inTrash ? 'Hapus permanen' : 'Pindahkan ke sampah'}
								</button>
							</div>
						{/if}
					</div>
				</div>
			</div>

			<div class="rd-scroll">
				<h1 class="rd-subject">{selected.subject}</h1>
				<div class="rd-sender">
					{@render avat(selected.fromAddr, selected.fromName, 'lg')}
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
								{@render avat(t.fromAddr, t.fromName, 'sm')}
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
				<button class="pill only-desktop" onclick={toggleReaderRead}>
					{#if readerSeen}<MailIcon size={16} /> Tandai belum dibaca{:else}<MailOpen size={16} /> Tandai sudah dibaca{/if}
				</button>
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
				<button class="cp-hbtn only-desktop" onclick={() => { composeMin = !composeMin; if (composeMin) composeFull = false; }} aria-label="Kecilkan">
					{#if composeMin}<ChevronRight size={15} style="transform:rotate(-90deg)" />{:else}<ChevronRight size={15} style="transform:rotate(90deg)" />{/if}
				</button>
				<button class="cp-hbtn only-desktop" onclick={() => { composeFull = !composeFull; composeMin = false; }} aria-label="Layar penuh">
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
				use:enhance={({ formData, cancel, submitter }) => {
						const isDraft = (submitter as HTMLButtonElement)?.formAction?.includes('saveDraft') ?? false;
					if (!isDraft && !compose.to.trim()) { cancel(); return; }
					formData.set('html', editorEl?.innerHTML ?? '');
					formData.set('body', editorEl?.innerText ?? '');
					if (!isDraft) for (const file of files) formData.append('files', file);
					if (isDraft) savingDraft = true; else sending = true;
					return async ({ result, update }) => {
						sending = false; savingDraft = false;
						if (result.type === 'success' && (result.data as any)?.sent) {
							composeOpen = false;
							files = [];
							compose.draftUid = null;
								poll();
								toast('Email terkirim');
							} else if (result.type === 'success' && (result.data as any)?.draftSaved) {
								composeOpen = false;
								files = [];
								compose.draftUid = null;
								poll();
								toast('Draf disimpan');
						} else {
							await update({ reset: false });
						}
					};
				}}
			>
				{#if data.addresses && data.addresses.length > 1}
					<div class="cp-field">
						<label>Dari</label>
						<select class="cp-from" bind:value={fromAddr}>
							{#each data.addresses as a}<option value={a}>{a}</option>{/each}
						</select>
					</div>
				{/if}
				<div class="cp-field">
					<label>Ke</label>
					<input name="to" bind:value={compose.to} placeholder="penerima@contoh.com" required autocomplete="off" oninput={onToInput} onfocus={onToInput} />
						{#if toSuggestOpen}
							<button type="button" class="to-backdrop" onclick={() => (toSuggestOpen = false)} aria-label="Tutup"></button>
							<div class="to-suggest">
								{#each toSuggest as c (c.address)}
									<button type="button" class="to-item" onclick={() => pickContact(c)}>
										{@render avat(c.address, c.name || c.address, 'sm')}
										<span class="to-info"><b>{c.name || c.address}</b><span>{c.address}</span></span>
									</button>
								{/each}
							</div>
						{/if}
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
				<input type="hidden" name="from_name" value={effName} />
				<input type="hidden" name="from_addr" value={fromAddr} />
				<input type="hidden" name="draft_uid" value={compose.draftUid ?? ''} />
				{#if (form as any)?.sendError}<p class="cp-err">{(form as any).sendError}</p>{/if}

				<div class="cp-foot">
					<button class="cp-send" disabled={sending}>
						{#if sending}<span class="spin"></span>{:else}<Send size={16} /> Kirim{/if}
					</button>
					<button type="submit" formaction="?/saveDraft" formnovalidate class="cp-icon" title="Simpan draf" disabled={savingDraft || sending}>
						{#if savingDraft}<span class="spin dark"></span>{:else}<FileText size={17} />{/if}
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
		<div class="modal wide" onclick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
			<header class="mh"><b>Pengaturan</b><button onclick={() => (settingsOpen = false)} aria-label="Tutup"><X size={16} /></button></header>
			<div class="tabs">
				<button class:on={settingsTab === 'profil'} onclick={() => (settingsTab = 'profil')}>Profil</button>
				<button class:on={settingsTab === 'keamanan'} onclick={() => (settingsTab = 'keamanan')}>Keamanan</button>
				<button class:on={settingsTab === 'tampilan'} onclick={() => (settingsTab = 'tampilan')}>Tampilan</button>
			</div>

			{#if settingsTab === 'profil'}
				<div class="pp-row">
					{#if data.user?.profile_picture_url}
						<img class="pp lg" src={data.user.profile_picture_url} alt="" />
					{:else}
						<span class="avatar xl" style="background:{avColor(data.account?.email || '')}">{initial(data.user?.full_name || 'U')}</span>
					{/if}
					<div class="pp-info">
						<b>{data.user?.full_name || data.user?.username}</b>
						<span class="muted">Foto profil dari Portal SI</span>
						<a class="pp-link" href="https://app.portalsi.com/profile/edit" target="_blank" rel="noopener"><Camera size={14} /> Ubah foto di app.portalsi.com</a>
					</div>
				</div>
				<label class="ml">Nama tampilan (di email keluar)</label>
				<button class="dark-toggle" onclick={() => (nameFollow = !nameFollow)}>
					<span class="dt-left"><UserRound size={16} /> Ikuti nama akun {#if data.user?.full_name} ({data.user.full_name}){/if}</span>
					<span class="switch-ui" class:on={nameFollow}><span class="knob"></span></span>
				</button>
				{#if !nameFollow}
					<input class="mi" bind:value={displayName} placeholder="Tuliskan nama pengirim kustom..." />
				{/if}
				<label class="ml">Alamat email{data.addresses && data.addresses.length > 1 ? ' (dua alamat, satu kotak masuk)' : ''}</label>
				{#each data.addresses ?? [data.account?.email] as a}
					<input class="mi" value={a} readonly />
				{/each}
				<p class="note">Alamat email tidak bisa diubah setelah dibuat.</p>
				<div class="mf"><button class="cp-send" onclick={saveSettings}>Simpan</button></div>
			{:else if settingsTab === 'keamanan'}
				<div class="sec-ico"><KeyRound size={22} /></div>
				<h3 class="sec-h">Ganti kata sandi</h3>
				<div class="warn-box">
					<ShieldCheck size={18} />
					<span>Kata sandi ini dipakai untuk <b>seluruh layanan Portal SI</b> (App, Meet, Marketplace, Mail). Menggantinya akan mengubah kata sandi di <b>semua</b> layanan tersebut.</span>
				</div>
				{#if (form as any)?.pwSent}
					<div class="ok-box">{(form as any).pwMessage || 'Tautan konfirmasi telah dikirim ke email. Cek kotak masuk (dan folder spam) untuk melanjutkan.'}</div>
				{:else}
					<p class="note">Tautan konfirmasi dikirim ke email akunmu: <b>{data.user?.email || '—'}</b>. Klik tautan itu untuk menyetel kata sandi baru. <span class="muted">(Maks 3 permintaan per hari.)</span></p>
					<form
						method="POST"
						action="?/resetPassword"
						use:enhance={() => {
							pwSending = true;
							return async ({ update }) => {
								pwSending = false;
								await update();
							};
						}}
					>
						{#if (form as any)?.pwError}<p class="err-inline">{(form as any).pwError}</p>{/if}
						<div class="mf">
							<button class="cp-send" disabled={pwSending || !data.user?.email}>
								{#if pwSending}<span class="spin"></span>{:else}Kirim tautan ke email{/if}
							</button>
						</div>
					</form>
				{/if}
			{:else}
				<label class="ml">Mode tampilan</label>
				<button class="dark-toggle" onclick={toggleDark}>
					<span class="dt-left">
						{#if darkMode}<Moon size={18} />{:else}<Sun size={18} />{/if}
						{darkMode ? 'Mode gelap' : 'Mode terang'}
					</span>
					<span class="switch-ui" class:on={darkMode}><span class="knob"></span></span>
				</button>
				<p class="note">Mode gelap berlaku di seluruh aplikasi Mail.</p>
				<div class="mf"><button class="cp-send" onclick={saveSettings}>Simpan</button></div>
			{/if}
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
		flex: 1;
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
		min-width: 20px;
		text-align: center;
		font-size: 0.72rem;
		font-weight: 700;
		background: #e5484d;
		color: #fff;
		padding: 2px 7px;
		border-radius: 999px;
		box-shadow: 0 1px 3px rgba(229, 72, 77, 0.4);
	}
	.fitem.active .count {
		background: #c62b30;
	}
	.sb-foot {
		margin-top: auto;
		display: flex;
		align-items: center;
		gap: 10px;
		width: 100%;
		padding: 10px 8px;
		margin-top: 6px;
		border: 0;
		border-top: 1px solid #e2e6ec;
		border-radius: 12px;
		background: transparent;
		cursor: pointer;
		text-align: left;
		transition: background 0.13s;
	}
	.sb-foot:hover {
		background: #eef1f5;
	}
	.pp {
		object-fit: cover;
		border-radius: 50%;
		flex: none;
	}
	.pp.sm {
		width: 34px;
		height: 34px;
	}
	.me {
		flex: 1;
		display: flex;
		flex-direction: column;
		min-width: 0;
		line-height: 1.25;
	}
	.me-name {
		font-size: 0.82rem;
		color: #202124;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.me-mail {
		font-size: 0.72rem;
		color: #80868b;
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
		border-radius: 16px 16px 0 0;
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
	.bulk-actions button:disabled {
		opacity: 0.4;
		pointer-events: none;
	}
	.bb-close {
		display: grid;
		place-items: center;
		width: 28px;
		height: 28px;
		border: 0;
		border-radius: 8px;
		background: transparent;
		color: #0b57d0;
		cursor: pointer;
	}
	.bb-close:hover {
		background: #d7e6ff;
	}
	.bb-all {
		border: 1px solid #b9d3ff;
		background: #fff;
		color: #0b57d0;
		font-size: 0.78rem;
		font-weight: 700;
		padding: 5px 12px;
		border-radius: 999px;
		cursor: pointer;
	}
	.bb-all:hover {
		background: #eef4ff;
	}
	.bulkbar span {
		flex: 1;
	}
	/* refresh berputar */
	.icon-btn.spinning :global(svg) {
		animation: spinbtn 0.8s linear infinite;
	}
	/* mode pilih: checkbox selalu tampak */
	.lp-scroll.selmode .row .chk {
		opacity: 1;
	}
	/* tombol kembali (reader) yang jelas */
	.back-btn {
		display: none;
		align-items: center;
		gap: 6px;
		height: 34px;
		padding: 0 14px 0 10px;
		margin-right: 4px;
		border: 1px solid #dbe0e7;
		border-radius: 999px;
		background: #f5f7fa;
		color: #3c4043;
		font-size: 0.84rem;
		font-weight: 600;
		white-space: nowrap;
		cursor: pointer;
		transition: background 0.13s;
	}
	.back-btn:hover {
		background: #e9edf3;
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
	img.avatar {
		object-fit: cover;
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
	.lbl-short {
		display: none;
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
		left: 50%;
		right: auto;
		transform: translateX(-50%);
		bottom: 3vh;
		width: min(95vw, 1120px);
		height: 94vh;
		border-radius: 16px;
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
		background: transparent;
		color: inherit;
	}
	.cp-from {
		flex: 1;
		border: 0;
		padding: 10px 0;
		font: inherit;
		outline: none;
		background: transparent;
		color: inherit;
		cursor: pointer;
	}
	.cp-field {
		position: relative;
	}
	.to-backdrop {
		position: fixed;
		inset: 0;
		background: transparent;
		border: 0;
		z-index: 30;
		cursor: default;
	}
	.to-suggest {
		position: absolute;
		top: 100%;
		left: 40px;
		right: 0;
		z-index: 40;
		margin-top: 4px;
		background: #fff;
		border: 1px solid #e6e9ef;
		border-radius: 12px;
		box-shadow: 0 12px 32px rgba(0, 0, 0, 0.16);
		padding: 4px;
		max-height: 240px;
		overflow-y: auto;
	}
	.to-item {
		display: flex;
		align-items: center;
		gap: 10px;
		width: 100%;
		padding: 7px 9px;
		border: 0;
		border-radius: 9px;
		background: transparent;
		cursor: pointer;
		text-align: left;
	}
	.to-item:hover {
		background: #f2f5f9;
	}
	.to-info {
		display: flex;
		flex-direction: column;
		min-width: 0;
		line-height: 1.25;
	}
	.to-info b {
		font-size: 0.85rem;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.to-info span {
		font-size: 0.76rem;
		color: #80868b;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	:global(html.psdark) .to-suggest {
		background: #1b2029;
		border-color: #2c333d;
	}
	:global(html.psdark) .to-item:hover {
		background: #222831;
	}
	:global(html.psdark) .to-info b {
		color: #e6e9ef;
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
	/* progress latar */
	.bgbar {
		display: flex;
		align-items: center;
		gap: 8px;
		margin: 0 12px 6px;
		padding: 8px 12px;
		background: #fff8e6;
		border: 1px solid #f5e2a8;
		border-radius: 10px;
		font-size: 0.82rem;
		color: #8a6d0f;
		font-weight: 600;
	}
	/* pemberitahuan sampah */
	.trash-notice {
		display: flex;
		flex-direction: column;
		gap: 10px;
		margin: 0 12px 8px;
		padding: 12px;
		background: #fff4f4;
		border: 1px solid #f6cbcb;
		border-radius: 12px;
	}
	.tn-text {
		display: flex;
		align-items: flex-start;
		gap: 8px;
		font-size: 0.8rem;
		color: #a23b3b;
		line-height: 1.4;
	}
	.tn-actions {
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.tn-actions form {
		margin: 0 0 0 auto;
	}
	.tn-close {
		display: grid;
		place-items: center;
		width: 32px;
		height: 32px;
		border: 1px solid #e2b8b8;
		border-radius: 8px;
		background: #fff;
		color: #a23b3b;
		cursor: pointer;
	}
	.tn-close:hover {
		background: #fdecec;
	}
	.tn-btn {
		border: 1px solid #e0a0a0;
		background: #fff;
		color: #c0392b;
		font-size: 0.78rem;
		font-weight: 700;
		padding: 7px 12px;
		border-radius: 999px;
		cursor: pointer;
		white-space: nowrap;
	}
	.tn-btn:hover {
		background: #fdecec;
	}
	/* kebab menu reader (mobile) */
	.kebab {
		position: relative;
	}
	.rmenu {
		position: absolute;
		top: 44px;
		right: 0;
		width: 210px;
		background: #fff;
		border: 1px solid #e6e9ef;
		border-radius: 14px;
		box-shadow: 0 18px 44px rgba(0, 0, 0, 0.18);
		padding: 6px;
		z-index: 60;
		animation: menuin 0.14s ease;
	}
	.rmenu button {
		display: flex;
		align-items: center;
		gap: 12px;
		width: 100%;
		padding: 11px 12px;
		border: 0;
		border-radius: 10px;
		background: transparent;
		color: #3c4043;
		font: inherit;
		font-size: 0.9rem;
		cursor: pointer;
		text-align: left;
	}
	.rmenu button:hover {
		background: #f2f5f9;
	}
	.rmenu button.danger {
		color: #c0392b;
	}
	.grp-h.pin {
		display: flex;
		align-items: center;
		gap: 6px;
		color: #1f6feb;
	}
	:global(.pinmark) {
		color: #1f6feb !important;
	}
	.icon-btn.pinon {
		color: #1f6feb;
	}
	.lp-scroll.compact .row {
		height: 50px;
	}
	.lp-scroll.compact .avatar {
		width: 34px;
		height: 34px;
	}
	/* settings tabs */
	.modal.wide {
		width: min(94vw, 520px);
		max-height: 88vh;
		overflow-y: auto;
	}
	.tabs {
		display: flex;
		gap: 2px;
		margin-bottom: 16px;
		border-bottom: 1px solid #eef1f5;
	}
	.tabs button {
		border: 0;
		background: transparent;
		padding: 8px 14px;
		font: inherit;
		font-size: 0.86rem;
		font-weight: 600;
		color: #5f6368;
		cursor: pointer;
		border-bottom: 2px solid transparent;
		margin-bottom: -1px;
	}
	.tabs button.on {
		color: #1f6feb;
		border-bottom-color: #1f6feb;
	}
	.pp-row {
		display: flex;
		align-items: center;
		gap: 14px;
		margin-bottom: 16px;
	}
	.pp.lg {
		width: 64px;
		height: 64px;
	}
	.avatar.xl {
		width: 64px;
		height: 64px;
		font-size: 1.4rem;
	}
	.pp-info {
		display: flex;
		flex-direction: column;
		gap: 2px;
		min-width: 0;
	}
	.pp-info .muted {
		font-size: 0.8rem;
		color: #80868b;
	}
	.pp-link {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		margin-top: 4px;
		font-size: 0.8rem;
		color: #1f6feb;
		text-decoration: none;
		font-weight: 600;
	}
	.mi {
		width: 100%;
		height: 44px;
		padding: 0 12px;
		border: 1px solid #d5dae2;
		border-radius: 10px;
		font: inherit;
		outline: none;
		margin-bottom: 4px;
	}
	.mi:focus {
		border-color: #1f6feb;
	}
	.mi[readonly] {
		background: #f5f7fa;
		color: #5f6368;
	}
	.note {
		font-size: 0.8rem;
		color: #80868b;
		margin: 8px 0 0;
	}
	.seg-pick {
		display: flex;
		gap: 8px;
	}
	.seg-pick button {
		flex: 1;
		padding: 11px;
		border: 1px solid #d5dae2;
		border-radius: 10px;
		background: #fff;
		font: inherit;
		font-weight: 600;
		color: #5f6368;
		cursor: pointer;
	}
	.seg-pick button.on {
		border-color: #1f6feb;
		background: #eef4ff;
		color: #0b57d0;
	}
	/* ═══ LIVE SEARCH ═══ */
	.lp-search-wrap {
		position: relative;
		margin: 0 14px 8px;
	}
	.lp-search-wrap .lp-search {
		margin: 0;
	}
	.s-clear {
		display: grid;
		place-items: center;
		width: 24px;
		height: 24px;
		border: 0;
		background: transparent;
		color: #80868b;
		cursor: pointer;
		border-radius: 50%;
	}
	.s-clear:hover {
		background: #e6eaf0;
	}
	.s-backdrop {
		position: fixed;
		inset: 0;
		background: transparent;
		border: 0;
		z-index: 30;
	}
	.menu-backdrop {
		position: fixed;
		inset: 0;
		background: transparent;
		border: 0;
		padding: 0;
		z-index: 55;
		cursor: default;
	}
	.s-results {
		position: absolute;
		top: calc(100% + 6px);
		left: 0;
		right: 0;
		background: #fff;
		border: 1px solid #e6e9ef;
		border-radius: 14px;
		box-shadow: 0 18px 44px rgba(0, 0, 0, 0.16);
		z-index: 40;
		max-height: 62vh;
		overflow-y: auto;
		padding: 6px;
		animation: menuin 0.14s ease;
	}
	@keyframes -global-menuin {
		from { opacity: 0; transform: translateY(-6px); }
		to { opacity: 1; transform: translateY(0); }
	}
	.s-loading,
	.s-empty {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 14px 12px;
		color: #80868b;
		font-size: 0.85rem;
	}
	.s-item {
		display: flex;
		align-items: center;
		gap: 10px;
		width: 100%;
		padding: 8px 10px;
		border: 0;
		background: transparent;
		border-radius: 10px;
		cursor: pointer;
		text-align: left;
	}
	.s-item:hover {
		background: #f2f5f9;
	}
	.s-body {
		min-width: 0;
		flex: 1;
		display: flex;
		flex-direction: column;
	}
	.s-line1 {
		display: flex;
		justify-content: space-between;
		gap: 8px;
	}
	.s-who {
		font-size: 0.86rem;
		color: #202124;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.s-date {
		font-size: 0.72rem;
		color: #80868b;
		flex: none;
	}
	.s-subj {
		font-size: 0.8rem;
		color: #5f6368;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		display: flex;
		align-items: center;
		gap: 5px;
	}
	.s-all {
		display: block;
		text-align: center;
		padding: 10px;
		color: #1f6feb;
		font-size: 0.82rem;
		font-weight: 600;
		text-decoration: none;
		border-top: 1px solid #eef1f5;
		margin-top: 4px;
	}
	.s-all:hover {
		background: #f2f5f9;
	}
	/* ═══ SETTINGS: keamanan + tampilan ═══ */
	.sec-ico {
		display: grid;
		place-items: center;
		width: 48px;
		height: 48px;
		border-radius: 14px;
		background: #eef4ff;
		color: #1f6feb;
		margin-bottom: 10px;
	}
	.sec-h {
		margin: 0 0 12px;
		font-size: 1.05rem;
	}
	.warn-box {
		display: flex;
		gap: 10px;
		padding: 11px 13px;
		background: #fff8ec;
		border: 1px solid #f3dca8;
		border-radius: 12px;
		font-size: 0.82rem;
		color: #7a5a00;
		line-height: 1.5;
		margin-bottom: 12px;
	}
	.warn-box :global(svg) {
		flex: none;
		color: #e08600;
		margin-top: 1px;
	}
	.ok-box {
		padding: 12px 14px;
		background: #e7f6ec;
		border: 1px solid #b6e0c4;
		border-radius: 12px;
		color: #1a6b34;
		font-size: 0.86rem;
		line-height: 1.5;
	}
	.err-inline {
		color: #c0392b;
		font-size: 0.8rem;
		margin: 6px 0 0;
	}
	.dark-toggle {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		padding: 12px 14px;
		border: 1px solid #d5dae2;
		border-radius: 12px;
		background: #fff;
		cursor: pointer;
		font: inherit;
		font-weight: 600;
		color: #3c4043;
		margin-bottom: 14px;
	}
	.dt-left {
		display: flex;
		align-items: center;
		gap: 10px;
	}
	.switch-ui {
		width: 44px;
		height: 26px;
		border-radius: 999px;
		background: #c3c9d4;
		position: relative;
		transition: background 0.15s;
		flex: none;
	}
	.switch-ui.on {
		background: #1f6feb;
	}
	.knob {
		position: absolute;
		top: 3px;
		left: 3px;
		width: 20px;
		height: 20px;
		border-radius: 50%;
		background: #fff;
		transition: left 0.15s;
	}
	.switch-ui.on .knob {
		left: 21px;
	}
	/* ═══ DARK MODE ═══ */
	:global(html.psdark) {
		color-scheme: dark;
	}
	:global(html.psdark body) {
		background: #0e1116;
		color: #e6e9ef;
	}
	:global(html.psdark) .app {
		background: #0e1116;
	}
	:global(html.psdark) .sb-brand {
		color: #e6e9ef;
	}
	:global(html.psdark) .hamb,
	:global(html.psdark) .gear {
		color: #9aa4b2;
	}
	:global(html.psdark) .hamb:hover,
	:global(html.psdark) .gear:hover,
	:global(html.psdark) .fitem:hover,
	:global(html.psdark) .sb-foot:hover {
		background: #1b2029;
	}
	:global(html.psdark) .fitem {
		color: #c3ccd8;
	}
	:global(html.psdark) .fitem.active {
		background: #1e2a3d;
		color: #7fb0ff;
	}
	:global(html.psdark) .fitem .count {
		background: #e5484d;
		color: #fff;
	}
	:global(html.psdark) .sb-foot {
		border-top-color: #252b34;
	}
	:global(html.psdark) .me-name {
		color: #e6e9ef;
	}
	:global(html.psdark) .listpane,
	:global(html.psdark) .readpane {
		background: #161a20;
		box-shadow: 0 0 0 1px #252b34;
	}
	:global(html.psdark) .lp-search {
		background: #1b2029;
		color: #9aa4b2;
	}
	:global(html.psdark) .lp-search input {
		color: #e6e9ef;
	}
	:global(html.psdark) .segs button {
		color: #9aa4b2;
	}
	:global(html.psdark) .segs button:hover {
		background: #1b2029;
	}
	:global(html.psdark) .segs button.on {
		background: #1e2a3d;
		color: #7fb0ff;
	}
	:global(html.psdark) .chk {
		background: #161a20;
		border-color: #3a424e;
	}
	:global(html.psdark) .grp-h {
		color: #7a8493;
		background: linear-gradient(#161a20 70%, rgba(22, 26, 32, 0));
	}
	:global(html.psdark) .row:hover {
		background: #1b2029;
	}
	:global(html.psdark) .who {
		color: #dce2ea;
	}
	:global(html.psdark) .row.unseen .who {
		color: #f2f5f9;
	}
	:global(html.psdark) .subj,
	:global(html.psdark) .date {
		color: #8b95a3;
	}
	:global(html.psdark) .row.unseen .subj {
		color: #b6bfca;
	}
	:global(html.psdark) .row.sel {
		background: #1e2a3d;
		border-left-color: #4f8bff;
	}
	:global(html.psdark) .row.checked {
		background: #1a2431;
	}
	:global(html.psdark) .icon-btn {
		color: #9aa4b2;
	}
	:global(html.psdark) .icon-btn:hover {
		background: #1b2029;
	}
	:global(html.psdark) .back-btn {
		background: #1b2029;
		border-color: #2c333d;
		color: #dce2ea;
	}
	:global(html.psdark) .back-btn:hover {
		background: #222831;
	}
	:global(html.psdark) .bulkbar {
		background: #14202f;
		border-color: #26436b;
		color: #9cc2ff;
	}
	:global(html.psdark) .bb-all {
		background: #161a20;
		border-color: #26436b;
		color: #9cc2ff;
	}
	:global(html.psdark) .bb-close {
		color: #9cc2ff;
	}
	:global(html.psdark) .rd-toolbar,
	:global(html.psdark) .rd-actionbar,
	:global(html.psdark) .thread {
		border-color: #252b34;
	}
	:global(html.psdark) .rd-subject,
	:global(html.psdark) .rs-name {
		color: #f2f5f9;
	}
	:global(html.psdark) .rs-addr,
	:global(html.psdark) .rs-date,
	:global(html.psdark) .rd-to,
	:global(html.psdark) .counter,
	:global(html.psdark) .pager,
	:global(html.psdark) .ti-date {
		color: #8b95a3;
	}
	:global(html.psdark) .att {
		background: #1b2029;
		border-color: #2c333d;
		color: #c3ccd8;
	}
	:global(html.psdark) .att-ico {
		background: #1e2a3d;
	}
	:global(html.psdark) .textbody {
		color: #e6e9ef;
	}
	:global(html.psdark) .htmlframe {
		background: #fff;
		border-radius: 12px;
	}
	:global(html.psdark) .thread-item {
		color: #c3ccd8;
	}
	:global(html.psdark) .thread-item:hover {
		background: #1b2029;
	}
	:global(html.psdark) .pill {
		background: #1b2029;
		border-color: #2c333d;
		color: #dce2ea;
	}
	:global(html.psdark) .pill.primary {
		background: #1f6feb;
		border-color: #1f6feb;
		color: #fff;
	}
	:global(html.psdark) .pill.ghost {
		background: transparent;
		border-color: transparent;
	}
	:global(html.psdark) .empty {
		color: #7a8493;
	}
	:global(html.psdark) .empty-ill {
		background: #1e2a3d;
		color: #7fb0ff;
	}
	:global(html.psdark) .empty h3 {
		color: #dce2ea;
	}
	:global(html.psdark) .s-results,
	:global(html.psdark) .modal,
	:global(html.psdark) .composer {
		background: #161a20;
		color: #e6e9ef;
	}
	:global(html.psdark) .s-results {
		border-color: #2c333d;
	}
	:global(html.psdark) .s-item:hover,
	:global(html.psdark) .s-all:hover {
		background: #222831;
	}
	:global(html.psdark) .s-who {
		color: #e6e9ef;
	}
	:global(html.psdark) .s-subj,
	:global(html.psdark) .s-date,
	:global(html.psdark) .s-loading,
	:global(html.psdark) .s-empty {
		color: #8b95a3;
	}
	:global(html.psdark) .s-all {
		border-color: #252b34;
	}
	:global(html.psdark) .mi,
	:global(html.psdark) .mt {
		background: #1b2029;
		border-color: #2c333d;
		color: #e6e9ef;
	}
	:global(html.psdark) .mi[readonly] {
		background: #12161c;
	}
	:global(html.psdark) .tabs {
		border-color: #252b34;
	}
	:global(html.psdark) .tabs button {
		color: #8b95a3;
	}
	:global(html.psdark) .note,
	:global(html.psdark) .pp-info .muted {
		color: #8b95a3;
	}
	:global(html.psdark) .dark-toggle {
		background: #1b2029;
		border-color: #2c333d;
		color: #dce2ea;
	}
	:global(html.psdark) .seg-pick button {
		background: #1b2029;
		border-color: #2c333d;
		color: #9aa4b2;
	}
	:global(html.psdark) .seg-pick button.on {
		background: #1e2a3d;
		border-color: #4f8bff;
		color: #7fb0ff;
	}
	:global(html.psdark) .cp-field {
		border-color: #252b34;
	}
	:global(html.psdark) .cp-field input,
	:global(html.psdark) .cp-from,
	:global(html.psdark) .cp-editor {
		color: #e6e9ef;
	}
	:global(html.psdark) .cp-from option {
		background: #1b2029;
		color: #e6e9ef;
	}
	:global(html.psdark) .cp-toolbar button,
	:global(html.psdark) .cp-icon,
	:global(html.psdark) .cp-ccbtns button {
		color: #9aa4b2;
	}
	:global(html.psdark) .cp-toolbar button:hover,
	:global(html.psdark) .cp-icon:hover {
		background: #222831;
	}
	:global(html.psdark) .sk {
		background: linear-gradient(90deg, #1b2029 25%, #222831 37%, #1b2029 63%);
		background-size: 400% 100%;
	}
	:global(html.psdark) .trash-notice {
		background: #2a1618;
		border-color: #5a2a2a;
	}
	:global(html.psdark) .trash-notice span {
		color: #f0a3a3;
	}
	:global(html.psdark) .tn-btn,
	:global(html.psdark) .tn-close {
		background: #161a20;
		border-color: #5a2a2a;
		color: #ff9b9b;
	}
	:global(html.psdark) .rmenu {
		background: #1b2029;
		border-color: #2c333d;
	}
	:global(html.psdark) .rmenu button {
		color: #dce2ea;
	}
	:global(html.psdark) .rmenu button:hover {
		background: #222831;
	}
	:global(html.psdark) .bgbar {
		background: #2a2410;
		border-color: #5a4a1a;
		color: #e6c96b;
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

	/* ═══ CETAK (print): hanya isi email ═══ */
	@media print {
		:global(body) {
			background: #fff !important;
		}
		:global(.top) {
			display: none !important;
		}
		.topbar-progress,
		.sb,
		.sb-backdrop,
		.fab,
		.listpane,
		.composer,
		.modal-bg,
		.mail-toast,
		.rd-toolbar,
		.rd-actionbar,
		.thread {
			display: none !important;
		}
		.app,
		.app.collapsed {
			display: block !important;
			height: auto !important;
			grid-template-columns: none !important;
		}
		.readpane {
			position: static !important;
			inset: auto !important;
			display: block !important;
			margin: 0 !important;
			border-radius: 0 !important;
			box-shadow: none !important;
			background: #fff !important;
			color: #000 !important;
		}
		.rd-scroll {
			overflow: visible !important;
			height: auto !important;
			padding: 0 !important;
		}
		.rd-subject,
		.rs-name,
		.rs-addr,
		.rs-date,
		.rd-to,
		.textbody {
			color: #000 !important;
		}
		.rd-body {
			background: #fff !important;
			padding: 0 !important;
		}
		.htmlframe {
			height: 950px !important;
			min-height: 0 !important;
		}
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
		:global(html.psdark) .sb {
			background: #161a20;
			box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6);
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
		.back-btn {
			display: inline-flex;
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
		.rd-actionbar {
			flex-wrap: nowrap;
			gap: 8px;
			padding: 10px 14px;
		}
		.rd-actionbar .pill {
			flex: 1 1 0;
			justify-content: center;
			padding: 10px 8px;
			font-size: 0.82rem;
			gap: 6px;
		}
		.lbl-full {
			display: none;
		}
		.lbl-short {
			display: inline;
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
