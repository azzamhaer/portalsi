<script lang="ts">
	import {
		Bookmark,
		Check,
		ChevronLeft,
		ChevronRight,
		CornerDownRight,
		Heart,
		MapPin,
		MessageCircle,
		MoreHorizontal,
		UserMinus,
		Music2,
		Pin,
		Send,
		Trash2,
		Users,
		X
	} from '@lucide/svelte';
	import { untrack } from 'svelte';
	import { clientRequest, ClientApiError } from '$lib/api/client';
	import { setPostLike } from '$lib/api/likes';
	import Avatar from '$lib/components/ui/Avatar.svelte';
	import StoryAvatarLink from '$lib/components/story/StoryAvatarLink.svelte';
	import SmartVideo from '$lib/components/media/SmartVideo.svelte';
	import ViewportMusic from '$lib/components/media/ViewportMusic.svelte';
	import MediaLightbox from '$lib/components/media/MediaLightbox.svelte';
	import SharePostSheet from '$lib/components/feed/SharePostSheet.svelte';
	import CommentsSheet from '$lib/components/reels/CommentsSheet.svelte';
	import UserBadges from '$lib/components/ui/UserBadges.svelte';
	import { createdCommentResponseSchema } from '$lib/schemas/comment';
	import type { PageData } from '../../../routes/(app)/posts/[postId]/$types';
	import { confirmAction, confirmButtonAction } from '$lib/ui/confirm';
	import MentionText from '$lib/components/ui/MentionText.svelte';
	import MentionTextarea from '$lib/components/ui/MentionTextarea.svelte';
	import GifPicker from '$lib/components/comment/GifPicker.svelte';
	import { portal } from '$lib/actions/portal';
	import { env } from '$env/dynamic/public';
	import { normalizeMediaUrl } from '$lib/utils/media';
	import { postCollaboratorsSchema } from '$lib/schemas/collab';
	import MusicPicker, { type MusicTrack } from '$lib/components/composer/MusicPicker.svelte';
	import { searchResponseSchema } from '$lib/schemas/search';

	type PrivatePostData = Extract<PageData, { isPublic: false }>;
	let {
		data,
		form = null
	}: { data: PrivatePostData; form?: { message?: string; success?: boolean } | null } = $props();
	// Urutan komentar: paling banyak like di atas, lalu yang punya balasan, sisanya urutan asli.
	// Disortir sekali saat awal (tidak melompat-lompat saat di-like); komentar baru muncul di atas.
	function sortComments<T extends { likesCount: number; replies: { length: number } }>(list: T[]): T[] {
		return [...list].sort(
			(a, b) => b.likesCount - a.likesCount || b.replies.length - a.replies.length
		);
	}
	let comments = $state(untrack(() => sortComments(structuredClone(data.comments))));
	let expandedReplies = $state<Set<number>>(new Set());
	let content = $state('');
	let replyTo = $state<{ id: number; name: string } | null>(null);
	let submitting = $state(false);
	let formMessage = $state('');
	let commentCount = $state(untrack(() => data.post.commentsCount));
	let gifOpen = $state(false);
	let editing = $state<{ id: number; reply: boolean; text: string } | null>(null);
	let savingEdit = $state(false);

	// ---- Kelola kolaborator (owner) ----
	const detailMediaBase = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';
	const isPostOwner = data.post.user.id === data.currentUser.id;
	type CollabRow = { userId: number; username: string; avatarUrl?: string; status: string };
	let collabList = $state<CollabRow[]>([]);
	let collabLoaded = $state(false);
	let collabQuery = $state('');
	let collabResults = $state<{ id: number; username: string; avatarUrl?: string }[]>([]);
	let collabActionBusy = $state(false);

	// Pemilih lokasi (edit) — seperti saat buat post.
	let editLocation = $state(untrack(() => data.post.location ?? ''));
	let editLocationResults = $state<{ id: number; label: string }[]>([]);
	let editLocationChosen = $state(untrack(() => Boolean(data.post.location)));
	$effect(() => {
		const q = editLocation.trim();
		if (!ownerMenuOpen || editLocationChosen || q.length < 3) {
			editLocationResults = [];
			return;
		}
		const controller = new AbortController();
		const timer = setTimeout(async () => {
			try {
				const res = await fetch(`/api/external/locations?q=${encodeURIComponent(q)}`, {
					signal: controller.signal
				});
				const payload = (await res.json()) as { locations?: { id: number; label: string }[] };
				editLocationResults = payload.locations ?? [];
			} catch {
				/* abaikan */
			}
		}, 300);
		return () => {
			clearTimeout(timer);
			controller.abort();
		};
	});

	async function loadCollaborators() {
		try {
			const res = await clientRequest(`posts/${data.post.id}/collaborators`, {
				schema: postCollaboratorsSchema
			});
			collabList = res.collaborators.map((c) => ({
				userId: c.user_id,
				username: c.username,
				avatarUrl: normalizeMediaUrl(c.profile_picture_thumb_url ?? c.profile_picture_url, detailMediaBase) ?? undefined,
				status: c.status
			}));
		} catch {
			collabList = [];
		} finally {
			collabLoaded = true;
		}
	}

	$effect(() => {
		const q = collabQuery.trim();
		if (!isPostOwner || q.length < 2) {
			collabResults = [];
			return;
		}
		const controller = new AbortController();
		const timer = setTimeout(async () => {
			try {
				const res = await clientRequest(`search?q=${encodeURIComponent(q)}&type=users`, {
					schema: searchResponseSchema,
					signal: controller.signal
				});
				const taken = new Set([data.post.user.id, ...collabList.map((c) => c.userId)]);
				collabResults = res.users
					.filter((u) => !taken.has(u.user_id))
					.slice(0, 5)
					.map((u) => ({
						id: u.user_id,
						username: u.username,
						avatarUrl: normalizeMediaUrl(u.profile_picture_thumb_url ?? u.profile_picture_url, detailMediaBase) ?? undefined
					}));
			} catch {
				/* abaikan */
			}
		}, 260);
		return () => {
			clearTimeout(timer);
			controller.abort();
		};
	});

	async function addCollaborator(u: { id: number; username: string; avatarUrl?: string }) {
		if (collabActionBusy) return;
		collabActionBusy = true;
		try {
			await clientRequest(`posts/${data.post.id}/collaborators`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ collaborators: [u.id] })
			});
			collabList = [...collabList, { userId: u.id, username: u.username, avatarUrl: u.avatarUrl, status: 'pending' }];
			collabQuery = '';
			collabResults = [];
		} catch {
			/* abaikan */
		} finally {
			collabActionBusy = false;
		}
	}

	async function removeCollaborator(userId: number) {
		if (collabActionBusy) return;
		const target = collabList.find((c) => c.userId === userId);
		const confirmed = await confirmAction({
			title: 'Hapus kolaborator?',
			description: target
				? `@${target.username} akan dihapus dari kolaborator postingan ini. Salinan postingan di profilnya juga hilang.`
				: 'Kolaborator ini akan dihapus dari postingan.',
			confirmLabel: 'Hapus',
			tone: 'danger'
		});
		if (!confirmed) return;
		collabActionBusy = true;
		try {
			await clientRequest(`posts/${data.post.id}/collaborators/${userId}`, { method: 'DELETE' });
			collabList = collabList.filter((c) => c.userId !== userId);
		} catch {
			/* abaikan */
		} finally {
			collabActionBusy = false;
		}
	}

	// ---- Media + interaksi (layout detail ala IG) ----
	const gallery = $derived(
		data.post.media && data.post.media.length > 1 ? data.post.media : [data.post.mediaUrl]
	);
	const isGallery = $derived(gallery.length > 1);
	let slide = $state(0);
	function goSlide(i: number) {
		slide = Math.max(0, Math.min(gallery.length - 1, i));
	}

	// ---- Frame media dengan rasio tetap ----
	// Rasio diambil dari gambar PERTAMA lalu dipakai untuk semua slide, sehingga kotak
	// medianya persis mengikuti bentuk asli gambar (potret tetap potret) dan tingginya
	// tidak meloncat saat galeri digeser. Gambar TIDAK dipotong (object-fit: contain).
	// Dibatasi 4:5 sampai 1.91:1 supaya tidak ada post yang ekstrem tinggi/pipih.
	const MIN_FRAME_ASPECT = 4 / 5;
	const MAX_FRAME_ASPECT = 1.91;
	let frameAspect = $state<number | null>(null);

	function readAspect(img: HTMLImageElement) {
		if (frameAspect !== null) return;
		if (!img.naturalWidth || !img.naturalHeight) return;
		const ratio = img.naturalWidth / img.naturalHeight;
		frameAspect = Math.min(MAX_FRAME_ASPECT, Math.max(MIN_FRAME_ASPECT, ratio));
	}

	/**
	 * Baca rasio gambar, baik yang baru diunduh maupun yang SUDAH selesai dimuat.
	 *
	 * Sebelumnya ini hanya mengandalkan event `load`. Masalahnya, gambar yang sudah ada di
	 * cache browser (atau sudah dimuat saat HTML dari server dirender) selesai sebelum
	 * event listener sempat dipasang, jadi `load` tidak pernah menyala — akibatnya rasio
	 * tidak pernah terbaca dan frame-nya jatuh ke ukuran default. Action ini memeriksa
	 * `complete` lebih dulu, baru menunggu `load` bila memang belum selesai.
	 */
	function frameProbe(node: HTMLImageElement, index = 0) {
		// Hanya gambar pertama yang menentukan rasio frame; sisanya mengikuti.
		if (index !== 0) return;
		if (node.complete) readAspect(node);
		const onLoad = () => readAspect(node);
		node.addEventListener('load', onLoad);
		return { destroy: () => node.removeEventListener('load', onLoad) };
	}

	// ---- Geser kiri-kanan (galeri) ----
	let galleryEl = $state<HTMLDivElement | null>(null);
	let dragging = $state(false);
	let dragDx = $state(0);
	let dragStartX = 0;
	let dragStartY = 0;
	// Dipakai untuk membedakan "geser" dari "ketuk" (ketuk membuka lightbox).
	let dragMoved = false;

	function onGalleryPointerDown(event: PointerEvent) {
		if (!isGallery) return;
		dragging = true;
		dragMoved = false;
		dragStartX = event.clientX;
		dragStartY = event.clientY;
		dragDx = 0;
	}
	function onGalleryPointerMove(event: PointerEvent) {
		if (!dragging) return;
		const dx = event.clientX - dragStartX;
		const dy = event.clientY - dragStartY;
		// Gerakan yang lebih dominan vertikal dibiarkan jadi scroll halaman.
		if (!dragMoved && Math.abs(dy) > Math.abs(dx)) {
			dragging = false;
			dragDx = 0;
			return;
		}
		if (Math.abs(dx) > 6) dragMoved = true;
		dragDx = dx;
	}
	function onGalleryPointerUp() {
		if (!dragging) return;
		dragging = false;
		const width = galleryEl?.clientWidth ?? 1;
		if (Math.abs(dragDx) > width * 0.18) goSlide(slide + (dragDx < 0 ? 1 : -1));
		dragDx = 0;
	}

	let liked = $state(untrack(() => data.post.isLiked));
	let bookmarked = $state(untrack(() => data.post.isBookmarked));
	let likesCount = $state(untrack(() => data.post.likesCount));
	let liking = $state(false);
	let bookmarking = $state(false);
	let likeBurst = $state(false);
	let shareOpen = $state(false);
	let ownerMenuOpen = $state(false);

	// ---- Batalkan kolaborasi (dari sisi kolaborator) ----
	let leavingCollab = $state(false);
	let leftCollab = $state(false);
	async function leaveCollab() {
		if (leavingCollab) return;
		const ok = await confirmAction({
			title: 'Batalkan kolaborasi?',
			description:
				'Postingan ini tidak lagi muncul di profil Anda. Pemilik bisa mengundang Anda lagi kapan saja.',
			confirmLabel: 'Batalkan collab',
			tone: 'danger'
		});
		if (!ok) return;
		leavingCollab = true;
		try {
			await clientRequest(`posts/${data.post.id}/collaborators/leave`, { method: 'POST' });
			leftCollab = true;
		} catch {
			formMessage = 'Kolaborasi belum dapat dibatalkan. Coba lagi.';
		} finally {
			leavingCollab = false;
		}
	}

	// ---- Draft ----
	let isDraft = $state(untrack(() => data.post.isDraft ?? false));
	let publishing = $state(false);

	// Musik draft: bisa diganti/dihapus sebelum terbit. Dipakai ulang dari komponen yang
	// sama dengan composer, jadi hasil pencarian & rekomendasinya identik.
	let draftMusic = $state<MusicTrack | null>(
		untrack(() =>
			data.post.music
				? {
						id: data.post.music.title,
						title: data.post.music.title,
						artist: data.post.music.artist,
						durationSeconds: data.post.music.durationSeconds ?? 15,
						previewUrl: data.post.music.previewUrl ?? null,
						artworkUrl: null
					}
				: null
		)
	);
	let savingMusic = $state(false);
	let musicSaved = $state(false);

	async function saveDraftMusic() {
		if (savingMusic) return;
		savingMusic = true;
		musicSaved = false;
		try {
			const body = new FormData();
			// String kosong = hapus musik. Backend memakai `??` untuk field musik, sehingga
			// nilai kosong dibiarkan apa adanya — cukup untuk mengganti, dan untuk menghapus
			// kita kirim string kosong yang akan tersimpan sebagai null oleh middleware.
			body.set('music_track_name', draftMusic?.title ?? '');
			body.set('music_artist_name', draftMusic?.artist ?? '');
			body.set('music_preview_url', draftMusic?.previewUrl ?? '');
			body.set('music_album_art_url', draftMusic?.artworkUrl ?? '');
			body.set('music_start_position_ms', '0');
			body.set('music_clip_duration_ms', String((draftMusic?.durationSeconds ?? 15) * 1000));
			await clientRequest(`posts/${data.post.id}/update`, { method: 'POST', body });
			musicSaved = true;
			setTimeout(() => (musicSaved = false), 2500);
		} catch {
			formMessage = 'Musik belum dapat disimpan. Coba lagi.';
		} finally {
			savingMusic = false;
		}
	}

	async function publishDraft() {
		if (publishing) return;
		const confirmed = await confirmAction({
			title: 'Terbitkan draft ini?',
			description:
				'Setelah terbit, postingan langsung muncul di beranda pengikut Anda dan tidak bisa dikembalikan menjadi draft.',
			confirmLabel: 'Ya, terbitkan'
		});
		if (!confirmed) return;
		publishing = true;
		try {
			await clientRequest(`posts/${data.post.id}/publish`, { method: 'POST' });
			isDraft = false;
		} catch {
			formMessage = 'Draft belum dapat diterbitkan. Coba lagi.';
		} finally {
			publishing = false;
		}
	}

	// ---- Sematkan postingan ----
	// Batas 3 juga divalidasi di server; angka di sini hanya untuk teks & popup.
	const MAX_PINNED = 3;
	let pinned = $state(untrack(() => data.post.isPinned ?? false));
	let pinBusy = $state(false);

	async function togglePin() {
		if (pinBusy) return;
		pinBusy = true;
		const target = !pinned;
		try {
			const body = new FormData();
			body.set('pinned', target ? 'true' : 'false');
			const result = await clientRequest<{ pinned?: boolean }>(`posts/${data.post.id}/pin`, {
				method: 'POST',
				body
			});
			pinned = typeof result?.pinned === 'boolean' ? result.pinned : target;
		} catch (error) {
			// Server menolak karena sudah 3 → jelaskan lewat dialog, bukan pesan mentah.
			const status = error instanceof ClientApiError ? error.status : 0;
			if (status === 422) {
				await confirmAction({
					title: 'Sematan sudah penuh',
					description: `Anda hanya bisa menyematkan ${MAX_PINNED} postingan. Lepas salah satu sematan lebih dulu, lalu coba lagi.`,
					confirmLabel: 'Mengerti',
					cancelLabel: ''
				});
			} else {
				formMessage = 'Sematan belum dapat disimpan. Coba lagi.';
			}
		} finally {
			pinBusy = false;
		}
	}
	let collabPopupOpen = $state(false);
	let mobileCommentsOpen = $state(false);
	function openComments() {
		if (isDesktop) document.querySelector<HTMLTextAreaElement>('.comment-form textarea')?.focus();
		else mobileCommentsOpen = true;
	}
	let editedCountdown = $state(0);
	// Popup countdown 3 detik saat post berhasil diedit.
	$effect(() => {
		if (!form?.success) return;
		ownerMenuOpen = false;
		editedCountdown = 3;
		const iv = setInterval(() => {
			editedCountdown -= 1;
			if (editedCountdown <= 0) clearInterval(iv);
		}, 1000);
		return () => clearInterval(iv);
	});
	let lightboxOpen = $state(false);
	let lightboxIndex = $state(0);
	let isDesktop = $state(false);
	$effect(() => {
		const mq = window.matchMedia('(min-width: 900px)');
		const sync = () => (isDesktop = mq.matches);
		sync();
		mq.addEventListener('change', sync);
		return () => mq.removeEventListener('change', sync);
	});
	// Cocok dengan breakpoint layout mobile di <style> (max-width: 950px). Dipakai untuk
	// TIDAK merender identitas uploader di baris caption — kalau hanya disembunyikan lewat
	// CSS, spasi antara username dan caption ikut tertinggal di depan kata pertama.
	let isNarrow = $state(false);
	$effect(() => {
		const mq = window.matchMedia('(max-width: 950px)');
		const sync = () => (isNarrow = mq.matches);
		sync();
		mq.addEventListener('change', sync);
		return () => mq.removeEventListener('change', sync);
	});
	const shareUrl = $derived(
		typeof window !== 'undefined'
			? new URL(`/posts/${data.post.id}`, window.location.origin).toString()
			: `https://app.portalsi.com/posts/${data.post.id}`
	);

	async function toggleLike() {
		if (liking) return;
		liking = true;
		const previousLiked = liked;
		const previousCount = likesCount;
		const target = !liked;
		liked = target;
		likesCount += target ? 1 : -1;
		try {
			const result = await setPostLike(data.post.id, target);
			// Samakan dengan state server agar tidak desinkron (like "hilang" setelah refresh).
			liked = result.liked;
			if (result.likesCount !== null) likesCount = result.likesCount;
		} catch {
			liked = previousLiked;
			likesCount = previousCount;
		} finally {
			liking = false;
		}
	}
	function doubleTapLike() {
		if (!liked) void toggleLike();
		likeBurst = true;
		setTimeout(() => (likeBurst = false), 850);
	}
	async function toggleBookmark() {
		if (bookmarking) return;
		bookmarking = true;
		bookmarked = !bookmarked;
		try {
			await clientRequest(`bookmarks/${data.post.id}`, { method: bookmarked ? 'POST' : 'DELETE' });
		} catch {
			bookmarked = !bookmarked;
		} finally {
			bookmarking = false;
		}
	}
	function openMobileLightbox(index: number) {
		if (isDesktop) return; // Desktop: tanpa fullscreen.
		lightboxIndex = index;
		lightboxOpen = true;
	}

	// Banner undangan kolaborasi untuk viewer (bila diundang & belum menjawab).
	let inviteStatus = $state<'pending' | 'done' | 'hidden'>(
		untrack(() => (!isPostOwner && data.post.viewerCollabStatus === 'pending' ? 'pending' : 'hidden'))
	);
	let inviteBusy = $state(false);
	async function respondInvite(accept: boolean) {
		if (inviteBusy) return;
		const confirmed = await confirmAction({
			title: accept ? 'Terima kolaborasi?' : 'Tolak undangan?',
			description: accept
				? 'Postingan ini akan muncul juga di profil Anda sebagai co-author.'
				: 'Undangan kolaborasi ini akan dihapus.',
			confirmLabel: accept ? 'Terima' : 'Tolak',
			tone: accept ? 'default' : 'danger'
		});
		if (!confirmed) return;
		inviteBusy = true;
		try {
			await clientRequest(`posts/${data.post.id}/collaborators/${accept ? 'accept' : 'reject'}`, {
				method: 'POST'
			});
			inviteStatus = 'done';
		} catch {
			/* abaikan */
		} finally {
			inviteBusy = false;
		}
	}

	// Mulai membalas. Balasan selalu menempel ke komentar INDUK (topCommentId) agar
	// kedalaman maksimal 2 level. Untuk balas-ke-balasan, prefill "@username " sehingga
	// orang yang dibalas tetap dapat notifikasi mention.
	function startReply(topCommentId: number, displayName: string, mentionUsername?: string) {
		replyTo = { id: topCommentId, name: displayName };
		if (mentionUsername) content = `@${mentionUsername} `;
		queueMicrotask(() => {
			const el = document.querySelector<HTMLTextAreaElement>('.comment-form textarea');
			el?.focus();
			el?.setSelectionRange(el.value.length, el.value.length);
		});
	}

	function toggleReplies(id: number) {
		const next = new Set(expandedReplies);
		if (next.has(id)) next.delete(id);
		else next.add(id);
		expandedReplies = next;
	}

	async function sendComment(gifUrl: string | null = null) {
		const text = content.trim();
		if ((!text && !gifUrl) || submitting) return;
		submitting = true;
		formMessage = '';
		try {
			const response = await clientRequest(`posts/${data.post.id}/comments`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					content: text,
					gif_url: gifUrl,
					parent_comment_id: replyTo?.id ?? null
				}),
				schema: createdCommentResponseSchema
			});
			const created = {
				id: response.data.comment_id,
				user: data.currentUser,
				text: response.data.content,
				gifUrl: response.data.gif_url ?? gifUrl ?? null,
				createdLabel: 'baru saja',
				likesCount: 0,
				isLiked: false,
				parentId: response.data.parent_comment_id
			};
			if (replyTo) {
				const parentId = replyTo.id;
				const parent = comments.find((comment) => comment.id === parentId);
				parent?.replies.push(created);
				expandedReplies = new Set(expandedReplies).add(parentId); // buka balasan otomatis
			} else comments.unshift({ ...created, parentId: null, replies: [] });
			content = '';
			replyTo = null;
			commentCount += 1;
			formMessage = gifUrl ? 'GIF terkirim.' : 'Komentar terkirim.';
		} catch {
			formMessage = 'Komentar belum dapat dikirim.';
		} finally {
			submitting = false;
		}
	}

	function submitComment(event?: SubmitEvent) {
		event?.preventDefault();
		void sendComment(null);
	}

	function pickGif(url: string) {
		gifOpen = false;
		void sendComment(url);
	}

	async function toggleCommentLike(id: number, reply = false) {
		const item = reply
			? comments.flatMap((comment) => comment.replies).find((entry) => entry.id === id)
			: comments.find((entry) => entry.id === id);
		if (!item) return;
		const wasLiked = item.isLiked;
		item.isLiked = !wasLiked;
		item.likesCount += wasLiked ? -1 : 1;
		try {
			await clientRequest(`comments/${id}/like`, { method: wasLiked ? 'DELETE' : 'POST' });
		} catch {
			item.isLiked = wasLiked;
			item.likesCount += wasLiked ? 1 : -1;
			formMessage = 'Like komentar belum tersimpan.';
		}
	}

	function findComment(id: number, reply: boolean) {
		return reply
			? comments.flatMap((comment) => comment.replies).find((entry) => entry.id === id)
			: comments.find((entry) => entry.id === id);
	}

	function editComment(id: number, reply = false) {
		const item = findComment(id, reply);
		if (!item) return;
		editing = { id, reply, text: item.text };
	}

	async function saveEdit() {
		if (!editing || savingEdit) return;
		const text = editing.text.trim();
		const item = findComment(editing.id, editing.reply);
		if (!item || !text || text === item.text) {
			editing = null;
			return;
		}
		savingEdit = true;
		try {
			await clientRequest(`comments/${editing.id}`, {
				method: 'PUT',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ content: text })
			});
			item.text = text;
			editing = null;
		} catch {
			formMessage = 'Komentar belum dapat diedit.';
		} finally {
			savingEdit = false;
		}
	}

	async function deleteComment(id: number, reply = false) {
		if (
			!(await confirmAction({
				title: 'Hapus komentar?',
				description: 'Komentar ini akan dihapus dari percakapan.',
				confirmLabel: 'Hapus komentar',
				tone: 'danger'
			}))
		)
			return;
		try {
			await clientRequest(`comments/${id}`, { method: 'DELETE' });
			if (reply) {
				for (const comment of comments)
					comment.replies = comment.replies.filter((entry) => entry.id !== id);
			} else comments = comments.filter((entry) => entry.id !== id);
			commentCount = Math.max(0, commentCount - 1);
		} catch {
			formMessage = 'Komentar belum dapat dihapus.';
		}
	}
