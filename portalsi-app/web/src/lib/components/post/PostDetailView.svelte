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
		Music2,
		Send,
		Trash2,
		Users,
		X
	} from '@lucide/svelte';
	import { untrack } from 'svelte';
	import { clientRequest } from '$lib/api/client';
	import Avatar from '$lib/components/ui/Avatar.svelte';
	import StoryAvatarLink from '$lib/components/story/StoryAvatarLink.svelte';
	import SmartVideo from '$lib/components/media/SmartVideo.svelte';
	import MediaLightbox from '$lib/components/media/MediaLightbox.svelte';
	import SharePostSheet from '$lib/components/feed/SharePostSheet.svelte';
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

	async function loadCollaborators() {
		try {
			const res = await clientRequest(`posts/${data.post.id}/collaborators`, {
				schema: postCollaboratorsSchema
			});
			collabList = res.collaborators.map((c) => ({
				userId: c.user_id,
				username: c.username,
				avatarUrl: normalizeMediaUrl(c.profile_picture_url, detailMediaBase) ?? undefined,
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
						avatarUrl: normalizeMediaUrl(u.profile_picture_url, detailMediaBase) ?? undefined
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

	let liked = $state(untrack(() => data.post.isLiked));
	let bookmarked = $state(untrack(() => data.post.isBookmarked));
	let likesCount = $state(untrack(() => data.post.likesCount));
	let liking = $state(false);
	let bookmarking = $state(false);
	let likeBurst = $state(false);
	let shareOpen = $state(false);
	let ownerMenuOpen = $state(false);
	let collabPopupOpen = $state(false);
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
	const shareUrl = $derived(
		typeof window !== 'undefined'
			? new URL(`/posts/${data.post.id}`, window.location.origin).toString()
			: `https://app.portalsi.com/posts/${data.post.id}`
	);

	async function toggleLike() {
		if (liking) return;
		liking = true;
		liked = !liked;
		likesCount += liked ? 1 : -1;
		try {
			await clientRequest(`posts/${data.post.id}/like`, { method: 'POST' });
		} catch {
			liked = !liked;
			likesCount += liked ? 1 : -1;
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
			{#if isPostOwner}<details
					class="owner-tools surface"
					ontoggle={(e) => {
						if ((e.currentTarget as HTMLDetailsElement).open && !collabLoaded) void loadCollaborators();
					}}
				>
					<summary aria-label="Kelola postingan"><MoreHorizontal size={20} /></summary>
					<form method="POST" action="?/update">
						<label>Caption <textarea name="caption" rows="4">{data.post.caption}</textarea></label>
						<label>Lokasi <input name="location" value={data.post.location ?? ''} maxlength="120" placeholder="Tambahkan lokasi" /></label>
						<div>
							<button type="submit">Simpan</button><button
								class="delete"
								type="submit"
								formaction="?/delete"
								onclick={(event) =>
									confirmButtonAction(event, {
										title: 'Hapus postingan?',
										description:
											'Foto/video, komentar, interaksi, DAN salinan di profil kolaborator akan dihapus permanen.',
										confirmLabel: 'Hapus postingan',
										tone: 'danger'
									})}><Trash2 size={14} /> Hapus</button
							>
						</div>
					</form>

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
				</details>{/if}
			{#if form?.message}<p class:success={form.success} class="notice" role="status">
					{form.message}
				</p>{/if}
			<div class="detail-media">
				{#if data.post.isVideo}
					<SmartVideo
						src={data.post.mediaUrl}
						sources={data.post.videoSources ?? []}
						poster={data.post.thumbnailUrl}
						label={data.post.mediaAlt}
						fill
						preferSound
						onDoubleTap={doubleTapLike}
					/>
				{:else if isGallery}
					<div class="dm-gallery">
						<div class="dm-track" style:transform={`translateX(-${slide * 100}%)`}>
							{#each gallery as src, i (i)}
								<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
								<img
									src={src}
									alt={`${data.post.mediaAlt} (${i + 1})`}
									ondblclick={doubleTapLike}
									onclick={() => openMobileLightbox(i)}
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
						ondblclick={doubleTapLike}
						onclick={() => openMobileLightbox(0)}
					/>
				{/if}
				{#if likeBurst}<span class="dm-burst"><Heart size={100} fill="currentColor" /></span>{/if}
			</div>
		</div>
		<section class="comments surface" id="comments">
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
					{#if data.post.location}<small><MapPin size={12} /> {data.post.location}</small>{/if}
				</div>
			</header>
			<div class="comment-list">
				{#if data.post.caption || data.post.music}
					<article class="pinned-caption">
						<Avatar name={data.post.user.fullName} src={data.post.user.avatarUrl} size="sm" />
						<div>
							<p>
								<strong>{data.post.user.username}</strong>
								{#if data.post.caption}<MentionText text={data.post.caption} />{/if}
							</p>
							{#if data.post.music}<span class="pc-music"
									><Music2 size={13} /> {data.post.music.title} — {data.post.music.artist}</span
								>{/if}
						</div>
					</article>
				{/if}
				{#each comments as comment (comment.id)}
					<article>
						<Avatar name={comment.user.fullName} src={comment.user.avatarUrl} size="sm" />
						<div>
							<p>
								<strong
									>{comment.user.fullName}<UserBadges
										verified={comment.user.badgeVerified}
										role={comment.user.role}
									/></strong
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
								><button onclick={() => startReply(comment.id, comment.user.fullName)}
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
									<CornerDownRight size={15} class="reply-arrow" /><Avatar
										name={reply.user.fullName}
										src={reply.user.avatarUrl}
										size="sm"
									/>
									<div>
										<p>
											<strong
												>{reply.user.fullName}<UserBadges
													verified={reply.user.badgeVerified}
													role={reply.user.role}
												/></strong
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
			<div class="ds-actions">
				<div class="ds-act-buttons">
					<button class:on={liked} onclick={toggleLike} disabled={liking} aria-label="Suka"
						><Heart size={24} fill={liked ? 'currentColor' : 'none'} /></button
					>
					<button
						onclick={() =>
							document.querySelector<HTMLTextAreaElement>('.comment-form textarea')?.focus()}
						aria-label="Komentar"><MessageCircle size={24} /></button
					>
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
	.owner-tools summary {
		font-size: 0.76rem;
		font-weight: 730;
	}
	.owner-tools form {
		display: grid;
		gap: 10px;
		margin-top: 14px;
	}
	.owner-tools label {
		display: grid;
		gap: 5px;
		color: var(--color-muted);
		font-size: 0.7rem;
		font-weight: 680;
	}
	.owner-tools form > small {
		color: var(--color-muted);
		font-size: 0.68rem;
	}
	.owner-tools textarea {
		width: 100%;
		padding: 9px 10px;
		background: var(--color-canvas);
		border: 1px solid var(--color-border);
		border-radius: 9px;
		outline: 0;
	}
	.owner-tools form > div {
		display: flex;
		gap: 7px;
	}
	.owner-tools button {
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
	.owner-tools button.delete {
		background: var(--color-danger);
	}
	.owner-tools input {
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
	.owner-tools .c-remove {
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
	.owner-tools .collab-add-results button {
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
	.owner-tools .collab-add-results button:hover {
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
		background: #0b0c0d;
		border-radius: 14px 0 0 14px;
		overflow: hidden;
	}
	.detail-media {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		max-height: 84vh;
	}
	.detail-media :global(.smart-video) {
		width: 100%;
	}
	.dm-img {
		display: block;
		max-width: 100%;
		max-height: 84vh;
		object-fit: contain;
		cursor: default;
	}
	.dm-gallery {
		position: relative;
		width: 100%;
		overflow: hidden;
	}
	.dm-track {
		display: flex;
		transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
	}
	.dm-track img {
		flex: 0 0 100%;
		width: 100%;
		max-height: 84vh;
		object-fit: contain;
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
		gap: 4px;
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
		gap: 9px;
		padding: 14px 15px;
		border-bottom: 1px solid var(--color-border);
	}
	.pinned-caption p {
		margin: 0;
		font-size: 0.82rem;
		line-height: 1.45;
	}
	.pinned-caption strong {
		margin-right: 5px;
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
		padding: 10px 14px 6px;
		border-top: 1px solid var(--color-border);
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
		margin-top: 8px;
		font-size: 0.84rem;
		font-weight: 700;
	}
	.ds-time {
		display: block;
		margin-top: 2px;
		color: var(--color-muted);
		font-size: 0.68rem;
		text-transform: uppercase;
		letter-spacing: 0.02em;
	}

	/* Owner tools → tombol titik-tiga di kanan atas media, dropdown kartu saat dibuka. */
	.owner-tools {
		position: absolute;
		top: 12px;
		right: 12px;
		z-index: 6;
		width: auto;
	}
	.owner-tools > summary {
		display: grid;
		width: 38px;
		height: 38px;
		margin-left: auto;
		place-items: center;
		list-style: none;
		background: rgb(0 0 0 / 45%);
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
		backdrop-filter: blur(6px);
	}
	.owner-tools > summary::-webkit-details-marker {
		display: none;
	}
	.owner-tools[open] {
		width: min(320px, 78vw);
		padding: 8px;
		background: var(--color-surface, #fff);
		border: 1px solid var(--color-border);
		border-radius: 14px;
		box-shadow: 0 16px 40px rgb(0 0 0 / 24%);
	}
	.owner-tools[open] > summary {
		background: var(--color-surface-soft, #eef0f3);
		color: var(--color-text);
		margin-bottom: 8px;
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
	/* Kolom tulis komentar disematkan (sticky) di bawah agar selalu mudah dijangkau. */
	.comment-compose {
		position: sticky;
		bottom: 0;
		z-index: 4;
		background: var(--color-surface);
		border-top: 1px solid var(--color-border);
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
	.comment-list > article {
		display: grid;
		grid-template-columns: auto 1fr;
		gap: 9px;
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
		}
		.comments {
			position: static;
		}
		.post-column {
			border-radius: 0;
		}
		.detail-media,
		.dm-img,
		.dm-track img {
			max-height: 70vh;
		}
		/* Di mobile biarkan mengalir natural (tanpa scroll internal komentar). */
		.comment-list {
			overflow: visible;
		}
	}
	@media (max-width: 767px) {
		.owner-tools,
		.comments {
			border-radius: 0;
		}
		.comment-list {
			padding-inline: 2px;
		}
	}
</style>
