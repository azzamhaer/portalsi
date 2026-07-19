<script lang="ts">
	import { env } from '$env/dynamic/public';
	import { untrack } from 'svelte';
	import {
		Bookmark,
		ChevronDown,
		ChevronUp,
		Heart,
		MessageCircle,
		Music2,
		Send
	} from '@lucide/svelte';
	import { clientRequest } from '$lib/api/client';
	import { mapPost } from '$lib/api/mappers';
	import { reelsFeedSchema } from '$lib/schemas/post';
	import SmartVideo from '$lib/components/media/SmartVideo.svelte';
	import CommentsSheet from '$lib/components/reels/CommentsSheet.svelte';
	import BackButton from '$lib/components/ui/BackButton.svelte';
	import Avatar from '$lib/components/ui/Avatar.svelte';
	import UserBadges from '$lib/components/ui/UserBadges.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
	const mediaBaseUrl = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';

	let reels = $state([...data.reels]);
	let page = $state(untrack(() => data.page));
	let lastPage = $state(untrack(() => data.lastPage));
	let loadingMore = $state(false);
	let activeIndex = $state(0);
	let commentsFor = $state<number | null>(null);
	let burstId = $state<number | null>(null);

	let itemEls: HTMLElement[] = [];

	function nf(n: number) {
		return n >= 1000 ? `${(n / 1000).toFixed(n >= 10_000 ? 0 : 1)}rb` : String(n);
	}

	async function toggleLike(reel: (typeof reels)[number]) {
		const was = reel.isLiked;
		reel.isLiked = !was;
		reel.likesCount += was ? -1 : 1;
		try {
			await clientRequest(`posts/${reel.id}/like`, { method: 'POST' });
		} catch {
			reel.isLiked = was;
			reel.likesCount += was ? 1 : -1;
		}
	}

	function doubleTapLike(reel: (typeof reels)[number]) {
		if (!reel.isLiked) void toggleLike(reel);
		burstId = reel.id;
		setTimeout(() => {
			if (burstId === reel.id) burstId = null;
		}, 850);
	}

	async function toggleSave(reel: (typeof reels)[number]) {
		const was = reel.isBookmarked;
		reel.isBookmarked = !was;
		try {
			await clientRequest(`bookmarks/${reel.id}`, { method: was ? 'DELETE' : 'POST' });
		} catch {
			reel.isBookmarked = was;
		}
	}

	async function share(reel: (typeof reels)[number]) {
		const url = new URL(`/posts/${reel.id}`, location.origin).toString();
		try {
			if (navigator.share) await navigator.share({ url, title: 'Reel Portal SI' });
			else await navigator.clipboard.writeText(url);
		} catch {
			/* dibatalkan */
		}
	}

	function scrollToIndex(i: number) {
		const t = Math.max(0, Math.min(reels.length - 1, i));
		itemEls[t]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	async function maybeLoadMore() {
		if (loadingMore || page >= lastPage) return;
		if (activeIndex < reels.length - 2) return;
		loadingMore = true;
		try {
			const res = await clientRequest(`reels?page=${page + 1}`, { schema: reelsFeedSchema });
			const known = new Set(reels.map((r) => r.id));
			const mapped = res.data.map((p) => mapPost(p, mediaBaseUrl)).filter((m) => !known.has(m.id));
			reels.push(...mapped);
			page = res.pagination.current_page;
			lastPage = res.pagination.last_page;
		} catch {
			/* diamkan */
		} finally {
			loadingMore = false;
		}
	}

	// Observer untuk melacak reel aktif (autoplay ditangani SmartVideo sendiri).
	function track(node: HTMLElement, index: number) {
		itemEls[index] = node;
		const io = new IntersectionObserver(
			([entry]) => {
				if (entry.isIntersecting && entry.intersectionRatio >= 0.6) {
					activeIndex = index;
					void maybeLoadMore();
				}
			},
			{ threshold: [0.6] }
		);
		io.observe(node);
		return { destroy: () => io.disconnect() };
	}

	$effect(() => {
		function onKey(e: KeyboardEvent) {
			if (commentsFor !== null) return;
			if (e.key === 'ArrowDown' || e.key === 'PageDown') {
				e.preventDefault();
				scrollToIndex(activeIndex + 1);
			} else if (e.key === 'ArrowUp' || e.key === 'PageUp') {
				e.preventDefault();
				scrollToIndex(activeIndex - 1);
			}
		}
		window.addEventListener('keydown', onKey);
		return () => window.removeEventListener('keydown', onKey);
	});
</script>

<svelte:head><title>Reels — Portal SI</title><meta name="robots" content="noindex" /></svelte:head>

<div class="reels-page">
	<div class="reels-topbar"><BackButton /></div>

	{#if reels.length === 0}
		<div class="reels-empty">Belum ada video untuk ditampilkan.</div>
	{:else}
		<div class="reels-viewport">
			{#each reels as reel, i (reel.id)}
				<section class="reel" use:track={i}>
					<div class="reel-stage">
						<div class="reel-video">
							<SmartVideo
								src={reel.mediaUrl}
								sources={reel.videoSources ?? []}
								poster={reel.thumbnailUrl}
								label={reel.mediaAlt}
								fill
								autoplay
								preferSound
								minimal
								onDoubleTap={() => doubleTapLike(reel)}
							/>
						</div>

						{#if burstId === reel.id}
							<span class="like-burst"><Heart size={110} fill="currentColor" /></span>
						{/if}

						<!-- Meta kiri bawah -->
						<div class="reel-meta">
							<a class="reel-author" href={`/u/${reel.user.username}`}>
								<Avatar
									name={reel.user.fullName}
									src={reel.user.avatarUrl ?? undefined}
									size="sm"
								/>
								<strong>{reel.user.username}</strong>
								<UserBadges verified={reel.user.badgeVerified} role={reel.user.role} />
							</a>
							{#if reel.caption}<p class="reel-caption">{reel.caption}</p>{/if}
							{#if reel.music}
								<button class="reel-music" title={`${reel.music.title} · ${reel.music.artist}`}>
									<Music2 size={13} />
									<span>{reel.music.title} · {reel.music.artist}</span>
								</button>
							{/if}
						</div>

						<!-- Aksi kanan (di dalam post → responsif mobile & desktop) -->
						<div class="reel-actions">
							<button class:on={reel.isLiked} onclick={() => toggleLike(reel)} aria-label="Suka">
								<Heart size={27} fill={reel.isLiked ? 'currentColor' : 'none'} />
								<b>{nf(reel.likesCount)}</b>
							</button>
							<button onclick={() => (commentsFor = reel.id)} aria-label="Komentar">
								<MessageCircle size={27} />
								<b>{nf(reel.commentsCount)}</b>
							</button>
							<button onclick={() => share(reel)} aria-label="Bagikan">
								<Send size={25} />
							</button>
							<button
								class:on={reel.isBookmarked}
								onclick={() => toggleSave(reel)}
								aria-label="Simpan"
							>
								<Bookmark size={25} fill={reel.isBookmarked ? 'currentColor' : 'none'} />
							</button>
						</div>
					</div>
				</section>
			{/each}
		</div>

		<!-- Navigasi atas/bawah (desktop) -->
		<div class="reels-nav">
			<button onclick={() => scrollToIndex(activeIndex - 1)} aria-label="Reel sebelumnya" disabled={activeIndex === 0}>
				<ChevronUp size={22} />
			</button>
			<button
				onclick={() => scrollToIndex(activeIndex + 1)}
				aria-label="Reel berikutnya"
				disabled={activeIndex >= reels.length - 1 && page >= lastPage}
			>
				<ChevronDown size={22} />
			</button>
		</div>
	{/if}
</div>

{#if commentsFor !== null}
	{@const reel = reels.find((r) => r.id === commentsFor)}
	<CommentsSheet
		postId={commentsFor}
		onClose={() => (commentsFor = null)}
		onPosted={(n) => {
			if (reel) reel.commentsCount = n;
		}}
	/>
{/if}

<style>
	.reels-page {
		position: fixed;
		inset: 0;
		z-index: 30;
		background: #000;
		color: #fff;
	}
	.reels-topbar {
		position: absolute;
		top: 14px;
		left: 14px;
		z-index: 5;
	}
	.reels-topbar :global(a),
	.reels-topbar :global(button) {
		color: #fff;
		background: rgb(0 0 0 / 40%);
		border-radius: 50%;
		backdrop-filter: blur(6px);
	}
	.reels-empty {
		display: grid;
		height: 100%;
		place-items: center;
		color: rgb(255 255 255 / 70%);
		font-size: 0.9rem;
	}
	.reels-viewport {
		height: 100%;
		overflow-y: auto;
		scroll-snap-type: y mandatory;
		scrollbar-width: none;
	}
	.reels-viewport::-webkit-scrollbar {
		display: none;
	}
	.reel {
		display: grid;
		place-items: center;
		height: 100%;
		scroll-snap-align: start;
		scroll-snap-stop: always;
	}
	.reel-stage {
		position: relative;
		width: 100%;
		height: 100%;
		overflow: hidden;
	}
	.reel-video {
		display: block;
		width: 100%;
		height: 100%;
		padding: 0;
		background: #000;
		border: 0;
		cursor: pointer;
	}
	.like-burst {
		position: absolute;
		top: 50%;
		left: 50%;
		color: #fff;
		transform: translate(-50%, -50%) scale(0.4);
		animation: burst 0.85s ease forwards;
		pointer-events: none;
		filter: drop-shadow(0 4px 14px rgb(0 0 0 / 40%));
	}
	@keyframes burst {
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
	.reel-meta {
		position: absolute;
		bottom: 18px;
		left: 16px;
		right: 84px;
		display: grid;
		gap: 8px;
		z-index: 3;
		text-shadow: 0 1px 6px rgb(0 0 0 / 55%);
	}
	.reel-author {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		color: #fff;
		font-weight: 700;
		width: fit-content;
	}
	.reel-author strong {
		font-size: 0.9rem;
	}
	.reel-caption {
		margin: 0;
		max-width: 34rem;
		font-size: 0.85rem;
		line-height: 1.4;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}
	.reel-music {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		max-width: 100%;
		padding: 5px 11px;
		background: rgb(0 0 0 / 38%);
		border: 1px solid rgb(255 255 255 / 16%);
		border-radius: 999px;
		color: #fff;
		font-size: 0.74rem;
		width: fit-content;
		cursor: pointer;
		backdrop-filter: blur(6px);
	}
	.reel-music span {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.reel-actions {
		position: absolute;
		right: 12px;
		bottom: 22px;
		display: grid;
		justify-items: center;
		gap: 18px;
		z-index: 3;
	}
	.reel-actions button {
		display: grid;
		justify-items: center;
		gap: 3px;
		background: none;
		border: 0;
		color: #fff;
		font-size: 0.68rem;
		font-weight: 700;
		cursor: pointer;
		filter: drop-shadow(0 2px 6px rgb(0 0 0 / 45%));
	}
	.reel-actions button.on {
		color: #ff2d55;
	}
	.reel-actions button.on :global(svg) {
		color: #ff2d55;
	}
	.reels-nav {
		position: absolute;
		right: 18px;
		top: 50%;
		display: none;
		flex-direction: column;
		gap: 12px;
		transform: translateY(-50%);
		z-index: 4;
	}
	.reels-nav button {
		display: grid;
		width: 46px;
		height: 46px;
		place-items: center;
		background: rgb(30 32 36 / 82%);
		border: 1px solid rgb(255 255 255 / 12%);
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
	}
	.reels-nav button:disabled {
		opacity: 0.35;
		cursor: default;
	}

	/* ---- Desktop: video portrait di tengah, nav bulat kanan, aksi di kanan video ---- */
	@media (min-width: 900px) {
		.reels-page {
			left: var(--sidebar-width, 240px);
		}
		.reel-stage {
			width: auto;
			height: min(90vh, calc(9 / 16 * 1600px));
			aspect-ratio: 9 / 16;
			border-radius: 14px;
		}
		.reel-video {
			border-radius: 14px;
		}
		.reels-nav {
			display: flex;
		}
	}
</style>
