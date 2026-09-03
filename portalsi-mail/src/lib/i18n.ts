import { derived, writable } from 'svelte/store';

export type Lang = 'id' | 'en';

// Cookie BERSAMA lintas subdomain *.portalsi.com — ganti bahasa di app mana pun, semua ikut.
export const LANG_COOKIE = 'portalsi_lang';
const COOKIE_DOMAIN = '.portalsi.com';

export const lang = writable<Lang>('id');

function writeCookie(l: Lang) {
	if (typeof document === 'undefined') return;
	try {
		localStorage.setItem(LANG_COOKIE, l);
	} catch {
		/* ignore */
	}
	try {
		document.cookie = `${LANG_COOKIE}=${l};path=/;max-age=31536000;samesite=lax;domain=${COOKIE_DOMAIN}`;
	} catch {
		/* ignore */
	}
	try {
		document.cookie = `${LANG_COOKIE}=${l};path=/;max-age=31536000;samesite=lax`;
	} catch {
		/* ignore */
	}
	document.documentElement.lang = l;
}

export function setLang(l: Lang) {
	lang.set(l);
	writeCookie(l);
}

/** Inisialisasi dari nilai server (cookie) + sinkron ke cookie klien. */
export function initLang(initial?: Lang) {
	let l: Lang = initial === 'en' || initial === 'id' ? initial : 'id';
	if (typeof document !== 'undefined') {
		const m = document.cookie.match(/(?:^|;\s*)portalsi_lang=(id|en)/);
		if (m) l = m[1] as Lang;
		else {
			try {
				const s = localStorage.getItem(LANG_COOKIE);
				if (s === 'id' || s === 'en') l = s;
			} catch {
				/* ignore */
			}
		}
		document.documentElement.lang = l;
	}
	lang.set(l);
}

