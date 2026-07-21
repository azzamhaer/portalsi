<script lang="ts">
	import { ArrowLeft, Copy, FileEdit, Play } from '@lucide/svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
</script>

<svelte:head><title>Draft — Portal SI</title><meta name="robots" content="noindex" /></svelte:head>

<main class="drafts-page">
	<header>
		<a href="/profile" aria-label="Kembali ke profil"><ArrowLeft size={19} /></a>
		<div>
			<h1>Draft</h1>
			<p>Hanya Anda yang bisa melihat halaman ini.</p>
		</div>
	</header>

	{#if data.failed}
		<p class="state" role="status">Draft belum dapat dimuat. Coba muat ulang halaman.</p>
	{:else if data.drafts.length === 0}
		<div class="state empty">
			<span><FileEdit size={26} /></span>
			<strong>Belum ada draft</strong>
			<p>
				Saat membuat postingan, pilih <b>Simpan draft</b> untuk menyimpannya di sini tanpa
				menerbitkannya.
			</p>
			<a href="/create/post">Buat postingan</a>
		</div>
	{:else}
		<section class="draft-grid" aria-label="Daftar draft">
			{#each data.drafts as draft (draft.id)}
				<!-- Draft dibuka di halaman detail post biasa: pemiliknya diizinkan melihat draft,
			     dan di sana sudah ada modal edit (caption, lokasi, kolaborator) + tombol terbit. -->
			<a href={`/posts/${draft.id}`} data-no-modal>
					<img src={draft.thumbnailUrl ?? draft.mediaUrl} alt={draft.caption} loading="lazy" />
					{#if draft.isVideo}<span aria-label="Video"><Play size={14} fill="currentColor" /></span
						>{:else if draft.isMultiple}<span aria-label="Beberapa foto"><Copy size={14} /></span
						>{/if}
				</a>
			{/each}
		</section>
	{/if}
</main>

<style>
	.drafts-page {
		width: min(100% - 32px, 935px);
		margin: 0 auto;
		padding: 18px 0 40px;
	}
	header {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 6px 0 18px;
	}
	header a {
		display: grid;
		width: 40px;
		height: 40px;
		flex: none;
		place-items: center;
		background: var(--color-surface);
		border: 1px solid var(--color-border);
		border-radius: 12px;
		color: var(--color-text);
	}
	header h1 {
		margin: 0;
		font-size: 1.35rem;
		letter-spacing: -0.03em;
	}
	header p {
		margin: 2px 0 0;
		color: var(--color-muted);
		font-size: 0.78rem;
	}
	.draft-grid {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 1px;
		overflow: hidden;
		border-radius: var(--radius-lg);
	}
	.draft-grid a {
		position: relative;
		aspect-ratio: 1;
		background: var(--color-canvas-deep, #f1ece3);
	}
	.draft-grid img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.draft-grid a span {
		position: absolute;
		top: 7px;
		right: 7px;
		display: grid;
		width: 24px;
		height: 24px;
		place-items: center;
		background: rgb(22 17 12 / 55%);
		border-radius: 50%;
		color: white;
	}
	.state {
		padding: 28px 20px;
		color: var(--color-muted);
		font-size: 0.86rem;
		text-align: center;
	}
	.state.empty {
		display: grid;
		justify-items: center;
		gap: 8px;
		background: var(--color-surface);
		border: 1px solid var(--color-border);
		border-radius: var(--radius-lg);
		padding: 40px 24px;
	}
	.state.empty span {
		display: grid;
		width: 52px;
		height: 52px;
		place-items: center;
		background: var(--color-primary-soft);
		border-radius: 16px;
		color: var(--color-primary-strong);
	}
	.state.empty strong {
		font-size: 1rem;
		color: var(--color-text);
	}
	.state.empty p {
		max-width: 26rem;
		margin: 0;
		line-height: 1.55;
	}
	.state.empty a {
		margin-top: 6px;
		padding: 10px 18px;
		background: var(--color-primary);
		border-radius: 11px;
		color: white;
		font-size: 0.84rem;
		font-weight: 720;
	}
	@media (max-width: 767px) {
		.drafts-page {
			width: 100%;
			padding-top: 0;
		}
		header {
			padding: 16px 16px 14px;
		}
		.draft-grid {
			border-radius: 0;
		}
	}
</style>
