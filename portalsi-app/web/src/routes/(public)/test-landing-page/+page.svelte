<script lang="ts">
	import {
		ArrowRight,
		LogIn,
		Video,
		Store,
		Megaphone,
		Play,
		BadgeCheck,
		HelpCircle,
		Star
	} from '@lucide/svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const rupiah = (n: number) =>
		new Intl.NumberFormat('id-ID', {
			style: 'currency',
			currency: 'IDR',
			maximumFractionDigits: 0
		}).format(n);

	const apps = [
		{
			name: 'Portal SI App',
			desc: 'Media sosial Islami yang menghubungkan silaturahmi, berbagi inspirasi, dan bertumbuh dalam kebaikan.',
			href: 'https://app.portalsi.com/',
			domain: 'app.portalsi.com',
			icon: LogIn,
			bg: 'https://portalsi.com/app.webp',
			theme: 'orange',
			label: 'Membuka Aplikasi'
		},
		{
			name: 'Portal SI Meet',
			desc: 'Video meeting open source, instan dan gratis. Host login pakai akun Portal SI, peserta cukup dengan nama.',
			href: 'https://meet.portalsi.com/',
			domain: 'meet.portalsi.com',
			icon: Video,
			bg: 'https://portalsi.com/meet.webp',
			theme: 'green',
			label: 'Membuka Meet'
		},
		{
			name: 'Marketplace',
			desc: 'Marketplace terpercaya untuk menemukan produk, jasa, dan kebutuhan komunitas dalam satu tempat.',
			href: 'https://marketplace.portalsi.com/',
			domain: 'marketplace.portalsi.com',
			icon: Store,
			bg: 'https://portalsi.com/marketplace.webp',
			theme: 'duo',
			label: 'Membuka Marketplace'
		}
	];

	// ── Transisi ala index.html: lingkaran melebar dari kartu, lalu pindah halaman. ──
	let overlay = $state<{ x: number; y: number; color: string; label: string } | null>(null);
	let expanding = $state(false);

	function launch(event: MouseEvent, app: (typeof apps)[number]) {
		if (typeof window === 'undefined') return;
		const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (reduce) return; // biarkan navigasi <a> normal
		event.preventDefault();
		const el = (event.currentTarget as HTMLElement).getBoundingClientRect();
		const color =
			app.theme === 'green' ? '#2a5e3a' : app.theme === 'duo' ? '#1a1714' : '#e86a17';
		overlay = { x: el.left + el.width / 2, y: el.top + el.height / 2, color, label: app.label };
		requestAnimationFrame(() => requestAnimationFrame(() => (expanding = true)));
		setTimeout(() => (window.location.href = app.href), 640);
	}
</script>

<svelte:head>
	<title>Portal SI — Satu Portal, All In One</title>
	<meta
		name="description"
		content="Portal SI — media sosial Islami, video meeting, dan marketplace komunitas dalam satu tempat."
	/>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
	<link
		href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
		rel="stylesheet"
	/>
</svelte:head>