const DICT: Record<Lang, Record<string, string>> = {
	id: {
		// ── layout / menu ──
		'menu.aria': 'Menu aplikasi',
		'menu.title': 'Aplikasi Portal SI',
		'menu.app': 'App',
		'menu.meet': 'Meet',
		'menu.market': 'Market',
		'menu.home': 'Beranda',
		'menu.help': 'Bantuan',
		'menu.logout': 'Keluar',
		// ── umum ──
		'common.processing': 'Memproses…',
		'common.save': 'Simpan',
		'common.cancel': 'Batal',
		'common.close': 'Tutup',
		// ── login ──
		'login.eyebrow': 'SELAMAT DATANG KEMBALI',
		'login.title': 'Masuk ke Portal SI Mail',
		'login.sub': 'Buka email dan lanjutkan bisnismu.',
		'field.loginId': 'Username atau email',
		'field.password': 'Kata sandi',
		'login.pwPlaceholder': 'Masukkan kata sandi',
		'login.submit': 'Masuk',
		'login.noAccount': 'Belum punya akun?',
		'login.registerNow': 'Daftar sekarang',
		'login.verifiedOk': 'Email berhasil diverifikasi. Silakan masuk.',
		'login.verifiedBad': 'Tautan verifikasi tidak valid atau kedaluwarsa. Coba masuk lalu kirim ulang tautannya.',
		'login.emailChangedOk': 'Email pemulihan berhasil diganti.',
		'login.emailChangedBad': 'Tautan ganti email tidak valid atau kedaluwarsa.',
		'login.resent': 'Tautan verifikasi baru telah dikirim. Cek email kamu.',
		'login.resendTo': 'Kirim ulang tautan verifikasi ke',
		'login.fill': 'Isi email/username dan password.',
		// ── register ──
		'reg.eyebrow': 'MULAI PERJALANAN ANDA',
		'reg.title': 'Buat akun Portal SI',
		'reg.sub': 'Karena jutaan langkah dimulai dari satu langkah.',
		'field.fullName': 'Nama lengkap',
		'field.username': 'Username',
		'field.recoveryEmail': 'Email pemulihan',
		'field.repeatPassword': 'Ulangi kata sandi',
		'reg.pwPlaceholder': 'Minimal 6 karakter',
		'reg.repeatPlaceholder': 'Ketik ulang',
		'reg.mismatch': 'Kata sandi belum sama.',
		'reg.submit': 'Buat akun',
		'reg.haveAccount': 'Sudah punya akun?',
		'reg.signin': 'Masuk',
		// ── gate ──
		'gate.title': 'Akses beta',
		'gate.body': 'Portal SI Mail masih tahap beta. Masukkan master password dari admin untuk melanjutkan.',
		'gate.placeholder': 'Master password',
		'gate.submit': 'Buka akses',
		// ── setup ──
		'setup.title': 'Buat email kamu',
		'setup.name': 'Nama email',
		'setup.create': 'Buat email',
		'setup.recheck': 'Periksa lagi',
		'setup.confirmTitle': 'Konfirmasi alamat email',
		// ── settings ──
		'set.title': 'Pengaturan',
		'set.tab.profile': 'Profil',
		'set.tab.security': 'Keamanan',
		'set.tab.display': 'Tampilan',
		'set.displayMode': 'Mode tampilan',
		'set.dark': 'Mode gelap',
		'set.light': 'Mode terang',
		'set.darkNote': 'Mode gelap berlaku di seluruh aplikasi Mail.',
		'set.language': 'Bahasa',
		'set.languageNote': 'Preferensi bahasa berlaku di seluruh layanan Portal SI.',
		'set.saved': 'Pengaturan disimpan',
		'set.changePw': 'Ganti kata sandi',
		'set.pwInfo': 'Kata sandi ini dipakai untuk masuk ke akun Portal SI Mail kamu.',
		'set.sendLink': 'Kirim tautan ke email',
		'set.emailFixed': 'Alamat email tidak bisa diubah setelah dibuat.',
		// ── folder ──
		'folder.inbox': 'Kotak Masuk',
		'folder.starred': 'Berbintang',
		'folder.sent': 'Terkirim',
		'folder.drafts': 'Draf',
		'folder.archive': 'Arsip',
		'folder.junk': 'Spam',
		'folder.trash': 'Sampah',
		// ── daftar / list ──
		'list.compose': 'Tulis email',
		'list.all': 'Semua',
		'list.unread': 'Belum dibaca',
		'list.refresh': 'Muat ulang',
		'list.searchIn': 'Cari',
		'list.noResults': 'Tidak ada hasil.',
		'list.allRead': 'Semua telah terbaca.',
		'list.empty': 'Tidak ada sesuatu disini.',
		'list.selected': 'dipilih',
		'list.selectAll': 'Pilih semua',
		'list.unselectAll': 'Batalkan semua',
		'list.newMail': 'Email baru masuk!',
		'list.noRecipient': '(tanpa penerima)',
		'trash.notice': 'Pesan di Sampah dihapus permanen setelah 30 hari.',
		'trash.empty': 'Bersihkan sampah sekarang',
		'trash.emptied': 'Sampah dikosongkan',
		// ── reader ──
		'read.reply': 'Balas',
		'read.forward': 'Teruskan',
		'read.markUnread': 'Tandai belum dibaca',
		'read.markRead': 'Tandai sudah dibaca',
		'read.archive': 'Arsipkan',
		'read.unarchive': 'Pindahkan ke Kotak Masuk',
		'read.trash': 'Pindahkan ke sampah',
		'read.purge': 'Hapus permanen',
		'read.star': 'Beri bintang',
		'read.unstar': 'Hapus bintang',
		'read.to': 'ke',
		'read.thread': 'Percakapan ini',
		'read.emptyBody': '(pesan kosong)',
		'read.pickToRead': 'Pilih email untuk dibaca',
		'read.pickHint': 'Klik salah satu pesan di daftar, atau tulis email baru.',
		// ── compose ──
		'cp.new': 'Pesan baru',
		'cp.from': 'Dari',
		'cp.to': 'Ke',
		'cp.subject': 'Subjek',
		'cp.subjectPlaceholder': 'Subjek',
		'cp.toPlaceholder': 'penerima@contoh.com',
		'cp.send': 'Kirim',
		'cp.saveDraft': 'Simpan draf',
		'cp.attach': 'Lampirkan',
		'cp.insertImage': 'Sisipkan gambar',
		'cp.discard': 'Buang',
		'cp.sent': 'Email terkirim',
		'cp.draftSaved': 'Draf disimpan',
		'cp.needRecipient': 'Isi penerima (To) dulu.'
	},
	en: {
		'menu.aria': 'App menu',
		'menu.title': 'Portal SI apps',
		'menu.app': 'App',
		'menu.meet': 'Meet',
		'menu.market': 'Market',
		'menu.home': 'Home',
		'menu.help': 'Help',
		'menu.logout': 'Sign out',
		'common.processing': 'Processing…',
		'common.save': 'Save',
		'common.cancel': 'Cancel',
		'common.close': 'Close',
		'login.eyebrow': 'WELCOME BACK',
		'login.title': 'Sign in to Portal SI Mail',
		'login.sub': 'Open your inbox and get back to it.',
		'field.loginId': 'Username or email',
		'field.password': 'Password',
		'login.pwPlaceholder': 'Enter your password',
		'login.submit': 'Sign in',
		'login.noAccount': "Don't have an account?",
		'login.registerNow': 'Sign up',
		'login.verifiedOk': 'Your email has been verified. Please sign in.',
		'login.verifiedBad': 'The verification link is invalid or expired. Try signing in, then resend the link.',
		'login.emailChangedOk': 'Your recovery email was changed successfully.',
		'login.emailChangedBad': 'The email-change link is invalid or expired.',
		'login.resent': 'A new verification link has been sent. Check your email.',
		'login.resendTo': 'Resend verification link to',
		'login.fill': 'Enter your email/username and password.',
		'reg.eyebrow': 'START YOUR JOURNEY',
		'reg.title': 'Create your Portal SI account',
		'reg.sub': 'Because every great journey begins with a single step.',
		'field.fullName': 'Full name',
		'field.username': 'Username',
		'field.recoveryEmail': 'Recovery email',
		'field.repeatPassword': 'Repeat password',
		'reg.pwPlaceholder': 'At least 6 characters',
		'reg.repeatPlaceholder': 'Type it again',
		'reg.mismatch': "Passwords don't match yet.",
		'reg.submit': 'Create account',
		'reg.haveAccount': 'Already have an account?',
		'reg.signin': 'Sign in',
		'gate.title': 'Beta access',
		'gate.body': 'Portal SI Mail is still in beta. Enter the master password from your admin to continue.',
		'gate.placeholder': 'Master password',
		'gate.submit': 'Unlock access',
		'setup.title': 'Create your email',
		'setup.name': 'Email name',
		'setup.create': 'Create email',
		'setup.recheck': 'Check again',
		'setup.confirmTitle': 'Confirm your email address',
		'set.title': 'Settings',
		'set.tab.profile': 'Profile',
		'set.tab.security': 'Security',
		'set.tab.display': 'Appearance',
		'set.displayMode': 'Display mode',
		'set.dark': 'Dark mode',
		'set.light': 'Light mode',
		'set.darkNote': 'Dark mode applies across the whole Mail app.',
		'set.language': 'Language',
		'set.languageNote': 'Your language preference applies across all Portal SI services.',
		'set.saved': 'Settings saved',
		'set.changePw': 'Change password',
		'set.pwInfo': 'This password is used to sign in to your Portal SI Mail account.',
		'set.sendLink': 'Send link to email',
		'set.emailFixed': 'Your email address cannot be changed once created.',
		'folder.inbox': 'Inbox',
		'folder.starred': 'Starred',
		'folder.sent': 'Sent',
		'folder.drafts': 'Drafts',
		'folder.archive': 'Archive',
		'folder.junk': 'Spam',
		'folder.trash': 'Trash',
		'list.compose': 'Compose',
		'list.all': 'All',
		'list.unread': 'Unread',
		'list.refresh': 'Refresh',
		'list.searchIn': 'Search',
		'list.noResults': 'No results.',
		'list.allRead': 'All caught up.',
		'list.empty': 'Nothing here yet.',
		'list.selected': 'selected',
		'list.selectAll': 'Select all',
		'list.unselectAll': 'Clear selection',
		'list.newMail': 'New email arrived!',
		'list.noRecipient': '(no recipient)',
		'trash.notice': 'Messages in Trash are permanently deleted after 30 days.',
		'trash.empty': 'Empty trash now',
		'trash.emptied': 'Trash emptied',
		'read.reply': 'Reply',
		'read.forward': 'Forward',
		'read.markUnread': 'Mark as unread',
		'read.markRead': 'Mark as read',
		'read.archive': 'Archive',
		'read.unarchive': 'Move to Inbox',
		'read.trash': 'Move to trash',
		'read.purge': 'Delete permanently',
		'read.star': 'Add star',
		'read.unstar': 'Remove star',
		'read.to': 'to',
		'read.thread': 'This conversation',
		'read.emptyBody': '(empty message)',
		'read.pickToRead': 'Pick an email to read',
		'read.pickHint': 'Click a message in the list, or compose a new email.',
		'cp.new': 'New message',
		'cp.from': 'From',
		'cp.to': 'To',
		'cp.subject': 'Subject',
		'cp.subjectPlaceholder': 'Subject',
		'cp.toPlaceholder': 'recipient@example.com',
		'cp.send': 'Send',
		'cp.saveDraft': 'Save draft',
		'cp.attach': 'Attach',
		'cp.insertImage': 'Insert image',
		'cp.discard': 'Discard',
		'cp.sent': 'Email sent',
		'cp.draftSaved': 'Draft saved',
		'cp.needRecipient': 'Add a recipient (To) first.'
	}
};

/** `$t('key')` → string terjemahan; fallback ke ID lalu ke key. */
export const t = derived(
	lang,
	($l) =>
		(key: string, fallback?: string): string =>
			DICT[$l]?.[key] ?? DICT.id[key] ?? fallback ?? key
);
