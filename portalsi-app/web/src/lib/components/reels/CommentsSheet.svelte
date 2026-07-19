<script lang="ts">
	import { env } from '$env/dynamic/public';
	import { CornerDownRight, Heart, LoaderCircle, Send, X } from '@lucide/svelte';
	import { untrack } from 'svelte';
	import { clientRequest } from '$lib/api/client';
	import Avatar from '$lib/components/ui/Avatar.svelte';
	import GifPicker from '$lib/components/comment/GifPicker.svelte';
	import MentionText from '$lib/components/ui/MentionText.svelte';
	import { confirmAction } from '$lib/ui/confirm';
	import { commentsResponseSchema, createdCommentResponseSchema } from '$lib/schemas/comment';
	import { normalizeMediaUrl } from '$lib/utils/media';
	import { relativeTimeId } from '$lib/utils/time';

	let {
		postId,
		currentUserId = null,
		onClose,
		onPosted
	}: {
		postId: number;
		currentUserId?: number | null;
		onClose: () => void;
		onPosted?: (count: number) => void;
	} = $props();

	const mediaBaseUrl = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';

	type Node = {
		id: number;
		userId: number;
		name: string;
		username: string;
		avatarUrl?: string;
		text: string;
		gifUrl?: string;
		createdLabel: string;
		likesCount: number;
		isLiked: boolean;
	};
	type CommentView = Node & { replies: Node[]; showReplies: boolean };

	let loading = $state(true);
	let comments = $state<CommentView[]>([]);
	let totalCount = $state(0);
	let draft = $state('');
	let sending = $state(false);
	let gifOpen = $state(false);
	let replyTo = $state<{ id: number; name: string } | null>(null);
	let editing = $state<{ id: number; reply: boolean } | null>(null);
	let currentPost = $state(untrack(() => postId));
	let inputEl = $state<HTMLInputElement>();

	function mapNode(c: {
		comment_id: number;
		user_id?: number;
		user: { user_id?: number; full_name?: string | null; username: string; profile_picture_url?: string | null };
		content: string;
		gif_url?: string | null;
		created_at: string;
		likes_count: number;
		is_liked: boolean;
	}): Node {
		return {
			id: c.comment_id,
			userId: c.user.user_id ?? c.user_id ?? 0,
			name: c.user.full_name?.trim() || c.user.username,
			username: c.user.username,
			avatarUrl: normalizeMediaUrl(c.user.profile_picture_url, mediaBaseUrl) ?? undefined,
			text: c.content,
			gifUrl: c.gif_url ?? undefined,
			createdLabel: relativeTimeId(c.created_at),
			likesCount: c.likes_count,
			isLiked: c.is_liked
		};
	}

	function recount() {
		totalCount = comments.reduce((sum, c) => sum + 1 + c.replies.length, 0);
		onPosted?.(totalCount);
	}

	async function loadComments(id: number) {
		loading = true;
		try {
			const response = await clientRequest(`posts/${id}/comments`, { schema: commentsResponseSchema });
			comments = response.comments.map((c) => ({
				...mapNode(c),
				replies: c.replies.map(mapNode),
				showReplies: false
			}));
			totalCount = comments.reduce((sum, c) => sum + 1 + c.replies.length, 0);
		} catch {
			comments = [];
		} finally {
			loading = false;
		}
	}

	$effect(() => {
		if (postId !== currentPost) currentPost = postId;
		void loadComments(postId);
	});

	function startReply(parentId: number, name: string, mentionUsername?: string) {
		replyTo = { id: parentId, name };
		editing = null;
		if (mentionUsername) draft = `@${mentionUsername} `;
		inputEl?.focus();
	}
	function startEdit(item: Node, reply: boolean) {
		editing = { id: item.id, reply };
		replyTo = null;
		draft = item.text;
		inputEl?.focus();
	}
	function cancelCompose() {
		replyTo = null;
		editing = null;
		draft = '';
	}

	function findNode(id: number, reply: boolean): Node | undefined {
		if (reply) {
			for (const c of comments) {
				const r = c.replies.find((x) => x.id === id);
				if (r) return r;
			}
			return undefined;
		}
		return comments.find((c) => c.id === id);
	}

	async function send(gifUrl: string | null = null) {
		const text = draft.trim();
		if ((!text && !gifUrl) || sending) return;
		sending = true;
		gifOpen = false;

		// Mode edit.
		if (editing) {
			const target = findNode(editing.id, editing.reply);
			const prev = target?.text;
			if (target) target.text = text;
			try {
				await clientRequest(`comments/${editing.id}`, {
					method: 'PUT',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ content: text })
				});
			} catch {
				if (target && prev !== undefined) target.text = prev;
			} finally {
				editing = null;
				draft = '';
				sending = false;
			}
			return;
		}

		const parentId = replyTo?.id ?? null;
		try {
			const response = await clientRequest(`posts/${postId}/comments`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ content: text, gif_url: gifUrl, parent_comment_id: parentId }),
				schema: createdCommentResponseSchema
			});
			const created: Node = {
				id: response.data.comment_id,
				userId: response.data.user.user_id ?? currentUserId ?? 0,
				name: response.data.user.full_name?.trim() || response.data.user.username,
				username: response.data.user.username,
				avatarUrl: normalizeMediaUrl(response.data.user.profile_picture_url, mediaBaseUrl) ?? undefined,
				text: response.data.content,
				gifUrl: response.data.gif_url ?? gifUrl ?? undefined,
				createdLabel: 'baru saja',
				likesCount: 0,
				isLiked: false
			};
			if (parentId) {
				const parent = comments.find((c) => c.id === parentId);
				if (parent) {
					parent.replies.push(created);
					parent.showReplies = true;
				}
			} else {
				comments.unshift({ ...created, replies: [], showReplies: false });
			}
			draft = '';
			replyTo = null;
			recount();
		} catch {
			/* diamkan */
		} finally {
			sending = false;
		}
	}

	async function toggleLike(item: Node) {
		const was = item.isLiked;
		item.isLiked = !was;
		item.likesCount += was ? -1 : 1;
		try {
			await clientRequest(`comments/${item.id}/like`, { method: was ? 'DELETE' : 'POST' });
		} catch {
			item.isLiked = was;
			item.likesCount += was ? 1 : -1;
		}
	}

	async function remove(id: number, reply: boolean) {
		const ok = await confirmAction({
			title: 'Hapus komentar?',
			description: 'Komentar ini akan dihapus dari percakapan.',
			confirmLabel: 'Hapus',
			tone: 'danger'
		});
		if (!ok) return;
		try {
			await clientRequest(`comments/${id}`, { method: 'DELETE' });
			if (reply) {
				for (const c of comments) c.replies = c.replies.filter((r) => r.id !== id);
			} else {
				comments = comments.filter((c) => c.id !== id);
			}
			recount();
		} catch {
			/* diamkan */
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
			<strong>Komentar{#if totalCount > 0}<span>{totalCount}</span>{/if}</strong>
			<button class="close-btn" onclick={onClose} aria-label="Tutup komentar"><X size={20} /></button>
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
								{#if item.text}<MentionText text={item.text} />{/if}
							</p>
							{#if item.gifUrl}<img class="c-gif" src={item.gifUrl} alt="GIF" />{/if}
							<footer>
								<time>{item.createdLabel}</time>
								{#if item.likesCount > 0}<span>{item.likesCount} suka</span>{/if}
								<button onclick={() => startReply(item.id, item.name)}>Balas</button>
								{#if item.userId === currentUserId}
									<button onclick={() => startEdit(item, false)}>Edit</button>
									<button onclick={() => remove(item.id, false)}>Hapus</button>
								{/if}
							</footer>
							{#if item.replies.length > 0}
								<button class="show-replies" onclick={() => (item.showReplies = !item.showReplies)}>
									<span></span>{item.showReplies ? 'Sembunyikan balasan' : `Lihat ${item.replies.length} balasan`}
								</button>
							{/if}
							{#if item.showReplies}
								{#each item.replies as reply (reply.id)}
									<div class="c-reply">
										<Avatar name={reply.name} src={reply.avatarUrl} size="sm" />
										<div class="c-body">
											<p>
												<a href={`/u/${reply.username}`}>{reply.username}</a>
												{#if reply.text}<MentionText text={reply.text} />{/if}
											</p>
											{#if reply.gifUrl}<img class="c-gif" src={reply.gifUrl} alt="GIF" />{/if}
											<footer>
												<time>{reply.createdLabel}</time>
												{#if reply.likesCount > 0}<span>{reply.likesCount} suka</span>{/if}
												<button onclick={() => startReply(item.id, reply.name, reply.username)}>Balas</button>
												{#if reply.userId === currentUserId}
													<button onclick={() => startEdit(reply, true)}>Edit</button>
													<button onclick={() => remove(reply.id, true)}>Hapus</button>
												{/if}
											</footer>
										</div>
										<button
											class="c-like"
											class:liked={reply.isLiked}
											onclick={() => toggleLike(reply)}
											aria-label={reply.isLiked ? 'Batal suka' : 'Suka'}
										>
											<Heart size={14} fill={reply.isLiked ? 'currentColor' : 'none'} />
										</button>
									</div>
								{/each}
							{/if}
						</div>
						<button
							class="c-like"
							class:liked={item.isLiked}
							onclick={() => toggleLike(item)}
							aria-label={item.isLiked ? 'Batal suka' : 'Suka'}
						>
							<Heart size={15} fill={item.isLiked ? 'currentColor' : 'none'} />
						</button>
					</article>
				{/each}
			{/if}
		</div>

		{#if replyTo || editing}
			<div class="compose-hint">
				{editing ? 'Mengedit komentar' : `Membalas ${replyTo?.name}`}
				<button onclick={cancelCompose} aria-label="Batal"><X size={14} /></button>
			</div>
		{/if}
		<form
			class="comments-input"
			onsubmit={(e) => {
				e.preventDefault();
				void send();
			}}
		>
			{#if !editing}
				<button type="button" class="gif-btn" onclick={() => (gifOpen = true)} aria-label="Kirim GIF"
					>GIF</button
				>
			{/if}
			<input
				bind:this={inputEl}
				bind:value={draft}
				maxlength="500"
				placeholder={editing ? 'Edit komentar…' : replyTo ? 'Tulis balasan…' : 'Tambahkan komentar…'}
				aria-label="Tulis komentar"
			/>
			<button type="submit" disabled={!draft.trim() || sending} aria-label="Kirim">
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
		display: inline-flex;
		align-items: center;
		gap: 7px;
		font-size: 0.95rem;
	}
	header strong span {
		display: grid;
		min-width: 22px;
		height: 22px;
		place-items: center;
		background: var(--color-primary-soft, #fdece0);
		border-radius: 999px;
		color: var(--color-primary-strong, #c96a10);
		font-size: 0.66rem;
		font-weight: 800;
	}
	.close-btn {
		background: none;
		border: 0;
		color: var(--color-muted);
		cursor: pointer;
	}
	.comments-list {
		flex: 1;
		min-height: 0;
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
	.c-item,
	.c-reply {
		display: flex;
		gap: 10px;
		padding: 9px 0;
		align-items: flex-start;
	}
	.c-reply {
		padding: 8px 0 2px;
	}
	.c-body {
		flex: 1;
		min-width: 0;
	}
	.c-body p {
		margin: 0;
		font-size: 0.84rem;
		line-height: 1.4;
		word-break: break-word;
	}
	.c-body > p > a {
		font-weight: 700;
		color: var(--color-text);
		margin-right: 5px;
	}
	.c-body footer {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 12px;
		margin-top: 4px;
	}
	.c-body footer time,
	.c-body footer span {
		color: var(--color-muted);
		font-size: 0.68rem;
	}
	.c-body footer button {
		background: none;
		border: 0;
		padding: 0;
		color: var(--color-muted);
		font-size: 0.68rem;
		font-weight: 700;
		cursor: pointer;
	}
	.c-body footer button:hover {
		color: var(--color-text);
	}
	.show-replies {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin-top: 8px;
		background: none;
		border: 0;
		padding: 0;
		color: var(--color-muted);
		font-size: 0.72rem;
		font-weight: 700;
		cursor: pointer;
	}
	.show-replies span {
		width: 22px;
		height: 1px;
		background: var(--color-border);
	}
	.c-like {
		display: grid;
		place-items: center;
		flex: none;
		background: none;
		border: 0;
		color: var(--color-muted);
		cursor: pointer;
		padding-top: 3px;
	}
	.c-like.liked {
		color: var(--color-danger, #e0245e);
	}
	.c-gif {
		margin-top: 6px;
		max-width: 160px;
		max-height: 160px;
		border-radius: 10px;
	}
	.compose-hint {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 7px 15px;
		background: var(--color-surface-soft, #f4f5f7);
		color: var(--color-muted);
		font-size: 0.74rem;
	}
	.compose-hint button {
		background: none;
		border: 0;
		color: var(--color-muted);
		cursor: pointer;
	}
	.comments-input {
		display: flex;
		gap: 8px;
		align-items: center;
		padding: 10px 14px calc(10px + env(safe-area-inset-bottom, 0px));
		border-top: 1px solid var(--color-border);
	}
	.comments-input input {
		flex: 1;
		min-width: 0;
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
	.comments-input button[type='submit'] {
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
