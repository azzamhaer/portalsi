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

	let notices = $state<Notice[]>([]);
	let open = $state(false);
	let busyId = $state<number | null>(null);
	let closing = $state(false);

	onMount(async () => {
		try {
			const res = await clientRequest('moderation/notices', { schema: noticesSchema });
			notices = res.notices;
			open = notices.length > 0;
		} catch {
			notices = [];
		}
	});

	// Akui satu pemberitahuan di server (tak muncul lagi).
	async function ack(postId: number) {
		try {
			await clientRequest(`moderation/notices/${postId}/ack`, { method: 'POST' });
		} catch {
			/* biarkan; lebih baik muncul lagi daripada hilang diam-diam */
		}
	}

	async function viewPost(postId: number) {
		if (busyId !== null) return;
		busyId = postId;
		await ack(postId);
		notices = notices.filter((n) => n.post_id !== postId);
		busyId = null;
		open = false;
		await goto(`/posts/${postId}`);
	}

	async function deletePost(postId: number) {
		if (busyId !== null) return;
		const ok = await confirmAction({
			title: 'Hapus postingan ini?',
			description: 'Postingan yang dimoderasi akan dihapus permanen dan tidak dapat dipulihkan.',
			confirmLabel: 'Hapus permanen',
			tone: 'danger'
		});
		if (!ok) return;
		busyId = postId;
		try {
			await clientRequest(`posts/${postId}`, { method: 'DELETE' });
			await ack(postId);
			notices = notices.filter((n) => n.post_id !== postId);
			if (notices.length === 0) open = false;
		} catch {
			/* biarkan modal terbuka bila gagal */
		} finally {
			busyId = null;
		}
	}

	// "Mengerti" → akui SEMUA sekaligus, tutup modal.
	async function acknowledgeAll() {
		if (closing) return;
		closing = true;
		try {
			await Promise.all(notices.map((n) => ack(n.post_id)));
		} finally {
			notices = [];
			open = false;
			closing = false;
		}
	}
</script>

