<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { z } from 'zod';
	import { ShieldAlert, X, LoaderCircle, Trash2, Eye } from '@lucide/svelte';
	import { clientRequest } from '$lib/api/client';
	import { confirmAction } from '$lib/ui/confirm';
	import { portal } from '$lib/actions/portal';

	const noticesSchema = z.object({
		notices: z.array(
			z.object({
				post_id: z.coerce.number().int().positive(),
				caption: z.string().nullish(),
				thumbnail_url: z.string().nullish(),
				media_url: z.string().nullish(),
				is_video: z.boolean().catch(false),
				moderated_at: z.string().nullish(),
				reason: z.string().nullish()
			})
		)
	});

	type Notice = z.infer<typeof noticesSchema>['notices'][number];

	let queue = $state<Notice[]>([]);
	let busy = $state(false);
	const current = $derived(queue[0] ?? null);

	onMount(async () => {
		try {
			const res = await clientRequest('moderation/notices', { schema: noticesSchema });
			queue = res.notices;
		} catch {
			queue = [];
		}
	});

	// Akui pemberitahuan (tidak muncul lagi), lalu tampilkan berikutnya bila ada.
	async function ack(postId: number) {
		try {
			await clientRequest(`moderation/notices/${postId}/ack`, { method: 'POST' });
		} catch {
			/* biarkan; akan muncul lagi nanti, lebih baik daripada hilang diam-diam */
		}
	}

	async function viewPost() {
		const item = current;
		if (!item || busy) return;
		busy = true;
		await ack(item.post_id);
		busy = false;
		queue = queue.slice(1);
		await goto(`/posts/${item.post_id}`);
	}

	async function deletePost() {
		const item = current;
		if (!item || busy) return;
		const ok = await confirmAction({
			title: 'Hapus postingan ini?',
			description: 'Postingan yang dimoderasi akan dihapus permanen dan tidak dapat dipulihkan.',
			confirmLabel: 'Hapus permanen',
			tone: 'danger'
		});
		if (!ok) return;
		busy = true;
		try {
			await clientRequest(`posts/${item.post_id}`, { method: 'DELETE' });
			await ack(item.post_id);
			queue = queue.slice(1);
		} catch {
			/* biarkan modal terbuka bila gagal */
		} finally {
			busy = false;
		}
	}

	async function dismiss() {
		const item = current;
		if (!item || busy) return;
		busy = true;
		await ack(item.post_id);
		busy = false;
		queue = queue.slice(1);
	}
</script>

{#if current}
	<div class="mn-scrim" use:portal role="dialog" aria-modal="true" aria-label="Pemberitahuan moderasi">
		<div class="mn-card">
			<span class="mn-ico"><ShieldAlert size={26} /></span>
			<strong>Postingan Anda dimoderasi</strong>
			<p class="mn-lead">
				Salah satu postingan Anda telah diturunkan oleh tim Portal SI karena melanggar kebijakan
				platform. Postingan tidak lagi tampil di aplikasi.
			</p>

			<div class="mn-post">
				{#if current.thumbnail_url || current.media_url}
					<img src={current.thumbnail_url ?? current.media_url ?? ''} alt="Postingan dimoderasi" />
				{/if}
				<div>
					<small>Alasan moderasi</small>
					<p>{current.reason || 'Melanggar kebijakan platform.'}</p>
				</div>
			</div>

			<p class="mn-policy">
				Harap segera memperbaiki atau menghapus postingan sesuai kebijakan. Setelah masa retensi
				(sekitar 30 hari), postingan akan dihapus permanen.
			</p>

			<div class="mn-actions">
				<button class="mn-open" onclick={viewPost} disabled={busy}>
					<Eye size={16} /> Lihat post
				</button>
				<button class="mn-del" onclick={deletePost} disabled={busy}>
					{#if busy}<LoaderCircle size={15} class="mn-spin" />{:else}<Trash2 size={16} />{/if} Hapus
				</button>
			</div>

			{#if queue.length > 1}<small class="mn-more">+{queue.length - 1} pemberitahuan lain</small>{/if}
			<button class="mn-x" onclick={dismiss} aria-label="Tutup"><X size={18} /></button>
		</div>
	</div>
{/if}

<style>
	.mn-scrim {
		position: fixed;
		inset: 0;
		z-index: 3200;
		display: grid;
		place-items: center;
		padding: 16px;
		background: rgb(15 12 9 / 58%);
		backdrop-filter: blur(3px);
	}
	.mn-card {
		position: relative;
		width: min(100%, 420px);
		padding: 22px;
		background: var(--color-surface, #fff);
		border-radius: 20px;
		box-shadow: 0 26px 64px rgb(0 0 0 / 40%);
		text-align: center;
	}
	.mn-ico {
		display: inline-grid;
		width: 54px;
		height: 54px;
		place-items: center;
		background: rgb(192 57 43 / 12%);
		border-radius: 16px;
		color: #c0392b;
	}
	.mn-card > strong {
		display: block;
		margin: 12px 0 6px;
		font-size: 1.12rem;
	}
	.mn-lead {
		margin: 0;
		color: var(--color-muted);
		font-size: 0.85rem;
		line-height: 1.5;
	}
	.mn-post {
		display: flex;
		gap: 11px;
		align-items: center;
		margin: 16px 0;
		padding: 11px;
		background: var(--color-canvas-deep, #f6f1e8);
		border-radius: 13px;
		text-align: left;
	}
	.mn-post img {
		width: 52px;
		height: 52px;
		flex: none;
		object-fit: cover;
		border-radius: 9px;
	}
	.mn-post small {
		color: var(--color-muted);
		font-size: 0.66rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
	}
	.mn-post p {
		margin: 3px 0 0;
		font-size: 0.82rem;
		line-height: 1.45;
		white-space: pre-line;
	}
	.mn-policy {
		margin: 0 0 4px;
		color: var(--color-muted);
		font-size: 0.76rem;
		line-height: 1.5;
	}
	.mn-actions {
		display: flex;
		gap: 8px;
		margin-top: 16px;
	}
	.mn-actions > * {
		flex: 1;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
		min-height: 42px;
		border-radius: 12px;
		font-size: 0.84rem;
		font-weight: 720;
		cursor: pointer;
	}
	.mn-open {
		background: var(--color-primary);
		color: #fff;
		border: 0;
	}
	.mn-del {
		background: transparent;
		border: 1px solid rgb(192 57 43 / 40%);
		color: #c0392b;
	}
	.mn-actions button:disabled {
		opacity: 0.6;
		cursor: default;
	}
	.mn-more {
		display: block;
		margin-top: 10px;
		color: var(--color-muted);
		font-size: 0.72rem;
	}
	.mn-x {
		position: absolute;
		top: 12px;
		right: 12px;
		display: grid;
		width: 30px;
		height: 30px;
		place-items: center;
		padding: 0;
		background: transparent;
		border: 0;
		border-radius: 50%;
		color: var(--color-muted);
		cursor: pointer;
	}
	:global(.mn-spin) {
		animation: mn-spin 0.8s linear infinite;
	}
	@keyframes mn-spin {
		to {
			transform: rotate(360deg);
		}
	}
</style>
