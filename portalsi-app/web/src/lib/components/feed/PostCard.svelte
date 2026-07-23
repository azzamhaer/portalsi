<script lang="ts">
	import {
		Bookmark,
		ChevronLeft,
		ChevronRight,
		Heart,
		LoaderCircle,
		MapPin,
		Maximize2,
		MessageCircle,
		Send,
		ShieldAlert
	} from '@lucide/svelte';
	import { onMount } from 'svelte';
	import StoryAvatarLink from '$lib/components/story/StoryAvatarLink.svelte';
	import Avatar from '$lib/components/ui/Avatar.svelte';
	import UserBadges from '$lib/components/ui/UserBadges.svelte';
	import { X } from '@lucide/svelte';
	import SmartVideo from '$lib/components/media/SmartVideo.svelte';
	import ViewportMusic from '$lib/components/media/ViewportMusic.svelte';
	import MediaLightbox from '$lib/components/media/MediaLightbox.svelte';
	import { clientRequest } from '$lib/api/client';
	import { setPostLike } from '$lib/api/likes';
	import type { PostPreview } from '$lib/types/domain';
	import MentionText from '$lib/components/ui/MentionText.svelte';
	import SharePostSheet from '$lib/components/feed/SharePostSheet.svelte';
	import ModerationModal from '$lib/components/moderation/ModerationModal.svelte';
	import { isModerator } from '$lib/stores/session';

	// (gaya .mod-btn & .moderated-note ada di <style> bawah)

	let {
		post,
		zoomable = false,
		autoplay = false,
		preferSound = false
	}: { post: PostPreview; zoomable?: boolean; autoplay?: boolean; preferSound?: boolean } = $props();
	let lightboxOpen = $state(false);
	let collabPopupOpen = $state(false);
	let lightboxIndex = $state(0);
	let shareOpen = $state(false);
	let moderationOpen = $state(false);
	let moderatedHidden = $state(false);
	let openingPost = $state(false);

	// Tombol follow di header: hanya muncul kalau BELUM mengikuti saat load.
	// Setelah dipencet, berubah jadi "Mengikuti" (bisa di-unfollow di halaman itu).
	const canShowFollow = post.user.isFollowing !== true && post.user.id !== undefined;
	let following = $state(false);
	let followBusy = $state(false);
	async function toggleFollow() {
		if (followBusy) return;
		followBusy = true;
		const was = following;
		following = !was; // optimistic
		try {
			await clientRequest(was ? `unfollow/${post.user.id}` : `follow/${post.user.id}`, {
				method: was ? 'DELETE' : 'POST'
			});
		} catch {
			following = was; // rollback bila gagal
		} finally {
			followBusy = false;
		}
	}

	// Galeri multi-foto (Instagram-style). Video selalu tunggal.
	const gallery = $derived(post.media && post.media.length > 0 ? post.media : [post.mediaUrl]);
	const isGallery = $derived(!post.isVideo && gallery.length > 1);
	let slide = $state(0);
	let trackEl = $state<HTMLDivElement>();
	function onTrackScroll() {
		if (!trackEl) return;
		slide = Math.round(trackEl.scrollLeft / (trackEl.clientWidth || 1));
	}
	function goSlide(index: number) {
		if (!trackEl) return;
		const clamped = Math.max(0, Math.min(gallery.length - 1, index));
		trackEl.scrollTo({ left: clamped * trackEl.clientWidth, behavior: 'smooth' });
	}
	function openLightbox(index: number) {
		lightboxIndex = index;
		lightboxOpen = true;
	}
	function initialInteractionState() {
		return {
			liked: post.isLiked,
			bookmarked: post.isBookmarked,
			likesCount: post.likesCount
		};
	}
	let interaction = $state(initialInteractionState());
	let liking = $state(false);
	let bookmarking = $state(false);
	let interactionError = $state('');
	let likeBurst = $state(false);

	// Double-tap untuk like (global di semua media post: video via SmartVideo, gambar via dblclick).
	function doubleTapLike() {
		if (!interaction.liked) void toggleLike();
		likeBurst = true;
		setTimeout(() => (likeBurst = false), 850);
	}

	async function toggleLike() {
		if (liking) return;
		liking = true;
		interactionError = '';
		const previousLiked = interaction.liked;
		const previousCount = interaction.likesCount;
		const target = !interaction.liked;
		interaction.liked = target;
		interaction.likesCount += target ? 1 : -1;
		try {
			const result = await setPostLike(post.id, target);
			// Samakan dengan state server agar tidak desinkron (like "hilang" setelah refresh).
			interaction.liked = result.liked;
			if (result.likesCount !== null) interaction.likesCount = result.likesCount;
		} catch {
			interaction.liked = previousLiked;
			interaction.likesCount = previousCount;
			interactionError = 'Like belum tersimpan. Coba lagi.';
		} finally {
			liking = false;
		}
	}

	async function toggleBookmark() {
		if (bookmarking) return;
		bookmarking = true;
		interactionError = '';
		interaction.bookmarked = !interaction.bookmarked;
		try {
			await clientRequest(`bookmarks/${post.id}`, {
				method: interaction.bookmarked ? 'POST' : 'DELETE'
			});
		} catch {
			interaction.bookmarked = !interaction.bookmarked;
			interactionError = 'Perubahan simpan belum berhasil.';
		} finally {
			bookmarking = false;
		}
	}

	const shareUrl = $derived(
		typeof window !== 'undefined'
			? new URL(`/posts/${post.id}`, window.location.origin).toString()
			: `https://app.portalsi.com/posts/${post.id}`
	);
	function sharePost() {
		shareOpen = true;
	}

	onMount(() => {
		const href = `/posts/${post.id}`;
		const opening = (event: Event) => {
			const detail = (event as CustomEvent<{ href?: string }>).detail;
			if (detail?.href === href) openingPost = true;
		};
		const opened = (event: Event) => {
			const detail = (event as CustomEvent<{ href?: string }>).detail;
			if (!detail?.href || detail.href === href) openingPost = false;
		};
		window.addEventListener('portal:post-opening', opening);
		window.addEventListener('portal:post-opened', opened);
		return () => {
			window.removeEventListener('portal:post-opening', opening);
			window.removeEventListener('portal:post-opened', opened);
		};
	});
