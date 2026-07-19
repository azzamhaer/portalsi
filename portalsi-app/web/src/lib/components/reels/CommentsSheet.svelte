<script lang="ts">
	import { env } from '$env/dynamic/public';
	import { Heart, LoaderCircle, Send, X } from '@lucide/svelte';
	import { untrack } from 'svelte';
	import { clientRequest } from '$lib/api/client';
	import Avatar from '$lib/components/ui/Avatar.svelte';
	import GifPicker from '$lib/components/comment/GifPicker.svelte';
	import { commentsResponseSchema, createdCommentResponseSchema } from '$lib/schemas/comment';
	import { normalizeMediaUrl } from '$lib/utils/media';
	import { relativeTimeId } from '$lib/utils/time';

	let {
		postId,
		onClose,
		onPosted
	}: { postId: number; onClose: () => void; onPosted?: (count: number) => void } = $props();

	const mediaBaseUrl = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';

	type CommentView = {
		id: number;
		name: string;
		username: string;
		avatarUrl?: string;
		text: string;
		gifUrl?: string;
		createdLabel: string;
		likesCount: number;
		isLiked: boolean;
		repliesCount: number;
	};

	let loading = $state(true);
	let comments = $state<CommentView[]>([]);
	let draft = $state('');
	let sending = $state(false);
	let gifOpen = $state(false);
	let currentPost = $state(untrack(() => postId));

	function mapUser(user: {
		full_name?: string | null;
		username: string;
		profile_picture_url?: string | null;
	}) {
		return {
			name: user.full_name?.trim() || user.username,
			username: user.username,
			avatarUrl: normalizeMediaUrl(user.profile_picture_url, mediaBaseUrl) ?? undefined
		};
	}

	async function loadComments(id: number) {
		loading = true;
		try {
			const response = await clientRequest(`posts/${id}/comments`, {
				schema: commentsResponseSchema
			});
			comments = response.comments.map((c) => ({
				id: c.comment_id,
				...mapUser(c.user),
				text: c.content,
				gifUrl: c.gif_url ?? undefined,
				createdLabel: relativeTimeId(c.created_at),
				likesCount: c.likes_count,
				isLiked: c.is_liked,
				repliesCount: c.replies.length
			}));
		} catch {
			comments = [];
		} finally {
			loading = false;
		}
	}

	// Muat ulang bila postId berubah (reel berganti).
	$effect(() => {
		if (postId !== currentPost) currentPost = postId;
		void loadComments(postId);
	});

	async function send(gifUrl: string | null = null) {
		const text = draft.trim();
		if ((!text && !gifUrl) || sending) return;
		sending = true;
		gifOpen = false;
		try {
			const response = await clientRequest(`posts/${postId}/comments`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ content: text, gif_url: gifUrl, parent_comment_id: null }),
				schema: createdCommentResponseSchema
			});
			comments.unshift({
				id: response.data.comment_id,
				...mapUser(response.data.user),
				text: response.data.content,
				gifUrl: response.data.gif_url ?? gifUrl ?? undefined,
				createdLabel: 'baru saja',
				likesCount: 0,
				isLiked: false,
				repliesCount: 0
			});
			draft = '';
			onPosted?.(comments.length);
		} catch {
			/* diamkan; user bisa coba lagi */
		} finally {
			sending = false;
		}
	}

	async function toggleLike(item: CommentView) {
		const wasLiked = item.isLiked;
		item.isLiked = !wasLiked;
		item.likesCount += wasLiked ? -1 : 1;
		try {
			await clientRequest(`comments/${item.id}/like`, { method: wasLiked ? 'DELETE' : 'POST' });
		} catch {
			item.isLiked = wasLiked;
			item.likesCount += wasLiked ? 1 : -1;
		}
	}
</script>

