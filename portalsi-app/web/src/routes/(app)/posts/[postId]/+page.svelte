<script lang="ts">
	import { LogIn } from '@lucide/svelte';
	import { page } from '$app/state';
	import BackButton from '$lib/components/ui/BackButton.svelte';
	import PostDetailView from '$lib/components/post/PostDetailView.svelte';
	import type { PageProps } from './$types';
	let { data, form }: PageProps = $props();
	const shareUrl = $derived(page.url.href);
</script>

<svelte:head>
	<title>{data.og.title}</title>
	<meta name="description" content={data.og.description} />
	<meta property="og:site_name" content="Portal SI" />
	<meta property="og:type" content="article" />
	<meta property="og:title" content={data.og.title} />
	<meta property="og:description" content={data.og.description} />
	<meta property="og:url" content={shareUrl} />
	{#if data.og.image}<meta property="og:image" content={data.og.image} />{/if}
	<meta name="twitter:card" content={data.og.image ? 'summary_large_image' : 'summary'} />
	<meta name="twitter:title" content={data.og.title} />
	<meta name="twitter:description" content={data.og.description} />
	{#if data.og.image}<meta name="twitter:image" content={data.og.image} />{/if}
	{#if data.isPublic}<meta name="robots" content="index" />{:else}<meta
			name="robots"
			content="noindex"
		/>{/if}
</svelte:head>

{#if data.isPublic}
	<main class="public-post">
		<div class="card surface">
			{#if data.og.image}<img src={data.og.image} alt="" />{/if}
			<div class="body">
				<p class="eyebrow">Postingan Portal SI</p>
				<h1>{data.author}</h1>
				<p class="desc">{data.og.description}</p>
				<a class="cta" href={`/login?next=${encodeURIComponent(`/posts/${data.postId}`)}`}>
					<LogIn size={18} /> Masuk untuk melihat
				</a>
				<a class="alt" href="/welcome">Belum punya akun? Daftar</a>
			</div>
		</div>
	</main>
{:else}
	<div class="detail-page-wrap">
		<div class="detail-page-inner">
			<div class="detail-back"><BackButton fallback="/home" /></div>
			<div class="detail-page-card surface">
				<PostDetailView {data} {form} />
			</div>
		</div>
	</div>
{/if}

<style>
	.detail-page-wrap {
		display: flex;
		justify-content: center;
		padding: 18px;
	}
	.detail-page-inner {
		width: min(1100px, 100%);
	}
	.detail-back {
		margin-bottom: 12px;
	}
	.detail-page-card {
		width: 100%;
		height: min(86vh, 860px);
		overflow: hidden;
		border-radius: 18px;
	}
	.detail-page-card :global(.post-detail-layout) {
		height: 100%;
		grid-template-rows: minmax(0, 1fr);
	}
	.detail-page-card :global(.comment-list) {
		min-height: 0;
		overflow-y: auto;
	}
	.detail-page-card :global(.post-column) {
		border-radius: 18px 0 0 18px;
	}
	@media (max-width: 950px) {
		.detail-page-wrap {
			padding: 0;
		}
		.detail-back {
			padding: 8px 10px;
			margin-bottom: 0;
		}
		.detail-page-card {
			width: 100%;
			height: auto;
			min-height: 100dvh;
			border-radius: 0;
		}
		/* Mobile: media rata tepi — tanpa sudut membulat di sisi mana pun. */
		.detail-page-card :global(.post-column) {
			border-radius: 0;
		}
	}
	.public-post {
		display: grid;
		min-height: 100vh;
		place-items: center;
		padding: 24px;
		background: var(--color-canvas);
	}
	.card {
		width: min(100%, 440px);
		overflow: hidden;
		border-radius: 20px;
		text-align: center;
	}
	.card img {
		width: 100%;
		max-height: 340px;
		object-fit: cover;
	}
	.body {
		display: grid;
		justify-items: center;
		gap: 8px;
		padding: 22px 24px 26px;
	}
	.eyebrow {
		margin: 0;
		color: var(--color-primary-strong);
		font-size: 0.72rem;
		font-weight: 750;
		letter-spacing: 0.04em;
		text-transform: uppercase;
	}
	h1 {
		margin: 0;
		font-size: 1.25rem;
	}
	.desc {
		margin: 0;
		color: var(--color-muted);
		font-size: 0.88rem;
		line-height: 1.5;
	}
	.cta {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin-top: 10px;
		padding: 12px 20px;
		background: var(--color-primary);
		border-radius: 12px;
		color: white;
		font-weight: 720;
	}
	.alt {
		color: var(--color-muted);
		font-size: 0.8rem;
		font-weight: 650;
	}
</style>