</script>

{#if moderatedHidden}
	<article class="post-card moderated-note" aria-label="Postingan dimoderasi">
		<ShieldAlert size={16} /> Postingan telah dimoderasi & diturunkan.
	</article>
{:else}
<article class="post-card" aria-labelledby={`post-${post.id}-author`}>
	<header>
		<div class="author">
			<div class="author-avatars">
				<StoryAvatarLink
					userId={post.user.id}
					username={post.user.username}
					name={post.user.fullName}
					avatarUrl={post.user.avatarUrl}
					size="md"
					hasStory={post.user.hasStory}
					seen={post.user.storyViewed}
				/>
				{#if post.coAuthors && post.coAuthors.length > 0}
					<button
						class="coauthor-stack"
						onclick={() => (collabPopupOpen = true)}
						aria-label="Lihat kolaborator"
					>
						{#each post.coAuthors.slice(0, 2) as ca (ca.id)}
							<Avatar name={ca.username} src={ca.avatarUrl} size="sm" />
						{/each}
						{#if post.coAuthors.length > 2}<span class="more">+{post.coAuthors.length - 2}</span>{/if}
					</button>
				{/if}
			</div>
			<div class="author-copy">
				<strong id={`post-${post.id}-author`}>
					<a href={`/u/${post.user.username}`}>{post.user.username}</a>
					<UserBadges verified={post.user.badgeVerified} role={post.user.role} />
					{#if post.coAuthors && post.coAuthors.length === 1}<span class="co-and"
							>dan <a href={`/u/${post.coAuthors[0].username}`}>{post.coAuthors[0].username}</a
							></span
						>{:else if post.coAuthors && post.coAuthors.length > 1}<button
							class="co-authors"
							onclick={() => (collabPopupOpen = true)}>dan {post.coAuthors.length} lainnya</button
						>{/if}
				</strong>
				<small>{post.createdLabel}</small>
			</div>
		</div>
		{#if canShowFollow}
			<button
				class="follow-btn"
				class:following
				onclick={toggleFollow}
				disabled={followBusy}
				aria-pressed={following}
				>{following ? 'Mengikuti' : post.user.followsYou ? 'Ikuti balik' : 'Ikuti'}</button
			>
		{/if}
		{#if $isModerator}
			<button
				class="mod-btn"
				onclick={() => (moderationOpen = true)}
				aria-label="Moderasi postingan"><ShieldAlert size={17} /></button
			>
		{/if}
	</header>

	{#if post.location || post.music}
		<div class="context-row">
			{#if post.location}<span><MapPin size={13} />{post.location}</span>{/if}
			{#if post.music}<ViewportMusic
					src={post.music.previewUrl}
					title={post.music.title}
					artist={post.music.artist}
					start={post.music.startSeconds}
					clipDuration={post.music.durationSeconds}
				/>{/if}
		</div>
	{/if}

	{#if post.isVideo}<div class="media" class:zoomable class:opening={openingPost}>
			<SmartVideo
				src={post.mediaUrl}
				sources={post.videoSources ?? []}
				poster={post.thumbnailUrl}
				label={post.mediaAlt}
				{autoplay}
				forceMuted={post.videoMuted || Boolean(post.music)}
				preferSound={preferSound || zoomable}
				onDoubleTap={doubleTapLike}
			/>
			{#if likeBurst}<span class="like-burst"><Heart size={96} fill="currentColor" /></span>{/if}
			{#if zoomable}<button
					class="expand-media"
					onclick={() => openLightbox(0)}
					aria-label="Perbesar video"><Maximize2 size={19} /></button
				>{/if}
			{#if openingPost}<span class="post-opening"><LoaderCircle size={28} /></span>{/if}
		</div>{:else if isGallery}<div class="media gallery">
			<div class="gallery-track" class:opening={openingPost} bind:this={trackEl} onscroll={onTrackScroll}>
				{#each gallery as src, index (index)}
					{#if zoomable}<button class="slide" onclick={() => openLightbox(index)}
							><img src={src} alt={`${post.mediaAlt} (${index + 1})`} /></button
						>{:else}<a class="slide" href={`/posts/${post.id}`}
							><img src={src} alt={`${post.mediaAlt} (${index + 1})`} /></a
						>{/if}
				{/each}
				{#if openingPost}<span class="post-opening"><LoaderCircle size={28} /></span>{/if}
			</div>
			<span class="counter">{slide + 1}/{gallery.length}</span>
			{#if slide > 0}<button
					class="nav prev"
					onclick={() => goSlide(slide - 1)}
					aria-label="Foto sebelumnya"><ChevronLeft size={20} /></button
				>{/if}
			{#if slide < gallery.length - 1}<button
					class="nav next"
					onclick={() => goSlide(slide + 1)}
					aria-label="Foto berikutnya"><ChevronRight size={20} /></button
				>{/if}
			<div class="dots">
				{#each gallery as _, index (index)}<span class:on={index === slide}></span>{/each}
			</div>
		</div>{:else if zoomable}<button
			class="media zoomable"
			onclick={() => openLightbox(0)}
			ondblclick={doubleTapLike}
			aria-label={`Perbesar postingan ${post.user.fullName}`}
			><img src={post.mediaUrl} alt={post.mediaAlt} /><span class="zoom-cue"
				><Maximize2 size={20} /> Perbesar</span
			>{#if likeBurst}<span class="like-burst"><Heart size={96} fill="currentColor" /></span>{/if}</button
		>{:else}<a
			class="media"
			class:opening={openingPost}
			href={`/posts/${post.id}`}
			aria-label={`Buka postingan ${post.user.fullName}`}
			><img src={post.mediaUrl} alt={post.mediaAlt} />{#if openingPost}<span class="post-opening"><LoaderCircle size={28} /></span>{/if}</a
		>{/if}

	<div class="actions">
		<div>
			<button
				class:active={interaction.liked}
				onclick={toggleLike}
				disabled={liking}
				aria-pressed={interaction.liked}
				aria-label={interaction.liked ? 'Batal menyukai' : 'Sukai'}
			>
				{#if liking}<LoaderCircle size={21} class="action-spin" />{:else}<Heart
						size={22}
						fill={interaction.liked ? 'currentColor' : 'none'}
					/>{/if}
			</button>
			<a href={`/posts/${post.id}#comments`} aria-label="Komentar"><MessageCircle size={22} /></a>
			<button onclick={sharePost} aria-label="Bagikan"><Send size={21} /></button>
		</div>
		<button
			class:active={interaction.bookmarked}
			onclick={toggleBookmark}
			disabled={bookmarking}
			aria-pressed={interaction.bookmarked}
			aria-label={interaction.bookmarked ? 'Hapus dari tersimpan' : 'Simpan'}
		>
			{#if bookmarking}<LoaderCircle size={21} class="action-spin" />{:else}<Bookmark
					size={22}
					fill={interaction.bookmarked ? 'currentColor' : 'none'}
				/>{/if}
		</button>
	</div>

	<div class="post-copy">
		{#if interactionError}<p class="interaction-status" aria-live="polite">
				{interactionError}
			</p>{/if}
		<p class="counts">
			<b>{interaction.likesCount.toLocaleString('id-ID')}</b> suka ·
			<a href={`/posts/${post.id}#comments`}>{post.commentsCount} komentar</a>
		</p>
		<p class="caption">
			<a href={`/u/${post.user.username}`}>{post.user.username}</a>
			<MentionText text={post.caption} />
		</p>
		<a class="comments" href={`/posts/${post.id}#comments`}>Lihat percakapan</a>
	</div>
</article>
{/if}

<MediaLightbox
	open={lightboxOpen}
	src={post.isVideo ? post.mediaUrl : (gallery[lightboxIndex] ?? post.mediaUrl)}
	alt={post.mediaAlt}
	isVideo={post.isVideo}
	poster={post.thumbnailUrl}
	onClose={() => (lightboxOpen = false)}
/>

{#if moderationOpen}
	<ModerationModal
		postId={post.id}
		onClose={() => (moderationOpen = false)}
		onDone={() => (moderatedHidden = true)}
	/>
{/if}

{#if shareOpen}
	<SharePostSheet postId={post.id} {shareUrl} onClose={() => (shareOpen = false)} />
{/if}

{#if collabPopupOpen && post.coAuthors && post.coAuthors.length > 0}
	<div class="collab-modal-scrim" role="presentation" onclick={() => (collabPopupOpen = false)}>
		<div
			class="collab-modal"
			role="dialog"
			aria-modal="true"
			aria-label="Kolaborator"
			onclick={(e) => e.stopPropagation()}
		>
			<header>
				<strong>Kolaborasi</strong>
				<button onclick={() => (collabPopupOpen = false)} aria-label="Tutup"><X size={19} /></button>
			</header>
			<ul>
				<li>
					<a href={`/u/${post.user.username}`}>
						<Avatar name={post.user.fullName} src={post.user.avatarUrl} size="md" />
						<span
							><strong>@{post.user.username}</strong><small>Pembuat</small></span
						><UserBadges verified={post.user.badgeVerified} role={post.user.role} />
					</a>
				</li>
				{#each post.coAuthors as ca (ca.id)}
					<li>
						<a href={`/u/${ca.username}`}>
							<Avatar name={ca.fullName} src={ca.avatarUrl} size="md" />
							<span><strong>@{ca.username}</strong><small>Kolaborator</small></span>
							{#if ca.verified}<UserBadges verified={true} role="other" />{/if}
						</a>
					</li>
				{/each}
			</ul>
		</div>
	</div>
{/if}

<style>
	.post-card {
		overflow: hidden;
		background: var(--color-surface);
		border: 1px solid var(--color-border);
		border-radius: var(--radius-lg);
		box-shadow: var(--shadow-xs);
	}

	header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 14px 15px 11px;
	}

	.author {
		display: flex;
		min-width: 0;
		align-items: center;
		gap: 10px;
	}

	.author-copy {
		display: grid;
		min-width: 0;
	}

	.author strong {
		display: flex;
		align-items: center;
		gap: 4px;
		font-size: 0.91rem;
	}

	.author small {
		color: var(--color-muted);
		font-size: 0.76rem;
	}
	.author-copy a {
		color: inherit;
	}
	.author-avatars {
		display: flex;
		align-items: center;
	}
	.coauthor-stack {
		display: inline-flex;
		align-items: center;
		margin-left: -10px;
		padding: 0;
		background: none;
		border: 0;
		cursor: pointer;
	}
	.coauthor-stack :global(.avatar-wrap) {
		margin-left: -8px;
		box-shadow: 0 0 0 2px var(--color-surface, #fff);
		border-radius: 50%;
	}
	.coauthor-stack .more {
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
	.co-and {
		font-weight: 700;
	}
	.co-and a {
		color: inherit;
	}
	.co-authors {
		background: none;
		border: 0;
		padding: 0;
		color: inherit;
		font-weight: 700;
		cursor: pointer;
	}
	.co-authors:hover {
		text-decoration: underline;
	}

	.collab-modal-scrim {
		position: fixed;
		inset: 0;
		z-index: 80;
		display: grid;
		place-items: center;
		padding: 20px;
		background: rgb(0 0 0 / 45%);
		animation: cm-fade 0.15s ease;
	}
	.collab-modal {
		width: min(100%, 380px);
		max-height: 70vh;
		overflow: hidden;
		display: flex;
		flex-direction: column;
		background: var(--color-surface, #fff);
		border-radius: 16px;
		animation: cm-pop 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
	}
	.collab-modal header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 14px 16px;
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
	.collab-modal li a:hover {
		background: var(--color-surface-soft, #f4f5f7);
	}
	.collab-modal li span {
		display: grid;
		flex: 1;
		min-width: 0;
	}
	.collab-modal li strong {
		font-size: 0.9rem;
	}
	.collab-modal li small {
		color: var(--color-muted);
		font-size: 0.72rem;
	}
	@keyframes cm-fade {
		from {
			opacity: 0;
		}
	}
	@keyframes cm-pop {
		from {
			opacity: 0;
			transform: scale(0.94);
		}
	}

	.mod-btn {
		display: grid;
		width: 34px;
		height: 34px;
		flex: none;
		place-items: center;
		padding: 0;
		background: rgb(192 57 43 / 8%);
		border: 1px solid rgb(192 57 43 / 28%);
		border-radius: 9px;
		color: #c0392b;
		cursor: pointer;
	}
	.mod-btn:hover {
		background: rgb(192 57 43 / 15%);
	}
	.moderated-note {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 16px 18px;
		color: var(--color-muted);
		font-size: 0.82rem;
	}
	.follow-btn {
		flex: 0 0 auto;
		padding: 7px 18px;
		background: var(--color-primary);
		border: 1px solid var(--color-primary);
		border-radius: 999px;
		color: white;
		font-size: 0.76rem;
		font-weight: 720;
		cursor: pointer;
		transition: all 140ms ease;
	}
	.follow-btn.following {
		background: transparent;
		color: var(--color-muted);
		border-color: var(--color-border-strong);
	}
	.follow-btn:disabled {
		opacity: 0.6;
	}

	.actions button,
	.actions a {
		display: grid;
		width: 42px;
		height: 42px;
		place-items: center;
		padding: 0;
		background: transparent;
		border: 0;
		border-radius: 50%;
		cursor: pointer;
	}

	.actions button:hover,
	.actions a:hover {
		background: var(--color-primary-soft);
	}

	.context-row {
		display: flex;
		gap: 12px;
		padding: 0 16px 10px 69px;
		overflow: hidden;
		color: var(--color-muted);
		font-size: 0.72rem;
	}

	.context-row span {
		display: flex;
		min-width: 0;
		align-items: center;
		gap: 4px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.media {
		position: relative;
		display: block;
		width: 100%;
		padding: 0;
		overflow: hidden;
		background: var(--color-canvas-deep);
		border: 0;
	}
	.media.zoomable {
		cursor: zoom-in;
	}
	.media.zoomable img {
		transition: transform 220ms ease;
	}
	.media.zoomable:hover img {
		transform: scale(1.018);
	}
	.media.opening,
	.gallery-track.opening {
		pointer-events: none;
	}
	.media.opening::before,
	.gallery-track.opening::before {
		position: absolute;
		z-index: 4;
		inset: 0;
		background: rgb(11 8 6 / 42%);
		backdrop-filter: blur(1px);
		content: '';
	}
	.post-opening {
		position: absolute;
		z-index: 5;
		inset: 0;
		display: grid;
		place-items: center;
		color: white;
		pointer-events: none;
	}
	.like-burst {
		position: absolute;
		top: 50%;
		left: 50%;
		z-index: 6;
		color: #ff2d55;
		transform: translate(-50%, -50%) scale(0.4);
		animation: pc-burst 0.85s ease forwards;
		pointer-events: none;
		filter: drop-shadow(0 4px 14px rgb(0 0 0 / 35%));
	}
	@keyframes pc-burst {
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
	.post-opening :global(svg) {
		filter: drop-shadow(0 4px 12px rgb(0 0 0 / 45%));
		animation: post-open-spin 0.75s linear infinite;
	}
	@keyframes post-open-spin {
		to {
			transform: rotate(360deg);
		}
	}
	.expand-media,
	.zoom-cue {
		position: absolute;
		z-index: 3;
		right: 14px;
		bottom: 14px;
		display: flex;
		align-items: center;
		gap: 6px;
		padding: 9px 11px;
		background: rgb(0 0 0 / 58%);
		border: 1px solid rgb(255 255 255 / 20%);
		border-radius: 999px;
		color: white;
		font-size: 0.68rem;
		font-weight: 700;
		backdrop-filter: blur(8px);
	}
	.expand-media {
		width: 42px;
		height: 42px;
		justify-content: center;
		padding: 0;
	}
	.zoom-cue {
		opacity: 0;
		transform: translateY(5px);
		transition: 170ms ease;
	}
	.media.zoomable:hover .zoom-cue,
	.media.zoomable:focus-visible .zoom-cue {
		opacity: 1;
		transform: translateY(0);
	}
	/* Desktop: tanpa opsi perbesar/fullscreen di detail (mobile tetap). */
	@media (min-width: 900px) {
		.expand-media,
		.zoom-cue {
			display: none;
		}
	}

	.media img {
		display: block;
		width: 100%;
		max-height: 82vh;
		object-fit: contain;
	}

	/* Galeri multi-foto */
	.gallery {
		position: relative;
	}
	.gallery-track {
		position: relative;
		display: flex;
		overflow-x: auto;
		scroll-snap-type: x mandatory;
		scrollbar-width: none;
		-webkit-overflow-scrolling: touch;
	}
	.gallery-track::-webkit-scrollbar {
		display: none;
	}
	.gallery .slide {
		flex: 0 0 100%;
		scroll-snap-align: center;
		padding: 0;
		border: 0;
		background: var(--color-canvas-deep);
		cursor: pointer;
	}
	.gallery .slide img {
		max-height: 82vh;
	}
	.gallery .counter {
		position: absolute;
		top: 12px;
		right: 12px;
		z-index: 3;
		padding: 4px 10px;
		background: rgb(0 0 0 / 60%);
		border-radius: 999px;
		color: white;
		font-size: 0.68rem;
		font-weight: 700;
		backdrop-filter: blur(6px);
	}
	.gallery .nav {
		position: absolute;
		top: 50%;
		z-index: 3;
		display: grid;
		width: 32px;
		height: 32px;
		place-items: center;
		padding: 0;
		background: rgb(255 255 255 / 82%);
		border: 0;
		border-radius: 50%;
		color: #1d1915;
		transform: translateY(-50%);
		box-shadow: 0 2px 8px rgb(0 0 0 / 22%);
	}
	.gallery .nav.prev {
		left: 10px;
	}
	.gallery .nav.next {
		right: 10px;
	}
	.gallery .dots {
		position: absolute;
		bottom: 12px;
		left: 0;
		right: 0;
		z-index: 3;
		display: flex;
		justify-content: center;
		gap: 5px;
		pointer-events: none;
	}
	.gallery .dots span {
		width: 6px;
		height: 6px;
		border-radius: 50%;
		background: rgb(255 255 255 / 55%);
		box-shadow: 0 0 3px rgb(0 0 0 / 40%);
		transition: background 160ms ease;
	}
	.gallery .dots span.on {
		background: white;
	}
	@media (hover: none) {
		.gallery .nav {
			display: none;
		}
	}

	.actions,
	.actions > div {
		display: flex;
		align-items: center;
	}

	.actions {
		justify-content: space-between;
		padding: 7px 9px 0;
	}

	.actions .active {
		color: var(--color-primary-strong);
	}

	.actions button:disabled {
		cursor: wait;
		opacity: 0.6;
	}
	:global(.action-spin) {
		animation: post-open-spin 0.75s linear infinite;
	}

	.post-copy {
		padding: 0 16px 16px;
	}

	.post-copy p {
		margin-bottom: 6px;
	}

	.post-copy .interaction-status {
		margin-top: 2px;
		color: var(--color-primary-strong);
		font-size: 0.75rem;
	}

	.counts {
		font-size: 0.81rem;
	}

	.caption {
		font-size: 0.91rem;
		line-height: 1.5;
		/* Pertahankan baris baru & spasi seperti yang diketik user (paragraf). */
		white-space: pre-wrap;
		overflow-wrap: anywhere;
	}

	.caption a {
		font-weight: 720;
	}

	.comments {
		color: var(--color-muted);
		font-size: 0.82rem;
		font-weight: 580;
	}

	@media (max-width: 767px) {
		.post-card {
			border-inline: 0;
			border-radius: 0;
		}
	}
</style>