<div class="land">
	<div class="dots"></div>
	<div class="glow glow-1"></div>
	<div class="glow glow-2"></div>

	<header class="top">
		<a class="brand" href="/test-landing-page">
			<img src="https://portalsi.com/favicon.png" alt="" />
			<span>Portal <b>SI</b></span>
		</a>
		<a class="help" href="https://wa.me/6281350880733"><HelpCircle size={16} /> Bantuan</a>
	</header>

	<section class="hero">
		<p class="salam">Assalamu'alaikum 👋</p>
		<h1 class="title">
			Satu Portal,<br /><span class="accent">All In One.</span>
			<span class="shine"></span>
		</h1>
		<p class="lead">
			Pilih layanan yang Anda butuhkan, <span class="hl">semua dalam satu tempat.</span>
		</p>
	</section>

	<section class="apps">
		{#each apps as app, i (app.href)}
			{@const Icon = app.icon}
			<a
				class="card {app.theme}"
				href={app.href}
				style={`animation-delay:${0.25 + i * 0.1}s`}
				onclick={(e) => launch(e, app)}
			>
				<span class="card-media" style={`background-image:url('${app.bg}')`}></span>
				<span class="arch"><Icon size={24} /></span>
				<h2>{app.name}</h2>
				<p>{app.desc}</p>
				<span class="foot">
					<span class="domain">{app.domain}</span>
					<span class="go"><ArrowRight size={16} /></span>
				</span>
			</a>
		{/each}
	</section>

	{#if data.posts.length}
		<section class="block">
			<div class="block-head">
				<h2>Yang sedang dibagikan</h2>
				<a class="more" href="https://app.portalsi.com/explore">Jelajahi <ArrowRight size={15} /></a>
			</div>
			<div class="posts">
				{#each data.posts as post (post.id)}
					<a class="post" href={`/posts/${post.id}`} aria-label={post.caption || 'Postingan'}>
						{#if post.imageUrl}<img class="post-img" src={post.imageUrl} alt="" loading="lazy" />{/if}
						{#if post.isVideo}<span class="post-badge"><Play size={13} fill="currentColor" /></span>{/if}
						<span class="post-over">
							{#if post.user?.avatarUrl}<img class="pu-av" src={post.user.avatarUrl} alt="" />{/if}
							<b>{post.user?.username}</b>
							{#if post.user?.verified}<BadgeCheck size={13} />{/if}
						</span>
					</a>
				{/each}
			</div>
		</section>
	{/if}

	{#if data.announcements.length}
		<section class="block">
			<div class="block-head"><h2><Megaphone size={18} /> Pengumuman</h2></div>
			<div class="announcements">
				{#each data.announcements as a (a.id)}
					<article class="ann">
						{#if a.imageUrl}<img src={a.imageUrl} alt="" loading="lazy" />{/if}
						<div class="ann-body">
							{#if a.pinned}<span class="ann-pin">Disematkan</span>{/if}
							<h3>{a.title}</h3>
							<p>{a.excerpt}</p>
							<small>{a.author}</small>
						</div>
					</article>
				{/each}
			</div>
		</section>
	{/if}

	{#if data.products.length}
		<section class="block">
			<div class="block-head">
				<h2><Store size={18} /> Dari Marketplace</h2>
				<a class="more" href="https://marketplace.portalsi.com/">Lihat semua <ArrowRight size={15} /></a>
			</div>
			<div class="products">
				{#each data.products as p (p.id)}
					<a class="product" href={p.url}>
						<span class="prod-img">{#if p.imageUrl}<img src={p.imageUrl} alt="" loading="lazy" />{/if}</span>
						<span class="prod-body">
							<strong>{p.name}</strong>
							{#if p.rating > 0}<span class="prod-rate"><Star size={12} fill="currentColor" /> {p.rating.toFixed(1)}</span>{/if}
							<span class="prod-price">
								<span>{rupiah(p.price)}</span>
								{#if p.originalPrice > p.price}<del>{rupiah(p.originalPrice)}</del>{/if}
							</span>
						</span>
					</a>
				{/each}
			</div>
		</section>
	{/if}

	<footer class="foot-note">© {new Date().getFullYear()} Portal SI. All rights reserved.</footer>
</div>

{#if overlay}
	<div
		class="overlay"
		class:expand={expanding}
		style={`--x:${overlay.x}px;--y:${overlay.y}px;--c:${overlay.color}`}
	>
		<div class="overlay-mark"><span class="spin"></span><span>{overlay.label}…</span></div>
	</div>
{/if}

<style>
	.land {
		--cream: #f5f0e8;
		--card: #fff;
		--ink: #1a1714;
		--ink-soft: #5c5347;
		--orange: #e86a17;
		--orange-dark: #b8530e;
		--orange-soft: #fde9d2;
		--green: #2a5e3a;
		--green-dark: #1a4028;
		--green-soft: #e1efe2;
		--border: rgb(26 23 20 / 8%);
		position: relative;
		min-height: 100vh;
		padding: 0 20px 60px;
		overflow: hidden;
		background: linear-gradient(180deg, #faf6ef 0%, var(--cream) 100%);
		color: var(--ink);
		font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
	}
	.dots {
		position: absolute;
		inset: 0;
		z-index: 0;
		background-image: radial-gradient(circle at 1px 1px, rgb(26 23 20 / 4%) 1px, transparent 1px);
		background-size: 32px 32px;
		pointer-events: none;
	}
	.glow {
		position: absolute;
		border-radius: 50%;
		filter: blur(30px);
		pointer-events: none;
		z-index: 0;
	}
	.glow-1 {
		top: -120px;
		left: -120px;
		width: 380px;
		height: 380px;
		background: radial-gradient(circle, rgb(232 106 23 / 22%), transparent 70%);
		animation: drift1 14s ease-in-out infinite alternate;
	}
	.glow-2 {
		bottom: -160px;
		right: -160px;
		width: 440px;
		height: 440px;
		background: radial-gradient(circle, rgb(42 94 58 / 18%), transparent 70%);
		animation: drift2 16s ease-in-out infinite alternate;
	}
	@keyframes drift1 {
		to {
			transform: translate(40px, 30px);
		}
	}
	@keyframes drift2 {
		to {
			transform: translate(-40px, -30px);
		}
	}
	.land > :not(.dots):not(.glow) {
		position: relative;
		z-index: 1;
	}
	.top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		max-width: 1100px;
		margin: 0 auto;
		padding: 22px 4px;
	}
	.brand {
		display: inline-flex;
		align-items: center;
		gap: 9px;
		color: var(--ink);
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-weight: 800;
		font-size: 1.2rem;
		text-decoration: none;
	}
	.brand img {
		width: 26px;
		height: 26px;
	}
	.brand b {
		color: var(--orange);
	}
	.help {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 8px 15px;
		background: rgb(255 255 255 / 70%);
		border: 1px solid var(--border);
		border-radius: 999px;
		color: var(--ink-soft);
		font-size: 0.85rem;
		font-weight: 600;
		text-decoration: none;
		backdrop-filter: blur(8px);
		transition: background 0.2s ease, transform 0.2s ease;
	}
	.help:hover {
		background: var(--green-soft);
		transform: translateY(-1px);
	}
	.hero {
		max-width: 720px;
		margin: 44px auto 8px;
		text-align: center;
	}
	.salam {
		margin: 0 0 10px;
		color: var(--ink-soft);
		font-weight: 600;
		opacity: 0;
		animation: rise 0.6s ease 0.05s forwards;
	}
	.title {
		position: relative;
		display: inline-block;
		margin: 0;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-weight: 800;
		font-size: clamp(2.4rem, 7vw, 4.1rem);
		line-height: 1.02;
		letter-spacing: -0.02em;
		overflow: hidden;
		opacity: 0;
		animation: rise 0.6s ease 0.1s forwards;
	}
	.accent {
		background: linear-gradient(135deg, #e86a17 0%, #f58a3e 30%, #2a5e3a 70%, #3d8b55 100%);
		background-size: 300% 300%;
		-webkit-background-clip: text;
		background-clip: text;
		color: transparent;
		animation: grad 5s ease-in-out infinite;
	}
	.shine {
		position: absolute;
		inset: 0;
		background: linear-gradient(
			105deg,
			transparent 30%,
			rgb(255 255 255 / 18%) 48%,
			rgb(255 255 255 / 28%) 50%,
			rgb(255 255 255 / 18%) 52%,
			transparent 70%
		);
		animation: shine 5s ease-in-out infinite;
		pointer-events: none;
	}
	@keyframes grad {
		0%,
		100% {
			background-position: 0% 50%;
		}
		50% {
			background-position: 100% 50%;
		}
	}
	@keyframes shine {
		0% {
			transform: translateX(-100%) skewX(-15deg);
		}
		30%,
		100% {
			transform: translateX(120%) skewX(-15deg);
		}
	}
	.lead {
		margin: 20px auto 0;
		max-width: 460px;
		color: var(--ink-soft);
		font-size: 1.02rem;
		opacity: 0;
		animation: rise 0.6s ease 0.2s forwards;
	}
	.hl {
		color: var(--orange-dark);
		font-weight: 600;
	}
	@keyframes rise {
		from {
			opacity: 0;
			transform: translateY(14px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}
	.apps {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 22px;
		max-width: 1100px;
		margin: 44px auto 0;
	}
	.card {
		position: relative;
		display: flex;
		flex-direction: column;
		padding: 30px 26px;
		overflow: hidden;
		background: rgb(255 255 255 / 85%);
		border: 1px solid rgb(255 255 255 / 40%);
		border-radius: 24px;
		box-shadow: 0 1px 3px rgb(26 23 20 / 4%), 0 16px 34px -20px rgb(26 23 20 / 30%);
		color: var(--ink);
		text-decoration: none;
		backdrop-filter: blur(12px);
		opacity: 0;
		animation: rise 0.6s ease forwards;
		transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
	}
	.card::before {
		content: '';
		position: absolute;
		inset: 0 0 auto;
		height: 4px;
		z-index: 2;
	}
	.card.orange::before {
		background: linear-gradient(90deg, var(--orange), var(--orange-dark));
	}
	.card.green::before {
		background: linear-gradient(90deg, var(--green), var(--green-dark));
	}
	.card.duo::before {
		background: linear-gradient(90deg, var(--orange), var(--green));
	}
	.card:hover {
		transform: translateY(-6px);
		box-shadow: 0 26px 52px -20px rgb(26 23 20 / 28%);
		background: rgb(255 255 255 / 95%);
	}
	.card-media {
		position: absolute;
		inset: 0;
		z-index: 0;
		background-size: cover;
		background-position: center;
		opacity: 0.14;
		-webkit-mask-image: linear-gradient(135deg, transparent 12%, black 82%);
		mask-image: linear-gradient(135deg, transparent 12%, black 82%);
		transition: transform 0.6s ease, opacity 0.4s ease;
	}
	.card:hover .card-media {
		transform: scale(1.08);
		opacity: 0.2;
	}
	.card > :not(.card-media) {
		position: relative;
		z-index: 1;
	}
	.arch {
		display: grid;
		width: 60px;
		height: 70px;
		place-items: center;
		margin-bottom: 20px;
		border-radius: 50% 50% 16px 16px;
		transition: transform 0.3s ease, box-shadow 0.3s ease;
	}
	.card.orange .arch {
		background: rgb(232 106 23 / 10%);
		color: var(--orange-dark);
	}
	.card.green .arch {
		background: rgb(42 94 58 / 10%);
		color: var(--green-dark);
	}
	.card.duo .arch {
		background: rgb(232 106 23 / 8%);
		color: var(--ink);
	}
	.card.orange:hover .arch {
		box-shadow: 0 0 0 8px rgb(232 106 23 / 8%);
		transform: scale(1.05);
	}
	.card.green:hover .arch {
		box-shadow: 0 0 0 8px rgb(42 94 58 / 8%);
		transform: scale(1.05);
	}
	.card.duo:hover .arch {
		box-shadow: 0 0 0 8px rgb(26 23 20 / 6%);
		transform: scale(1.05);
	}
	.card h2 {
		margin: 0 0 8px;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.28rem;
	}
	.card p {
		flex-grow: 1;
		margin: 0 0 22px;
		color: var(--ink-soft);
		font-size: 0.94rem;
		line-height: 1.6;
	}
	.foot {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding-top: 16px;
		border-top: 1px solid rgb(26 23 20 / 6%);
	}
	.domain {
		color: var(--ink-soft);
		font-size: 0.78rem;
		font-weight: 500;
	}
	.go {
		display: grid;
		width: 34px;
		height: 34px;
		place-items: center;
		border-radius: 50%;
		transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease;
	}
	.card.orange .go {
		background: rgb(232 106 23 / 8%);
		color: var(--orange-dark);
	}
	.card.green .go {
		background: rgb(42 94 58 / 8%);
		color: var(--green-dark);
	}
	.card.duo .go {
		background: rgb(26 23 20 / 6%);
		color: var(--ink);
	}
	.card.orange:hover .go {
		background: var(--orange);
		color: #fff;
		transform: translateX(3px);
	}
	.card.green:hover .go {
		background: var(--green);
		color: #fff;
		transform: translateX(3px);
	}
	.card.duo:hover .go {
		background: var(--ink);
		color: #fff;
		transform: translateX(3px);
	}
	.block {
		max-width: 1100px;
		margin: 56px auto 0;
	}
	.block-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 18px;
	}
	.block-head h2 {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin: 0;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.42rem;
	}
	.more {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		color: var(--orange-dark);
		font-size: 0.85rem;
		font-weight: 600;
		text-decoration: none;
	}
	.more:hover {
		gap: 7px;
	}
	.posts {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 12px;
	}
	.post {
		position: relative;
		aspect-ratio: 1;
		overflow: hidden;
		border-radius: 18px;
		background: var(--cream);
		box-shadow: 0 6px 18px -10px rgb(26 23 20 / 30%);
	}
	.post-img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		transition: transform 0.35s ease;
	}
	.post:hover .post-img {
		transform: scale(1.06);
	}
	.post-badge {
		position: absolute;
		top: 9px;
		right: 9px;
		color: #fff;
		filter: drop-shadow(0 1px 2px rgb(0 0 0 / 55%));
	}
	.post-over {
		position: absolute;
		inset: auto 0 0;
		display: flex;
		align-items: center;
		gap: 6px;
		padding: 26px 11px 10px;
		background: linear-gradient(transparent, rgb(0 0 0 / 60%));
		color: #fff;
	}
	/* Avatar chip kecil — dipaksa ukuran agar tak menutupi gambar. */
	.pu-av {
		width: 22px !important;
		height: 22px !important;
		flex: none;
		object-fit: cover;
		border: 1.5px solid rgb(255 255 255 / 80%);
		border-radius: 50%;
	}
	.post-over b {
		overflow: hidden;
		font-size: 0.78rem;
		font-weight: 700;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.announcements {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 14px;
	}
	.ann {
		display: flex;
		gap: 14px;
		padding: 14px;
		background: var(--card);
		border: 1px solid var(--border);
		border-radius: 18px;
		transition: transform 0.18s ease, box-shadow 0.18s ease;
	}
	.ann:hover {
		transform: translateY(-2px);
		box-shadow: 0 14px 30px -16px rgb(26 23 20 / 30%);
	}
	.ann img {
		width: 84px;
		height: 84px;
		flex: none;
		object-fit: cover;
		border-radius: 14px;
	}
	.ann-body {
		min-width: 0;
	}
	.ann-pin {
		display: inline-block;
		margin-bottom: 4px;
		padding: 2px 8px;
		background: var(--orange-soft);
		border-radius: 999px;
		color: var(--orange-dark);
		font-size: 0.66rem;
		font-weight: 700;
	}
	.ann-body h3 {
		margin: 0 0 4px;
		font-size: 1rem;
	}
	.ann-body p {
		margin: 0 0 6px;
		color: var(--ink-soft);
		font-size: 0.84rem;
		line-height: 1.5;
	}
	.ann-body small {
		color: var(--ink-soft);
		opacity: 0.8;
		font-size: 0.74rem;
	}
	.products {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 14px;
	}
	.product {
		display: flex;
		flex-direction: column;
		overflow: hidden;
		background: var(--card);
		border: 1px solid var(--border);
		border-radius: 18px;
		color: var(--ink);
		text-decoration: none;
		transition: transform 0.18s ease, box-shadow 0.18s ease;
	}
	.product:hover {
		transform: translateY(-4px);
		box-shadow: 0 16px 34px -18px rgb(26 23 20 / 32%);
	}
	.prod-img {
		display: block;
		aspect-ratio: 1;
		background: var(--cream);
	}
	.prod-img img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.prod-body {
		display: block;
		padding: 11px 12px 13px;
	}
	.prod-body strong {
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		font-size: 0.86rem;
		font-weight: 600;
		line-height: 1.35;
	}
	.prod-rate {
		display: inline-flex;
		align-items: center;
		gap: 3px;
		margin-top: 5px;
		color: var(--gold, #c99a2e);
		font-size: 0.74rem;
		font-weight: 700;
	}
	.prod-price {
		display: flex;
		align-items: baseline;
		gap: 6px;
		margin-top: 5px;
	}
	.prod-price > span {
		color: var(--orange-dark);
		font-weight: 800;
		font-size: 0.9rem;
	}
	.prod-price del {
		color: var(--ink-soft);
		opacity: 0.6;
		font-size: 0.74rem;
	}
	.foot-note {
		max-width: 1100px;
		margin: 56px auto 0;
		text-align: center;
		color: var(--ink-soft);
		font-size: 0.82rem;
	}
	/* ── Transition overlay ── */
	.overlay {
		position: fixed;
		inset: 0;
		z-index: 999;
		pointer-events: none;
		background: var(--c, #e86a17);
		clip-path: circle(0 at var(--x, 50%) var(--y, 50%));
		transition: clip-path 0.62s cubic-bezier(0.65, 0, 0.35, 1);
	}
	.overlay.expand {
		clip-path: circle(150% at var(--x, 50%) var(--y, 50%));
	}
	.overlay-mark {
		position: absolute;
		top: 50%;
		left: 50%;
		display: flex;
		align-items: center;
		gap: 9px;
		transform: translate(-50%, -50%) scale(0.6);
		opacity: 0;
		color: #fff;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-weight: 700;
		transition: opacity 0.3s ease 0.25s, transform 0.4s ease 0.25s;
	}
	.overlay.expand .overlay-mark {
		opacity: 1;
		transform: translate(-50%, -50%) scale(1);
	}
	.spin {
		width: 18px;
		height: 18px;
		border: 2.5px solid rgb(255 255 255 / 35%);
		border-top-color: #fff;
		border-radius: 50%;
		animation: spin 0.7s linear infinite;
	}
	@keyframes spin {
		to {
			transform: rotate(360deg);
		}
	}
	@media (max-width: 860px) {
		.apps {
			grid-template-columns: 1fr;
		}
		.posts,
		.products {
			grid-template-columns: repeat(2, 1fr);
		}
		.announcements {
			grid-template-columns: 1fr;
		}
	}
	@media (prefers-reduced-motion: reduce) {
		.glow,
		.accent,
		.shine,
		.card,
		.salam,
		.title,
		.lead {
			animation: none !important;
		}
	}
</style>