</script>

<div class="post-detail-layout">
		<div class="post-column">
			{#if inviteStatus === 'pending'}
				<div class="collab-invite-banner surface">
					<div class="cib-text">
						<strong>Kamu diundang berkolaborasi</strong>
						<small>Terima agar postingan ini juga muncul di profilmu.</small>
					</div>
					<div class="cib-actions">
						<button class="cib-accept" disabled={inviteBusy} onclick={() => respondInvite(true)}
							><Check size={16} /> Terima</button
						>
						<button class="cib-reject" disabled={inviteBusy} onclick={() => respondInvite(false)}
							><X size={16} /> Tolak</button
						>
					</div>
				</div>
			{:else if inviteStatus === 'done'}
				<div class="collab-invite-banner surface done">Undangan kolaborasi telah diproses.</div>
			{/if}
			{#if ownerMenuOpen}
				<!-- use:portal → dipindah ke <body>. Tanpa ini popup terjebak di dalam kolom
				     media/panel post yang ber-overflow hidden, sehingga ujungnya terpotong. -->
				<div
					class="edit-modal-scrim"
					use:portal
					role="presentation"
					onclick={() => (ownerMenuOpen = false)}
				>
					<div
						class="edit-modal-card"
						role="dialog"
						aria-modal="true"
						aria-label="Kelola postingan"
						onclick={(e) => e.stopPropagation()}
					>
						<header class="emc-head">
							<strong>Kelola postingan</strong>
							<button onclick={() => (ownerMenuOpen = false)} aria-label="Tutup"><X size={20} /></button>
						</header>
						<div class="emc-body">
						<form id="editPostForm" method="POST" action="?/update">
						<label>Caption <textarea name="caption" rows="4">{data.post.caption}</textarea></label>
						<div class="edit-loc">
							<label
								>Lokasi <input
									name="location"
									bind:value={editLocation}
									oninput={() => (editLocationChosen = false)}
									maxlength="160"
									autocomplete="off"
									placeholder="Cari & tambahkan lokasi"
								/></label
							>
							{#if editLocationResults.length}
								<div class="edit-loc-results">
									{#each editLocationResults as place (place.id)}
										<button
											type="button"
											onclick={() => {
												editLocation = place.label;
												editLocationChosen = true;
												editLocationResults = [];
											}}><MapPin size={14} /> {place.label}</button
										>
									{/each}
								</div>
							{/if}
						</div>
					</form>

					{#if isDraft}
						<div class="draft-music">
							<MusicPicker bind:selected={draftMusic} disabled={savingMusic} />
							<button type="button" disabled={savingMusic} onclick={saveDraftMusic}>
								{#if savingMusic}Menyimpan…{:else if musicSaved}Musik tersimpan{:else}Simpan musik{/if}
							</button>
						</div>
					{/if}

					<div class="pin-manage">
						<strong><Pin size={15} /> Sematkan di profil</strong>
						<p>
							Postingan yang disematkan tampil paling atas di profil Anda. Maksimal {MAX_PINNED}
							postingan.
						</p>
						<button
							type="button"
							class="pin-toggle"
							class:on={pinned}
							disabled={pinBusy}
							onclick={togglePin}
						>
							{#if pinBusy}Menyimpan…{:else if pinned}Lepas sematan{:else}Sematkan postingan{/if}
						</button>
					</div>

					<div class="collab-manage">
						<strong><Users size={15} /> Kolaborator</strong>
						{#if collabList.length > 0}
							<ul>
								{#each collabList as c (c.userId)}
									<li>
										<Avatar name={c.username} src={c.avatarUrl} size="sm" />
										<a href={`/u/${c.username}`}>@{c.username}</a>
										<span class="c-status" class:pending={c.status === 'pending'}
											>{c.status === 'accepted' ? 'Diterima' : 'Menunggu'}</span
										>
										<button
											class="c-remove"
											disabled={collabActionBusy}
											onclick={() => removeCollaborator(c.userId)}
											aria-label={`Hapus ${c.username}`}><X size={14} /></button
										>
									</li>
								{/each}
							</ul>
						{:else if collabLoaded}
							<p class="c-empty">Belum ada kolaborator.</p>
						{/if}
						{#if collabList.length < 5}
							<div class="collab-add">
								<input bind:value={collabQuery} maxlength="40" placeholder="Tambah kolaborator (cari username)" />
								{#if collabResults.length}
									<div class="collab-add-results">
										{#each collabResults as u (u.id)}
											<button type="button" onclick={() => addCollaborator(u)}>
												<Avatar name={u.username} src={u.avatarUrl} size="sm" /> @{u.username}
											</button>
										{/each}
									</div>
								{/if}
							</div>
						{/if}
						</div>
						</div>
						<div class="emc-actions">
							<button type="submit" form="editPostForm">Simpan</button>
							<button
								class="delete"
								type="submit"
								form="editPostForm"
								formaction="?/delete"
								onclick={(event) =>
									confirmButtonAction(event, {
										title: 'Hapus postingan?',
										description:
											'Foto/video, komentar, interaksi, dan salinan di profil kolaborator akan dihapus permanen.',
										confirmLabel: 'Hapus postingan',
										tone: 'danger'
									})}><Trash2 size={14} /> Hapus</button
							>
						</div>
					</div>
				</div>
			{/if}
			{#if form?.message && !form.success}<p class="notice" role="status">
					{form.message}
				</p>{/if}
			<div
				class="detail-media"
				class:framed={frameAspect !== null && !data.post.isVideo}
				style:--frame-aspect={frameAspect !== null ? String(frameAspect) : undefined}
			>
				{#if data.post.isVideo}
					<SmartVideo
						src={data.post.mediaUrl}
						sources={data.post.videoSources ?? []}
						poster={data.post.thumbnailUrl}
						label={data.post.mediaAlt}
						fill
						autoplay
						preferSound={!data.post.music?.previewUrl}
						forceMuted={data.post.videoMuted || Boolean(data.post.music?.previewUrl)}
						musicSrc={data.post.music?.previewUrl}
						musicStart={data.post.music?.startSeconds ?? 0}
						musicClip={data.post.music?.durationSeconds ?? 15}
						onDoubleTap={doubleTapLike}
					/>
				{:else if isGallery}
					<div
						class="dm-gallery"
						bind:this={galleryEl}
						onpointerdown={onGalleryPointerDown}
						onpointermove={onGalleryPointerMove}
						onpointerup={onGalleryPointerUp}
						onpointercancel={onGalleryPointerUp}
						onpointerleave={onGalleryPointerUp}
					>
						<div
							class="dm-track"
							class:dragging
							style:transform={`translateX(calc(-${slide * 100}% + ${dragDx}px))`}
						>
							{#each gallery as src, i (i)}
								<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
								<img
									src={src}
									alt={`${data.post.mediaAlt} (${i + 1})`}
									draggable="false"
									use:frameProbe={i}
									ondblclick={doubleTapLike}
									onclick={() => {
										// Setelah menggeser, jangan ikut membuka lightbox.
										if (dragMoved) return;
										openMobileLightbox(i);
									}}
								/>
							{/each}
						</div>
						{#if slide > 0}<button class="dm-nav prev" onclick={() => goSlide(slide - 1)} aria-label="Sebelumnya"><ChevronLeft size={22} /></button>{/if}
						{#if slide < gallery.length - 1}<button class="dm-nav next" onclick={() => goSlide(slide + 1)} aria-label="Berikutnya"><ChevronRight size={22} /></button>{/if}
						<div class="dm-dots">{#each gallery as _, i (i)}<span class:on={i === slide}></span>{/each}</div>
					</div>
				{:else}
					<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
					<img
						class="dm-img"
						src={data.post.mediaUrl}
						alt={data.post.mediaAlt}
						use:frameProbe
						ondblclick={doubleTapLike}
						onclick={() => openMobileLightbox(0)}
					/>
				{/if}
				{#if likeBurst}<span class="dm-burst"><Heart size={100} fill="currentColor" /></span>{/if}
			</div>
		</div>
		<section class="comments surface" id="comments">
			<!-- Banner draft sengaja diletakkan di kolom komentar, bukan kolom media: kolom
			     media berlatar gelap, tingginya dikunci, dan overflow-nya disembunyikan —
			     apa pun yang ditaruh di sana ikut terpotong dan tidak pernah terlihat. -->
			{#if isDraft && isPostOwner}
				<div class="draft-banner" role="status">
					<div>
						<strong>Ini masih draft</strong>
						<small>Hanya Anda yang bisa melihatnya.</small>
					</div>
					<button type="button" disabled={publishing} onclick={publishDraft}>
						{publishing ? 'Menerbitkan…' : 'Terbitkan'}
					</button>
				</div>
			{/if}
			<header class="ds-head">
				<StoryAvatarLink
					userId={data.post.user.id}
					username={data.post.user.username}
					name={data.post.user.fullName}
					avatarUrl={data.post.user.avatarUrl}
					size="md"
					hasStory={data.post.user.hasStory}
					seen={data.post.user.storyViewed}
				/>
				{#if data.post.coAuthors && data.post.coAuthors.length > 0}<button
						class="ds-costack"
						onclick={() => (collabPopupOpen = true)}
						aria-label="Lihat kolaborator"
						>{#each data.post.coAuthors.slice(0, 2) as ca (ca.id)}<Avatar
								name={ca.username}
								src={ca.avatarUrl}
								size="sm"
							/>{/each}{#if data.post.coAuthors.length > 2}<span class="more"
								>+{data.post.coAuthors.length - 2}</span
							>{/if}</button
					>{/if}
				<div class="ds-names">
					<strong>
						<a href={`/u/${data.post.user.username}`}>{data.post.user.username}</a>
						<UserBadges verified={data.post.user.badgeVerified} role={data.post.user.role} />
						{#if data.post.coAuthors && data.post.coAuthors.length === 1}<span class="co-and"
								>dan <a href={`/u/${data.post.coAuthors[0].username}`}
									>{data.post.coAuthors[0].username}</a
								></span
							>{:else if data.post.coAuthors && data.post.coAuthors.length > 1}<button
								class="co-and-btn"
								onclick={() => (collabPopupOpen = true)}
								>dan {data.post.coAuthors.length} lainnya</button
							>{/if}
					</strong>
				</div>
				{#if isPostOwner}<button
						class="ds-more"
						onclick={() => {
							ownerMenuOpen = true;
							if (!collabLoaded) void loadCollaborators();
						}}
						aria-label="Kelola postingan"><MoreHorizontal size={20} /></button
					>{:else if data.post.viewerCollabStatus === 'accepted' && !leftCollab}<button
						class="ds-leave"
						disabled={leavingCollab}
						onclick={leaveCollab}
						aria-label="Batalkan kolaborasi"
						><UserMinus size={15} /> {leavingCollab ? 'Membatalkan…' : 'Batalkan collab'}</button
					>{/if}
			</header>
			<div class="comment-list">
				{#if data.post.caption}
					<div class="pinned-caption">
						{#if !isNarrow}
							<span class="pc-avatar">
								<StoryAvatarLink
									userId={data.post.user.id}
									username={data.post.user.username}
									name={data.post.user.fullName}
									avatarUrl={data.post.user.avatarUrl}
									size="sm"
									hasStory={data.post.user.hasStory}
									seen={data.post.user.storyViewed}
								/>
							</span>
						{/if}
						<p>
							{#if !isNarrow}<a class="c-user" href={`/u/${data.post.user.username}`}
									><strong>{data.post.user.username}</strong><UserBadges
										verified={data.post.user.badgeVerified}
										role={data.post.user.role}
									/></a
								>
							{/if}<MentionText text={data.post.caption} />
						</p>
					</div>
				{/if}
				{#if data.post.music && data.post.music.previewUrl && !data.post.isVideo}
					<div class="pinned-music">
						<ViewportMusic
							src={data.post.music.previewUrl}
							title={data.post.music.title}
							artist={data.post.music.artist}
							start={data.post.music.startSeconds}
							clipDuration={data.post.music.durationSeconds}
							autoStart
						/>
					</div>
				{:else if data.post.music && data.post.music.title && data.post.isVideo}
					<!-- Video: musik menyatu dengan video (play/pause mengikuti video), jadi tanpa tombol. -->
					<div class="pinned-music-static">
						<Music2 size={15} />
						<span
							><strong>{data.post.music.title}</strong>{#if data.post.music.artist}
								— {data.post.music.artist}{/if}</span
						>
					</div>
				{/if}
				{#if data.post.location}
					<div class="pinned-location">
						<MapPin size={14} /><span>{data.post.location}</span>
					</div>
				{/if}
				{#if commentCount > 0}<button
						class="mobile-comments-link"
						onclick={() => (mobileCommentsOpen = true)}
						>Lihat semua {commentCount} komentar</button
					>{/if}
				<div class="inline-thread">
				{#each comments as comment (comment.id)}
					<article>
						<StoryAvatarLink
							userId={comment.user.id}
							username={comment.user.username}
							name={comment.user.fullName}
							avatarUrl={comment.user.avatarUrl ?? undefined}
							size="sm"
							hasStory={comment.user.hasStory}
							seen={comment.user.storyViewed}
						/>
						<div>
							<p>
								<a class="c-user" href={`/u/${comment.user.username}`}
									><strong>{comment.user.username}</strong><UserBadges
										verified={comment.user.badgeVerified}
										role={comment.user.role}
									/></a
								>
								{#if !comment.gifUrl}<MentionText text={comment.text} />{/if}
							</p>
							{#if comment.gifUrl}<img
									class="comment-gif"
									src={comment.gifUrl}
									alt="Komentar GIF"
									loading="lazy"
								/>{/if}
							<footer>
								<time>{comment.createdLabel}</time><button
									class:active={comment.isLiked}
									onclick={() => toggleCommentLike(comment.id)}
									><Heart size={13} fill={comment.isLiked ? 'currentColor' : 'none'} />
									{comment.likesCount}</button
								><button onclick={() => startReply(comment.id, comment.user.username)}
									>Balas</button
								>
								{#if comment.user.id === data.currentUser.id}{#if !comment.gifUrl}<button
											onclick={() => editComment(comment.id)}>Edit</button
										>{/if}<button onclick={() => deleteComment(comment.id)}>Hapus</button>{/if}
							</footer>
							{#if comment.replies.length > 0}<button
									class="show-replies"
									onclick={() => toggleReplies(comment.id)}
									><span></span>{expandedReplies.has(comment.id)
										? 'Sembunyikan balasan'
										: `Lihat ${comment.replies.length} balasan`}</button
								>{/if}
							{#if expandedReplies.has(comment.id)}
								{#each comment.replies as reply (reply.id)}
								<div class="reply">
									<CornerDownRight size={15} class="reply-arrow" /><StoryAvatarLink
										userId={reply.user.id}
										username={reply.user.username}
										name={reply.user.fullName}
										avatarUrl={reply.user.avatarUrl ?? undefined}
										size="sm"
										hasStory={reply.user.hasStory}
										seen={reply.user.storyViewed}
									/>
									<div>
										<p>
											<a class="c-user" href={`/u/${reply.user.username}`}
												><strong>{reply.user.username}</strong><UserBadges
													verified={reply.user.badgeVerified}
													role={reply.user.role}
												/></a
											>
											{#if !reply.gifUrl}<MentionText text={reply.text} />{/if}
										</p>
										{#if reply.gifUrl}<img
												class="comment-gif"
												src={reply.gifUrl}
												alt="Balasan GIF"
												loading="lazy"
											/>{/if}
										<footer>
											<time>{reply.createdLabel}</time><button
												class:active={reply.isLiked}
												onclick={() => toggleCommentLike(reply.id, true)}
												><Heart size={13} fill={reply.isLiked ? 'currentColor' : 'none'} />
												{reply.likesCount}</button
											>
											<button class="reply-btn" onclick={() => startReply(comment.id, reply.user.username, reply.user.username)}>Balas</button>{#if reply.user.id === data.currentUser.id}{#if !reply.gifUrl}<button
														onclick={() => editComment(reply.id, true)}>Edit</button
													>{/if}<button onclick={() => deleteComment(reply.id, true)}>Hapus</button
												>{/if}
										</footer>
									</div>
								</div>
							{/each}
							{/if}
						</div>
					</article>
				{/each}
				{#if comments.length === 0}<p class="empty">Jadilah yang pertama berkomentar.</p>{/if}
				</div>
			</div>
			<div class="ds-actions">
				<div class="ds-act-buttons">
					<button class:on={liked} onclick={toggleLike} disabled={liking} aria-label="Suka"
						><Heart size={24} fill={liked ? 'currentColor' : 'none'} /></button
					>
					<button onclick={openComments} aria-label="Komentar"><MessageCircle size={24} /></button>
					<button onclick={() => (shareOpen = true)} aria-label="Bagikan"><Send size={22} /></button>
					<button
						class="ds-bookmark"
						class:on={bookmarked}
						onclick={toggleBookmark}
						disabled={bookmarking}
						aria-label="Simpan"><Bookmark size={23} fill={bookmarked ? 'currentColor' : 'none'} /></button
					>
				</div>
				{#if likesCount > 0}<strong class="ds-likes">{likesCount.toLocaleString('id-ID')} suka</strong
					>{/if}
				<time class="ds-time">{data.post.createdLabel}</time>
			</div>
			<button class="mobile-compose-bar" onclick={() => (mobileCommentsOpen = true)}>
				<Avatar name={data.currentUser.fullName} src={data.currentUser.avatarUrl} size="sm" />
				<span>Tulis komentar…</span>
			</button>
			<div class="comment-compose">
			{#if replyTo}<div class="replying">
					Membalas {replyTo.name}<button
						onclick={() => (replyTo = null)}
						aria-label="Batal membalas"><X size={14} /></button
					>
				</div>{/if}
			<form class="comment-form" onsubmit={submitComment}>
				<Avatar name={data.currentUser.fullName} src={data.currentUser.avatarUrl} size="sm" />
				<label
					><span class="sr-only">Tulis komentar</span><MentionTextarea
						bind:value={content}
						name="comment"
						maxlength={2000}
						rows={1}
						placeholder={replyTo ? 'Tulis balasan…' : 'Tulis komentar…'}
						onEnter={() => submitComment()}
					/></label
				>
				<button
					type="button"
					class="gif-btn"
					onclick={() => (gifOpen = true)}
					disabled={submitting}
					aria-label="Kirim GIF">GIF</button
				>
				<button type="submit" aria-label="Kirim komentar" disabled={!content.trim() || submitting}
					><Send size={18} /></button
				>
			</form>
			{#if formMessage}<p class="form-message" aria-live="polite">{formMessage}</p>{/if}
			</div>
			{#if gifOpen}<GifPicker onSelect={pickGif} onClose={() => (gifOpen = false)} />{/if}
		</section>
	</div>

{#if editedCountdown > 0}
	<div class="edited-toast-scrim" role="presentation" onclick={() => (editedCountdown = 0)}>
		<div class="edited-toast" role="status">
			<span class="et-ring"><Check size={26} /></span>
			<strong>Postingan diperbarui</strong>
			<small>Tertutup otomatis dalam {editedCountdown} detik…</small>
			<button onclick={() => (editedCountdown = 0)}>Tutup</button>
		</div>
	</div>
{/if}

{#if mobileCommentsOpen}
	<CommentsSheet
		postId={data.post.id}
		currentUserId={data.currentUser.id}
		onClose={() => (mobileCommentsOpen = false)}
		onPosted={(n) => (commentCount = n)}
	/>
{/if}

{#if shareOpen}
	<SharePostSheet postId={data.post.id} {shareUrl} onClose={() => (shareOpen = false)} />
{/if}

{#if lightboxOpen}
	<MediaLightbox
		open={lightboxOpen}
		src={gallery[lightboxIndex]}
		alt={data.post.mediaAlt}
		onClose={() => (lightboxOpen = false)}
	/>
{/if}

{#if collabPopupOpen && data.post.coAuthors && data.post.coAuthors.length > 0}
	<div class="collab-modal-scrim" role="presentation" onclick={() => (collabPopupOpen = false)}>
		<div class="collab-modal" role="dialog" aria-modal="true" aria-label="Kolaborator" onclick={(e) => e.stopPropagation()}>
			<header>
				<strong>Kolaborasi</strong>
				<button onclick={() => (collabPopupOpen = false)} aria-label="Tutup"><X size={19} /></button>
			</header>
			<ul>
				<li>
					<a href={`/u/${data.post.user.username}`}>
						<Avatar name={data.post.user.fullName} src={data.post.user.avatarUrl} size="md" />
						<span><strong>@{data.post.user.username}</strong><small>Pembuat</small></span>
					</a>
				</li>
				{#each data.post.coAuthors as ca (ca.id)}
					<li>
						<a href={`/u/${ca.username}`}>
							<Avatar name={ca.fullName} src={ca.avatarUrl} size="md" />
							<span><strong>@{ca.username}</strong><small>Kolaborator</small></span>
						</a>
					</li>
				{/each}
			</ul>
		</div>
	</div>
{/if}

{#if editing}
	<div use:portal>
	<div class="edit-overlay" role="presentation" onclick={() => (editing = null)}></div>
	<div class="edit-modal" role="dialog" aria-modal="true" aria-label="Edit komentar">
		<header>
			<strong>Edit komentar</strong>
			<button onclick={() => (editing = null)} aria-label="Tutup"><X size={18} /></button>
		</header>
		<textarea
			bind:value={editing.text}
			rows="4"
			maxlength="2000"
			placeholder="Tulis komentar…"
			onkeydown={(event) => {
				if (event.key === 'Enter' && !event.shiftKey) {
					event.preventDefault();
					void saveEdit();
				}
			}}
		></textarea>
		<div class="edit-actions">
			<button class="cancel" onclick={() => (editing = null)}>Batal</button>
			<button class="save" onclick={saveEdit} disabled={savingEdit || !editing.text.trim()}
				>{savingEdit ? 'Menyimpan…' : 'Simpan'}</button
			>
		</div>
	</div>
	</div>
{/if}

<style>
	.edit-overlay {
		position: fixed;
		inset: 0;
		z-index: 1450;
		background: rgb(18 13 8 / 55%);
		backdrop-filter: blur(2px);
	}
	.edit-modal {
		position: fixed;
		z-index: 1451;
		top: 50%;
		left: 50%;
		display: grid;
		width: min(440px, calc(100% - 28px));
		gap: 12px;
		padding: 18px;
		background: var(--color-surface);
		border-radius: 18px;
		box-shadow: 0 24px 60px rgb(0 0 0 / 30%);
		transform: translate(-50%, -50%);
	}
	.edit-modal > header {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}
	.edit-modal > header strong {
		font-size: 1rem;
	}
	.edit-modal > header button {
		display: grid;
		width: 34px;
		height: 34px;
		place-items: center;
		border: 0;
		border-radius: 50%;
		background: var(--color-canvas-deep, #f1ece3);
		color: var(--color-muted);
	}
	.edit-modal textarea {
		width: 100%;
		padding: 12px;
		background: var(--color-canvas, #fbf7ef);
		border: 1px solid var(--color-border);
		border-radius: 12px;
		outline: 0;
		font: inherit;
		line-height: 1.4;
		resize: vertical;
	}
	.edit-modal textarea:focus {
		border-color: var(--color-primary);
		box-shadow: var(--focus-ring);
	}
	.edit-actions {
		display: flex;
		justify-content: flex-end;
		gap: 10px;
	}
	.edit-actions button {
		min-height: 42px;
		padding: 0 18px;
		border: 0;
		border-radius: 11px;
		font-weight: 720;
	}
	.edit-actions .cancel {
		background: var(--color-canvas-deep, #f1ece3);
		color: var(--color-text);
	}
	.edit-actions .save {
		background: var(--color-primary);
		color: white;
	}
	.edit-actions .save:disabled {
		opacity: 0.5;
	}
	.edit-modal-card summary {
		font-size: 0.76rem;
		font-weight: 730;
	}
	.edit-modal-card form {
		display: grid;
		gap: 10px;
		margin-top: 14px;
	}
	.edit-modal-card label {
		display: grid;
		gap: 5px;
		color: var(--color-muted);
		font-size: 0.7rem;
		font-weight: 680;
	}
	.edit-modal-card form > small {
		color: var(--color-muted);
		font-size: 0.68rem;
	}
	.edit-modal-card textarea {
		width: 100%;
		padding: 9px 10px;
		background: var(--color-canvas);
		border: 1px solid var(--color-border);
		border-radius: 9px;
		outline: 0;
	}
	.edit-modal-card form > div {
		display: flex;
		gap: 7px;
	}
	.edit-modal-card button {
		display: flex;
		min-height: 38px;
		align-items: center;
		gap: 5px;
		padding: 0 13px;
		background: var(--color-primary);
		border: 0;
		border-radius: 9px;
		color: white;
		font-size: 0.72rem;
		font-weight: 720;
	}
	.edit-modal-card button.delete {
		background: var(--color-danger);
	}
	.edit-modal-card input {
		width: 100%;
		min-height: 38px;
		padding: 0 10px;
		background: var(--color-canvas);
		border: 1px solid var(--color-border);
		border-radius: 9px;
		outline: 0;
		font-size: 0.82rem;
	}
	.collab-invite-banner {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
		padding: 13px 15px;
		background: var(--color-primary-soft, #fdece0);
		border: 1px solid var(--color-primary, #f28a22);
	}
	.collab-invite-banner.done {
		justify-content: center;
		color: var(--color-muted);
		font-size: 0.8rem;
	}
	.cib-text {
		display: grid;
		min-width: 0;
	}
	.cib-text strong {
		font-size: 0.88rem;
	}
	.cib-text small {
		color: var(--color-muted);
		font-size: 0.74rem;
	}
	.cib-actions {
		display: flex;
		gap: 8px;
	}
	.cib-actions button {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		min-height: 36px;
		padding: 0 14px;
		border: 0;
		border-radius: 9px;
		font-size: 0.78rem;
		font-weight: 720;
		cursor: pointer;
	}
	.cib-accept {
		background: var(--color-primary);
		color: #fff;
	}
	.cib-reject {
		background: var(--color-surface, #fff);
		color: var(--color-danger);
	}
	.cib-actions button:disabled {
		opacity: 0.6;
	}
	.draft-banner {
		display: flex;
		flex: none;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		padding: 12px 16px;
		background: var(--color-primary-soft);
		border-bottom: 1px solid var(--color-border);
		/* Banner ini anak pertama kolom komentar yang sudut atasnya membulat. Tanpa
		   mewarisi radius, latarnya menimpa sudut itu dan ujungnya terlihat terpotong. */
		border-radius: inherit;
		border-bottom-left-radius: 0;
		border-bottom-right-radius: 0;
	}
	.draft-banner strong {
		display: block;
		font-size: 0.86rem;
	}
	.draft-banner small {
		color: var(--color-muted);
		font-size: 0.74rem;
		line-height: 1.45;
	}
	.draft-banner button {
		flex: none;
		min-height: 38px;
		padding: 0 15px;
		background: var(--color-primary);
		border: 0;
		border-radius: 10px;
		color: white;
		font-size: 0.82rem;
		font-weight: 720;
		cursor: pointer;
	}
	.draft-banner button:disabled {
		opacity: 0.6;
		cursor: default;
	}
	.draft-music {
		display: grid;
		gap: 10px;
		margin-top: 14px;
		padding-top: 12px;
		border-top: 1px solid var(--color-border);
	}
	.draft-music > button {
		min-height: 40px;
		background: transparent;
		border: 1px solid var(--color-border);
		border-radius: 11px;
		color: var(--color-text);
		font-size: 0.82rem;
		font-weight: 700;
		cursor: pointer;
	}
	.draft-music > button:hover:not(:disabled) {
		background: var(--color-primary-soft);
	}
	.draft-music > button:disabled {
		opacity: 0.6;
		cursor: default;
	}
	.pin-manage {
		display: grid;
		gap: 8px;
		margin-top: 14px;
		padding-top: 12px;
		border-top: 1px solid var(--color-border);
	}
	.pin-manage > strong {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 0.8rem;
	}
	.pin-manage > p {
		margin: 0;
		color: var(--color-muted);
		font-size: 0.74rem;
		line-height: 1.5;
	}
	.pin-toggle {
		min-height: 42px;
		background: transparent;
		border: 1px solid var(--color-border);
		border-radius: 11px;
		color: var(--color-text);
		font-size: 0.82rem;
		font-weight: 700;
		cursor: pointer;
	}
	.pin-toggle:hover:not(:disabled) {
		background: var(--color-primary-soft);
	}
	.pin-toggle.on {
		background: var(--color-primary);
		border-color: var(--color-primary);
		color: white;
	}
	.pin-toggle:disabled {
		opacity: 0.6;
		cursor: default;
	}
	.collab-manage {
		margin-top: 14px;
		padding-top: 12px;
		border-top: 1px solid var(--color-border);
		display: grid;
		gap: 9px;
	}
	.collab-manage > strong {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 0.8rem;
	}
	.collab-manage ul {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		gap: 4px;
	}
	.collab-manage li {
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.collab-manage li a {
		font-size: 0.82rem;
		font-weight: 700;
		color: var(--color-text);
	}
	.c-status {
		margin-left: auto;
		padding: 2px 8px;
		border-radius: 999px;
		background: var(--color-secondary-soft, #e7f6ee);
		color: var(--color-secondary, #1a9d5a);
		font-size: 0.64rem;
		font-weight: 800;
	}
	.c-status.pending {
		background: var(--color-warning-soft, #fdf0dc);
		color: var(--color-warning-strong, #b7791f);
	}
	.edit-modal-card .c-remove {
		display: grid;
		place-items: center;
		width: 28px;
		min-height: 28px;
		padding: 0;
		background: var(--color-danger-soft);
		color: var(--color-danger);
		border-radius: 8px;
	}
	.c-empty {
		margin: 0;
		color: var(--color-muted);
		font-size: 0.74rem;
	}
	.collab-add {
		position: relative;
		display: grid;
		gap: 5px;
	}
	.collab-add-results {
		display: grid;
		gap: 2px;
		padding: 4px;
		background: var(--color-canvas);
		border: 1px solid var(--color-border);
		border-radius: 10px;
	}
	.edit-modal-card .collab-add-results button {
		display: flex;
		align-items: center;
		gap: 8px;
		min-height: 40px;
		padding: 0 9px;
		background: transparent;
		border-radius: 8px;
		color: var(--color-text);
		font-weight: 700;
	}
	.edit-modal-card .collab-add-results button:hover {
		background: var(--color-surface-soft, #f4f5f7);
	}
	.notice {
		margin: 0;
		padding: 9px 11px;
		background: var(--color-danger-soft);
		border-radius: 9px;
		color: var(--color-danger);
		font-size: 0.72rem;
	}
	.notice.success {
		background: var(--color-secondary-soft);
		color: var(--color-secondary);
	}
	.likers > div {
		display: grid;
		max-height: 260px;
		margin-top: 10px;
		overflow-y: auto;
	}
	.likers a {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 0;
		border-top: 1px solid var(--color-border);
	}
	.likers a span {
		display: grid;
	}
	.likers strong {
		font-size: 0.74rem;
	}
	.likers small,
	.likers p {
		color: var(--color-muted);
		font-size: 0.66rem;
	}
	.post-detail-layout {
		display: grid;
		grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
		gap: 0;
		align-items: stretch;
	}
	/* ---- Kolom media (kiri) ---- */
	.post-column {
		position: relative;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		min-width: 0;
		height: 100%;
		background: #0a0b0c;
		border-radius: 16px 0 0 16px;
		overflow: hidden;
	}
	.detail-media {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 100%;
	}
	.detail-media :global(.smart-video) {
		width: 100%;
		height: 100%;
	}
	/* Frame mengikuti rasio gambar pertama, jadi kotak medianya pas dengan gambarnya dan
	   garis hitam kiri-kanan hilang dengan sendirinya — TANPA memotong apa pun.
	   Post lama yang rasionya di luar 4:5–1.91:1 tetap utuh (disisakan ruang, bukan
	   dipotong); post baru yang sudah di-crop saat upload akan mengisi frame dengan pas.
	   Tinggi frame juga tidak meloncat saat galeri digeser. */
	.detail-media.framed {
		width: 100%;
		height: auto;
		aspect-ratio: var(--frame-aspect);
	}
	/* Desktop: kolom media tingginya dikunci mengikuti panel modal. Kalau frame dihitung
	   dari LEBAR (seperti di mobile), foto potret jadi lebih tinggi dari kolomnya lalu
	   terpotong overflow — hasilnya terlihat kotak. Di sini frame dihitung dari TINGGI,
	   jadi rasio potret tetap utuh dan sisa ruang kiri-kanan diisi latar gelap. */
	@media (min-width: 951px) {
		.detail-media.framed {
			width: auto;
			height: 100%;
			max-width: 100%;
		}
	}
	.detail-media.framed .dm-img,
	.detail-media.framed .dm-track img {
		width: 100%;
		height: 100%;
		max-width: none;
		max-height: none;
		object-fit: contain;
	}
	.dm-img {
		display: block;
		max-width: 100%;
		max-height: 100%;
		object-fit: contain;
		cursor: default;
	}
	.dm-gallery {
		display: flex;
		align-items: center;
		width: 100%;
		height: 100%;
	}
	.dm-gallery {
		position: relative;
		width: 100%;
		overflow: hidden;
	}
	.dm-track {
		display: flex;
		width: 100%;
		height: 100%;
		transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
		/* Geser horizontal ditangani sendiri; vertikal tetap milik scroll halaman. */
		touch-action: pan-y;
	}
	/* Saat jari masih menempel, ikuti gerakan tanpa transisi biar terasa langsung. */
	.dm-track.dragging {
		transition: none;
	}
	.dm-track img {
		flex: 0 0 100%;
		width: 100%;
		height: 100%;
		max-height: 100%;
		object-fit: contain;
		user-select: none;
		-webkit-user-drag: none;
	}
	.dm-nav {
		position: absolute;
		top: 50%;
		display: grid;
		width: 34px;
		height: 34px;
		place-items: center;
		background: rgb(255 255 255 / 88%);
		border: 0;
		border-radius: 50%;
		color: #111;
		transform: translateY(-50%);
		box-shadow: 0 2px 8px rgb(0 0 0 / 25%);
		cursor: pointer;
	}
	.dm-nav.prev {
		left: 10px;
	}
	.dm-nav.next {
		right: 10px;
	}
	.dm-dots {
		position: absolute;
		bottom: 12px;
		left: 0;
		right: 0;
		display: flex;
		justify-content: center;
		gap: 5px;
	}
	.dm-dots span {
		width: 6px;
		height: 6px;
		border-radius: 50%;
		background: rgb(255 255 255 / 45%);
	}
	.dm-dots span.on {
		background: #fff;
	}
	.dm-burst {
		position: absolute;
		top: 50%;
		left: 50%;
		color: #ff2d55;
		transform: translate(-50%, -50%) scale(0.4);
		animation: dm-burst 0.85s ease forwards;
		pointer-events: none;
		filter: drop-shadow(0 4px 14px rgb(0 0 0 / 35%));
	}
	@keyframes dm-burst {
		0% {
			opacity: 0;
			transform: translate(-50%, -50%) scale(0.4);
		}
		25% {
			opacity: 1;
			transform: translate(-50%, -50%) scale(1.1);
		}
		70% {
			opacity: 1;
			transform: translate(-50%, -50%) scale(1);
		}
		100% {
			opacity: 0;
			transform: translate(-50%, -50%) scale(1.15);
		}
	}

	/* ---- Kolom info + komentar (kanan) ---- */
	.comments {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}
	.comments > .ds-head {
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 12px 15px;
		border-bottom: 1px solid var(--color-border);
	}
	.ds-more {
		display: grid;
		place-items: center;
		width: 34px;
		height: 34px;
		margin-left: auto;
		flex: none;
		background: none;
		border: 0;
		border-radius: 50%;
		color: var(--color-text);
		cursor: pointer;
	}
	.ds-more:hover {
		background: var(--color-surface-soft, #f1f2f4);
	}
	/* Tombol batalkan collab: menempati posisi tombol kelola (kanan atas), gaya danger halus. */
	.ds-leave {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		margin-left: auto;
		flex: none;
		padding: 7px 12px;
		background: transparent;
		border: 1px solid var(--color-border);
		border-radius: 10px;
		color: #c0392b;
		font-size: 0.78rem;
		font-weight: 700;
		cursor: pointer;
	}
	.ds-leave:hover:not(:disabled) {
		background: rgb(192 57 43 / 8%);
		border-color: rgb(192 57 43 / 35%);
	}
	.ds-leave:disabled {
		opacity: 0.6;
		cursor: default;
	}
	.ds-costack {
		display: inline-flex;
		align-items: center;
		margin-left: -14px;
		padding: 0;
		background: none;
		border: 0;
		cursor: pointer;
	}
	.ds-costack :global(.avatar-wrap) {
		margin-left: -8px;
		border-radius: 50%;
		box-shadow: 0 0 0 2px var(--color-surface, #fff);
	}
	.ds-costack .more {
		display: grid;
		place-items: center;
		min-width: 22px;
		height: 22px;
		margin-left: -8px;
		padding: 0 5px;
		background: var(--color-secondary-soft, #e7ebf2);
		border-radius: 999px;
		box-shadow: 0 0 0 2px var(--color-surface, #fff);
		font-size: 0.62rem;
		font-weight: 800;
		color: var(--color-secondary, #4b5563);
	}
	.ds-names {
		display: grid;
		min-width: 0;
	}
	.ds-names strong {
		display: inline-flex;
		align-items: center;
		gap: 7px;
		font-size: 0.9rem;
		flex-wrap: wrap;
	}
	.ds-names a {
		color: inherit;
	}
	.ds-names small {
		display: inline-flex;
		align-items: center;
		gap: 3px;
		color: var(--color-muted);
		font-size: 0.72rem;
	}
	.co-and,
	.co-and-btn {
		background: none;
		border: 0;
		padding: 0;
		color: inherit;
		font-weight: 700;
		cursor: pointer;
	}
	.pinned-caption {
		display: flex;
		align-items: flex-start;
		gap: 10px;
		padding: 14px 15px;
	}
	.pc-avatar {
		display: inline-flex;
		flex: none;
	}
	.pinned-caption p {
		flex: 1;
		min-width: 0;
		margin: 0;
		font-size: 0.84rem;
		line-height: 1.5;
		white-space: pre-wrap;
		word-break: break-word;
	}
	.c-user {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		margin-right: 5px;
		color: var(--color-text);
		vertical-align: baseline;
	}
	.c-user strong {
		font-weight: 700;
	}
	.pinned-music {
		padding: 2px 16px 12px;
	}
	.pinned-music-static {
		display: flex;
		align-items: center;
		gap: 7px;
		padding: 2px 15px 12px;
		color: var(--color-muted);
		font-size: 0.78rem;
	}
	.pinned-music-static :global(svg) {
		flex: none;
	}
	.pinned-music-static strong {
		font-weight: 600;
		color: var(--color-text);
	}
	.pinned-music :global(.viewport-music) {
		font-size: 0.78rem;
	}
	.pinned-music :global(.viewport-music strong) {
		font-weight: 600;
	}
	.pinned-location {
		display: flex;
		align-items: center;
		gap: 6px;
		padding: 0 16px 14px;
		color: var(--color-muted);
		font-size: 0.78rem;
	}
	.pinned-location :global(svg) {
		flex: none;
	}
	.pinned-caption a {
		color: inherit;
	}
	.pinned-caption strong {
		font-weight: 700;
	}
	.pc-music {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		margin-top: 6px;
		color: var(--color-muted);
		font-size: 0.72rem;
	}
	.comment-list {
		flex: 1;
		min-height: 0;
		overflow-y: auto;
	}
	.ds-actions {
		padding: 8px 14px 8px;
	}
	.ds-act-buttons {
		display: flex;
		align-items: center;
		gap: 14px;
	}
	.ds-act-buttons button {
		display: grid;
		place-items: center;
		background: none;
		border: 0;
		color: var(--color-text);
		cursor: pointer;
	}
	.ds-act-buttons button.on {
		color: #ff2d55;
	}
	.ds-act-buttons .ds-bookmark {
		margin-left: auto;
		color: var(--color-text);
	}
	.ds-act-buttons .ds-bookmark.on {
		color: var(--color-primary-strong, #c96a10);
	}
	.ds-likes {
		display: block;
		margin-top: 10px;
		font-size: 0.84rem;
		font-weight: 700;
	}
	.ds-time {
		display: block;
		margin-top: 10px;
		color: var(--color-muted);
		font-size: 0.66rem;
		text-transform: uppercase;
		letter-spacing: 0.03em;
	}
	.ds-likes + .ds-time {
		margin-top: 3px;
	}

	/* Owner tools → tombol titik-tiga di kanan atas media, dropdown kartu saat dibuka. */
	.post-more {
		position: absolute;
		top: 12px;
		right: 12px;
		z-index: 6;
		display: grid;
		width: 38px;
		height: 38px;
		place-items: center;
		background: rgb(0 0 0 / 42%);
		border: 0;
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
		backdrop-filter: blur(6px);
	}
	.edit-modal-scrim {
		position: fixed;
		inset: 0;
		z-index: 1500;
		display: grid;
		place-items: center;
		padding: 18px;
		background: rgb(0 0 0 / 48%);
		animation: cm-fade 0.15s ease;
	}
	.edit-modal-card {
		display: flex;
		flex-direction: column;
		width: min(100%, 480px);
		max-height: 86vh;
		overflow: hidden;
		background: var(--color-surface, #fff);
		border-radius: 18px;
		box-shadow: 0 24px 60px rgb(0 0 0 / 30%);
		animation: cm-pop 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
	}
	.emc-actions {
		display: flex;
		gap: 8px;
		flex: none;
		padding: 14px 18px calc(14px + env(safe-area-inset-bottom, 0px));
		border-top: 1px solid var(--color-border);
	}
	.emc-actions button {
		flex: 1;
		justify-content: center;
	}
	.emc-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 15px 18px;
		border-bottom: 1px solid var(--color-border);
	}
	.emc-head strong {
		font-size: 1rem;
	}
	.emc-head button {
		background: none;
		border: 0;
		color: var(--color-muted);
		cursor: pointer;
	}
	.emc-body {
		flex: 1;
		min-height: 0;
		overflow-y: auto;
	}
	.edit-loc {
		display: grid;
		gap: 5px;
	}
	.edit-loc-results {
		display: grid;
		gap: 2px;
		padding: 4px;
		background: var(--color-canvas);
		border: 1px solid var(--color-border);
		border-radius: 10px;
	}
	.edit-loc-results button {
		display: flex;
		align-items: center;
		gap: 7px;
		min-height: 38px;
		padding: 0 9px;
		background: transparent;
		border-radius: 8px;
		color: var(--color-text);
		font-size: 0.8rem;
		text-align: left;
	}
	.edit-loc-results button:hover {
		background: var(--color-surface-soft, #f4f5f7);
	}
	.edit-modal-card form,
	.edit-modal-card .draft-music,
	.edit-modal-card .pin-manage {
		padding: 16px 18px 0;
		border-top: 0;
		margin-top: 0;
	}
	.edit-modal-card .collab-manage {
		padding: 16px 18px;
	}
	.edit-modal-card .collab-manage {
		border-top: 1px solid var(--color-border);
	}
	@keyframes cm-fade {
		from {
			opacity: 0;
		}
	}
	@keyframes cm-pop {
		from {
			opacity: 0;
			transform: scale(0.95);
		}
	}

	.edited-toast-scrim {
		position: fixed;
		inset: 0;
		z-index: 1600;
		display: grid;
		place-items: center;
		padding: 20px;
		background: rgb(0 0 0 / 40%);
		animation: cm-fade 0.15s ease;
	}
	.edited-toast {
		display: grid;
		justify-items: center;
		gap: 6px;
		width: min(100%, 320px);
		padding: 24px 22px 18px;
		background: var(--color-surface, #fff);
		border-radius: 18px;
		text-align: center;
		animation: cm-pop 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
	}
	.et-ring {
		display: grid;
		place-items: center;
		width: 52px;
		height: 52px;
		margin-bottom: 4px;
		border-radius: 50%;
		background: var(--color-secondary-soft, #e7f6ee);
		color: var(--color-secondary, #1a9d5a);
	}
	.edited-toast strong {
		font-size: 1.02rem;
	}
	.edited-toast small {
		color: var(--color-muted);
		font-size: 0.78rem;
	}
	.edited-toast button {
		width: 100%;
		min-height: 40px;
		margin-top: 10px;
		background: var(--color-primary);
		border: 0;
		border-radius: 11px;
		color: #fff;
		font-weight: 700;
		cursor: pointer;
	}
	.collab-modal-scrim {
		position: fixed;
		inset: 0;
		z-index: 1500;
		display: grid;
		place-items: center;
		padding: 20px;
		background: rgb(0 0 0 / 45%);
	}
	.collab-modal {
		width: min(100%, 360px);
		max-height: 70vh;
		overflow: hidden;
		display: flex;
		flex-direction: column;
		background: var(--color-surface, #fff);
		border-radius: 16px;
	}
	.collab-modal header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 13px 15px;
		border-bottom: 1px solid var(--color-border);
	}
	.collab-modal header button {
		background: none;
		border: 0;
		color: var(--color-muted);
		cursor: pointer;
	}
	.collab-modal ul {
		list-style: none;
		margin: 0;
		padding: 6px;
		overflow-y: auto;
	}
	.collab-modal li a {
		display: flex;
		align-items: center;
		gap: 11px;
		padding: 9px 10px;
		border-radius: 12px;
		color: inherit;
	}
	.collab-modal li span {
		display: grid;
	}
	.collab-modal li small {
		color: var(--color-muted);
		font-size: 0.72rem;
	}
	.comment-compose {
		background: var(--color-surface);
		border-top: 1px solid var(--color-border);
	}
	.mobile-comments-link,
	.mobile-compose-bar {
		display: none;
	}
	@media (max-width: 950px) {
		.inline-thread,
		.comment-compose {
			display: none;
		}
		.mobile-comments-link {
			display: block;
			width: 100%;
			padding: 6px 16px 12px;
			background: none;
			border: 0;
			color: var(--color-muted);
			font-size: 0.82rem;
			text-align: left;
			cursor: pointer;
		}
		.mobile-compose-bar {
			display: flex;
			align-items: center;
			gap: 10px;
			width: 100%;
			padding: 10px 15px calc(10px + env(safe-area-inset-bottom, 0px));
			background: var(--color-surface);
			border: 0;
			border-top: 1px solid var(--color-border);
			cursor: pointer;
		}
		.mobile-compose-bar span {
			color: var(--color-muted);
			font-size: 0.86rem;
		}
	}
	.show-replies {
		display: flex;
		align-items: center;
		gap: 8px;
		margin: 4px 0 2px;
		padding: 4px 0;
		background: transparent;
		border: 0;
		color: var(--color-muted);
		font-size: 0.72rem;
		font-weight: 650;
	}
	.show-replies span {
		width: 22px;
		height: 1px;
		background: var(--color-border);
	}
	.inline-thread > article {
		display: grid;
		grid-template-columns: auto 1fr;
		gap: 10px;
		align-items: start;
		padding: 14px 15px;
		border-bottom: 1px solid var(--color-border);
	}
	article p {
		margin: 0;
		font-size: 0.8rem;
		overflow-wrap: anywhere;
	}
	article footer {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-top: 6px;
		color: var(--color-muted);
		font-size: 0.66rem;
	}
	article footer button {
		display: flex;
		align-items: center;
		gap: 4px;
		padding: 0;
		background: transparent;
		border: 0;
		color: inherit;
		font-size: inherit;
		cursor: pointer;
	}
	article footer button.active {
		color: var(--color-primary-strong);
	}
	.reply {
		display: grid;
		grid-template-columns: auto auto 1fr;
		gap: 7px;
		margin-top: 13px;
		color: var(--color-muted);
	}
	:global(.reply-arrow) {
		margin-top: 8px;
	}
	.reply p {
		color: var(--color-text);
	}
	.replying {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 8px 14px;
		background: var(--color-primary-soft);
		color: var(--color-primary-strong);
		font-size: 0.72rem;
	}
	.replying button {
		display: grid;
		place-items: center;
		padding: 3px;
		background: transparent;
		border: 0;
	}
	.comment-form {
		display: grid;
		grid-template-columns: auto minmax(0, 1fr) auto auto;
		align-items: center;
		gap: 8px;
		padding: 12px;
		border-top: 1px solid var(--color-border);
	}
	.comment-form label {
		display: block;
		min-width: 0;
	}
	.comment-form :global(.mention-field textarea) {
		display: block;
		width: 100%;
		min-height: 42px;
		max-height: 120px;
		padding: 10px 13px;
		background: var(--color-canvas);
		border: 1px solid var(--color-border);
		border-radius: 12px;
		outline: 0;
		font: inherit;
		line-height: 1.35;
		resize: none;
	}
	.comment-form button {
		display: grid;
		width: 42px;
		height: 42px;
		flex: none;
		place-items: center;
		background: var(--color-primary);
		border: 0;
		border-radius: 12px;
		color: white;
	}
	.comment-form .gif-btn {
		background: var(--color-secondary-soft, #d9efe6);
		color: var(--color-secondary, #178f72);
		font-size: 0.7rem;
		font-weight: 800;
		letter-spacing: 0.02em;
	}
	.comment-form button:disabled {
		opacity: 0.45;
	}
	.comment-gif {
		max-width: min(220px, 68%);
		margin: 6px 0 2px;
		border-radius: 12px;
		border: 1px solid var(--color-border);
	}
	.form-message,
	.empty {
		margin: 0;
		padding: 10px 14px;
		color: var(--color-muted);
		font-size: 0.72rem;
		text-align: center;
	}
	@media (max-width: 950px) {
		.post-detail-layout {
			grid-template-columns: 1fr;
			grid-template-rows: auto;
		}
		.comments {
			position: static;
			background: var(--color-surface);
			/* Menyatu dengan media di atasnya — tanpa lengkung atas. */
			border-radius: 0;
		}
		/* Media: penuh selebar layar, tinggi dibatasi agar info tetap terlihat. */
		.post-column {
			height: auto;
			/* Cukup tinggi untuk menampung frame portrait 4:5 tanpa terpotong.
			   Video tetap dibatasi 56vh lewat aturan di bawah. */
			max-height: 78vh;
			border-radius: 16px 16px 0 0;
		}
		.detail-media {
			height: auto;
		}
		.dm-img,
		.dm-track img,
		.detail-media :global(.smart-video) {
			max-height: 56vh;
		}
		.detail-media {
			justify-content: center;
		}
		/* Mobile: mengalir natural (tanpa scroll internal komentar). */
		.comment-list {
			overflow: visible;
			flex: initial;
		}
		.comments > .ds-head {
			padding: 12px 14px;
		}
		/* Mobile: identitas uploader sudah tampil di header tepat di atasnya, jadi baris
		   caption hanya berisi teksnya (lihat `isNarrow` di script). */
		.pinned-caption {
			padding-top: 4px;
		}
		.ds-actions {
			padding: 10px 14px 4px;
		}
	}
	@media (max-width: 767px) {
		.edit-modal-card {
			border-radius: 18px;
		}
		.comment-list {
			padding-inline: 2px;
		}
	}
</style>