<div class="comments-scrim" role="presentation" onclick={onClose}>
	<section
		class="comments-sheet"
		role="dialog"
		aria-modal="true"
		aria-label="Komentar"
		onclick={(e) => e.stopPropagation()}
	>
		<header>
			<strong>Komentar</strong>
			<button onclick={onClose} aria-label="Tutup komentar"><X size={20} /></button>
		</header>

		<div class="comments-list">
			{#if loading}
				<div class="c-state"><LoaderCircle class="spin" size={22} /></div>
			{:else if comments.length === 0}
				<div class="c-state">Belum ada komentar. Jadilah yang pertama!</div>
			{:else}
				{#each comments as item (item.id)}
					<article class="c-item">
						<Avatar name={item.name} src={item.avatarUrl} size="sm" />
						<div class="c-body">
							<p>
								<a href={`/u/${item.username}`}>{item.username}</a>
								{#if item.text}<span>{item.text}</span>{/if}
							</p>
							{#if item.gifUrl}<img class="c-gif" src={item.gifUrl} alt="GIF" />{/if}
							<small
								>{item.createdLabel}{#if item.repliesCount > 0} · {item.repliesCount} balasan{/if}</small
							>
						</div>
						<button
							class="c-like"
							class:liked={item.isLiked}
							onclick={() => toggleLike(item)}
							aria-label={item.isLiked ? 'Batal suka' : 'Suka'}
						>
							<Heart size={15} fill={item.isLiked ? 'currentColor' : 'none'} />
							{#if item.likesCount > 0}<b>{item.likesCount}</b>{/if}
						</button>
					</article>
				{/each}
			{/if}
		</div>

		<form
			class="comments-input"
			onsubmit={(e) => {
				e.preventDefault();
				void send();
			}}
		>
			<button
				type="button"
				class="gif-btn"
				onclick={() => (gifOpen = true)}
				aria-label="Kirim GIF">GIF</button
			>
			<input
				bind:value={draft}
				maxlength="500"
				placeholder="Tambahkan komentar…"
				aria-label="Tulis komentar"
			/>
			<button type="submit" disabled={!draft.trim() || sending} aria-label="Kirim komentar">
				{#if sending}<LoaderCircle class="spin" size={18} />{:else}<Send size={18} />{/if}
			</button>
		</form>
	</section>
</div>

{#if gifOpen}
	<GifPicker onSelect={(url) => void send(url)} onClose={() => (gifOpen = false)} />
{/if}

<style>
	.comments-scrim {
		position: fixed;
		inset: 0;
		z-index: 80;
		display: flex;
		align-items: flex-end;
		justify-content: center;
		background: rgb(0 0 0 / 45%);
		animation: c-fade 0.15s ease;
	}
	.comments-sheet {
		display: flex;
		width: min(100%, 460px);
		height: min(74vh, 640px);
		flex-direction: column;
		background: var(--color-surface, #fff);
		border-radius: 18px 18px 0 0;
		overflow: hidden;
		animation: c-up 0.22s cubic-bezier(0.22, 1, 0.36, 1);
	}
	header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 13px 16px;
		border-bottom: 1px solid var(--color-border);
	}
	header strong {
		font-size: 0.95rem;
	}
	header button,
	.c-like {
		display: inline-flex;
		align-items: center;
		gap: 3px;
		background: none;
		border: 0;
		color: var(--color-muted);
		cursor: pointer;
	}
	.comments-list {
		flex: 1;
		overflow-y: auto;
		padding: 6px 14px;
	}
	.c-state {
		display: grid;
		place-items: center;
		padding: 40px 10px;
		color: var(--color-muted);
		font-size: 0.82rem;
		text-align: center;
	}
	.c-item {
		display: flex;
		gap: 10px;
		padding: 10px 0;
		align-items: flex-start;
	}
	.c-body {
		flex: 1;
		min-width: 0;
	}
	.c-body p {
		margin: 0;
		font-size: 0.84rem;
		line-height: 1.35;
	}
	.c-body a {
		font-weight: 700;
		color: var(--color-text);
		margin-right: 5px;
	}
	.c-body small {
		color: var(--color-muted);
		font-size: 0.68rem;
	}
	.c-like {
		flex-direction: column;
		font-size: 0.64rem;
		padding-top: 3px;
	}
	.c-like.liked {
		color: var(--color-danger, #e0245e);
	}
	.comments-input {
		display: flex;
		gap: 8px;
		align-items: center;
		padding: 10px 14px;
		border-top: 1px solid var(--color-border);
	}
	.comments-input input {
		flex: 1;
		min-height: 40px;
		padding: 0 13px;
		background: var(--color-surface-soft, #f2f3f5);
		border: 1px solid var(--color-border);
		border-radius: 999px;
		font-size: 0.85rem;
	}
	.gif-btn {
		flex: none;
		height: 40px;
		padding: 0 11px;
		background: var(--color-surface-soft, #f2f3f5);
		border: 1px solid var(--color-border);
		border-radius: 999px;
		font-size: 0.72rem;
		font-weight: 800;
		color: var(--color-primary-strong, #c96a10);
		cursor: pointer;
	}
	.c-gif {
		margin-top: 6px;
		max-width: 160px;
		max-height: 160px;
		border-radius: 10px;
	}
	.comments-input button {
		display: grid;
		width: 40px;
		height: 40px;
		flex: none;
		place-items: center;
		background: var(--color-primary);
		border: 0;
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
	}
	.comments-input button:disabled {
		opacity: 0.5;
	}
	:global(.spin) {
		animation: c-spin 0.8s linear infinite;
	}
	@keyframes c-spin {
		to {
			transform: rotate(360deg);
		}
	}
	@keyframes c-fade {
		from {
			opacity: 0;
		}
	}
	@keyframes c-up {
		from {
			transform: translateY(100%);
		}
	}
	/* Desktop: panel mengambang di kanan, bukan bottom sheet. */
	@media (min-width: 768px) {
		.comments-scrim {
			align-items: center;
		}
		.comments-sheet {
			height: min(80vh, 680px);
			border-radius: 18px;
		}
	}
</style>