{#if open && notices.length > 0}
	<div
		class="mn-scrim"
		use:portal
		role="dialog"
		aria-modal="true"
		aria-label="Pemberitahuan moderasi"
	>
		<div class="mn-card" class:single={notices.length === 1}>
			<header class="mn-head">
				<span class="mn-ico"><ShieldAlert size={24} /></span>
				<div class="mn-headtext">
					<strong>
						{notices.length === 1
							? 'Postingan Anda dimoderasi'
							: `${notices.length} postingan Anda dimoderasi`}
					</strong>
					<p>
						Terdeteksi oleh sistem AI karena melanggar kebijakan platform. Postingan berikut tidak
						lagi tampil di aplikasi. Harap perbaiki atau hapus sesuai kebijakan — akan dihapus
						permanen setelah masa retensi (sekitar 30 hari).
					</p>
				</div>
				<button
					class="mn-x"
					onclick={acknowledgeAll}
					disabled={closing}
					aria-label="Tutup semua">{#if closing}<LoaderCircle size={16} class="mn-spin" />{:else}<X
							size={18}
						/>{/if}</button
				>
			</header>

			<div class="mn-list" class:scroll={notices.length > 3}>
				{#each notices as n (n.post_id)}
					<div class="mn-item">
						<div class="mn-thumb">
							{#if n.thumbnail_url || n.media_url}
								<img src={n.thumbnail_url ?? n.media_url ?? ''} alt="Postingan dimoderasi" />
							{:else}
								<span class="mn-thumb-empty"><ShieldAlert size={18} /></span>
							{/if}
						</div>
						<div class="mn-item-body">
							<small>Alasan moderasi</small>
							<p>{n.reason || 'Melanggar kebijakan platform.'}</p>
							<div class="mn-item-actions">
								<button
									class="mn-open"
									onclick={() => viewPost(n.post_id)}
									disabled={busyId !== null}><Eye size={15} /> Lihat post</button
								>
								<button
									class="mn-del"
									onclick={() => deletePost(n.post_id)}
									disabled={busyId !== null}
									>{#if busyId === n.post_id}<LoaderCircle size={14} class="mn-spin" />{:else}<Trash2
											size={15}
										/>{/if} Hapus</button
								>
							</div>
						</div>
					</div>
				{/each}
			</div>

			<footer class="mn-foot">
				<button class="mn-ack-all" onclick={acknowledgeAll} disabled={closing}>
					{#if closing}<LoaderCircle size={16} class="mn-spin" /> Menutup…{:else}Saya mengerti{/if}
				</button>
			</footer>
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
		display: flex;
		flex-direction: column;
		width: min(100%, 500px);
		max-height: min(88vh, 720px);
		background: var(--color-surface, #fff);
		border-radius: 20px;
		box-shadow: 0 26px 64px rgb(0 0 0 / 40%);
		overflow: hidden;
	}
	.mn-head {
		display: flex;
		gap: 12px;
		align-items: flex-start;
		padding: 20px 20px 14px;
	}
	.mn-ico {
		display: grid;
		width: 46px;
		height: 46px;
		flex: none;
		place-items: center;
		background: rgb(192 57 43 / 12%);
		border-radius: 14px;
		color: #c0392b;
	}
	.mn-headtext {
		flex: 1;
		min-width: 0;
	}
	.mn-headtext strong {
		display: block;
		font-size: 1.08rem;
	}
	.mn-headtext p {
		margin: 5px 0 0;
		color: var(--color-muted);
		font-size: 0.8rem;
		line-height: 1.5;
	}
	.mn-x {
		display: grid;
		width: 32px;
		height: 32px;
		flex: none;
		place-items: center;
		padding: 0;
		background: transparent;
		border: 0;
		border-radius: 50%;
		color: var(--color-muted);
		cursor: pointer;
	}
	.mn-x:hover {
		background: var(--color-surface-soft, #f1f2f4);
	}
	.mn-list {
		display: flex;
		flex-direction: column;
		gap: 10px;
		padding: 4px 20px;
	}
	.mn-list.scroll {
		overflow-y: auto;
	}
	.mn-item {
		display: flex;
		gap: 12px;
		padding: 12px;
		background: var(--color-canvas-deep, #f6f1e8);
		border-radius: 14px;
	}
	.mn-thumb {
		flex: none;
	}
	.mn-thumb img,
	.mn-thumb-empty {
		display: grid;
		place-items: center;
		width: 64px;
		height: 64px;
		object-fit: cover;
		border-radius: 10px;
		background: rgb(192 57 43 / 10%);
		color: #c0392b;
	}
	.mn-item-body {
		flex: 1;
		min-width: 0;
	}
	.mn-item-body small {
		color: var(--color-muted);
		font-size: 0.64rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
	}
	.mn-item-body p {
		margin: 3px 0 9px;
		font-size: 0.8rem;
		line-height: 1.45;
		white-space: pre-line;
		/* Batasi tampilan di daftar; alasan penuh ada di detail. */
		display: -webkit-box;
		-webkit-line-clamp: 3;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}
	.mn-item-actions {
		display: flex;
		gap: 8px;
	}
	.mn-item-actions button {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		min-height: 34px;
		padding: 0 12px;
		border-radius: 10px;
		font-size: 0.78rem;
		font-weight: 700;
		cursor: pointer;
	}
	.mn-open {
		background: var(--color-primary);
		border: 0;
		color: #fff;
	}
	.mn-del {
		background: transparent;
		border: 1px solid rgb(192 57 43 / 40%);
		color: #c0392b;
	}
	.mn-item-actions button:disabled {
		opacity: 0.6;
		cursor: default;
	}
	.mn-foot {
		padding: 14px 20px 20px;
	}
	.mn-ack-all {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
		width: 100%;
		min-height: 44px;
		background: var(--color-canvas-deep, #f1f2f4);
		border: 1px solid var(--color-border);
		border-radius: 12px;
		color: var(--color-text);
		font-size: 0.86rem;
		font-weight: 720;
		cursor: pointer;
	}
	.mn-ack-all:disabled {
		opacity: 0.6;
		cursor: default;
	}
	:global(.mn-spin) {
		animation: mn-spin 0.8s linear infinite;
	}
	@keyframes mn-spin {
		to {
			transform: rotate(360deg);
		}
	}
	@media (max-width: 520px) {
		.mn-item {
			flex-direction: column;
		}
		.mn-thumb img,
		.mn-thumb-empty {
			width: 100%;
			height: 120px;
		}
	}
</style>
