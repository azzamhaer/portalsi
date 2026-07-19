<script lang="ts">
	import { untrack } from 'svelte';
	import { Check, X } from '@lucide/svelte';
	import { clientRequest } from '$lib/api/client';
	import Avatar from '$lib/components/ui/Avatar.svelte';
	import SectionPage from '$lib/components/layout/SectionPage.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
	let invites = $state(untrack(() => [...data.invites]));
	let busy = $state<number | null>(null);
	let message = $state('');

	async function decide(postId: number, accept: boolean) {
		if (busy !== null) return;
		busy = postId;
		message = '';
		try {
			await clientRequest(`posts/${postId}/collaborators/${accept ? 'accept' : 'reject'}`, {
				method: 'POST'
			});
			invites = invites.filter((i) => i.postId !== postId);
			message = accept ? 'Kolaborasi diterima.' : 'Undangan ditolak.';
		} catch {
			message = 'Gagal memproses. Coba lagi.';
		} finally {
			busy = null;
		}
	}
</script>

<svelte:head><title>Undangan kolaborasi — Portal SI</title></svelte:head>
<SectionPage
	eyebrow="Kolaborasi"
	title="Undangan kolaborasi"
	description="Setujui untuk menjadi co-author. Postingan akan muncul juga di profil Anda."
>
	<section class="collab-invites surface">
		{#each invites as inv (inv.postId)}
			<article>
				<a class="thumb" href={`/posts/${inv.postId}`}>
					<img src={inv.thumbnailUrl} alt={inv.caption || 'Postingan'} />
				</a>
				<div class="info">
					<div class="who">
						<Avatar name={inv.inviter.fullName} src={inv.inviter.avatarUrl} size="sm" />
						<span
							><strong>@{inv.inviter.username}</strong> mengundang Anda berkolaborasi</span
						>
					</div>
					{#if inv.caption}<p class="cap">{inv.caption}</p>{/if}
				</div>
				<div class="acts">
					<button class="accept" disabled={busy === inv.postId} onclick={() => decide(inv.postId, true)}
						><Check size={17} /></button
					>
					<button class="reject" disabled={busy === inv.postId} onclick={() => decide(inv.postId, false)}
						><X size={17} /></button
					>
				</div>
			</article>
		{/each}
		{#if invites.length === 0}<p class="empty">Tidak ada undangan kolaborasi.</p>{/if}
		{#if message}<p class="msg" aria-live="polite">{message}</p>{/if}
	</section>
</SectionPage>

<style>
	.collab-invites {
		overflow: hidden;
	}
	article {
		display: grid;
		grid-template-columns: 54px 1fr auto;
		align-items: center;
		gap: 12px;
		padding: 13px 15px;
		border-bottom: 1px solid var(--color-border);
	}
	.thumb {
		width: 54px;
		height: 54px;
		border-radius: 10px;
		overflow: hidden;
		background: var(--color-surface-soft);
	}
	.thumb img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.info {
		min-width: 0;
	}
	.who {
		display: flex;
		align-items: center;
		gap: 8px;
		font-size: 0.82rem;
	}
	.who strong {
		font-weight: 700;
	}
	.cap {
		margin: 4px 0 0;
		color: var(--color-muted);
		font-size: 0.76rem;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.acts {
		display: flex;
		gap: 7px;
	}
	.acts button {
		display: grid;
		width: 38px;
		height: 38px;
		place-items: center;
		border: 0;
		border-radius: 10px;
		cursor: pointer;
	}
	.acts button:disabled {
		opacity: 0.5;
	}
	.accept {
		background: var(--color-secondary);
		color: #fff;
	}
	.reject {
		background: var(--color-danger-soft);
		color: var(--color-danger);
	}
	.empty,
	.msg {
		padding: 26px;
		color: var(--color-muted);
		font-size: 0.8rem;
		text-align: center;
	}
</style>
