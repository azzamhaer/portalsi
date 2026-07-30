<script lang="ts">
	import {
		ArrowRight,
		ArrowUpRight,
		LogIn,
		Video,
		Store,
		Megaphone,
		Play,
		BadgeCheck,
		HelpCircle,
		Users,
		Images,
		ShoppingBag,
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
	const compact = (n: number) =>
		n >= 1000 ? `${(n / 1000).toFixed(n >= 10000 ? 0 : 1)}rb` : `${n}`;

	const apps = [
		{
			name: 'Portal SI App',
			tag: 'Sosial media Islami',
			href: 'https://app.portalsi.com/',
			domain: 'app.portalsi.com',
			icon: LogIn,
			bg: 'https://portalsi.com/app.webp',
			theme: 'app',
			label: 'Membuka Aplikasi'
		},
		{
			name: 'Portal SI Meet',
			tag: 'Video meeting gratis',
			href: 'https://meet.portalsi.com/',
			domain: 'meet.portalsi.com',
			icon: Video,
			bg: 'https://portalsi.com/meet.webp',
			theme: 'meet',
			label: 'Membuka Meet'
		},
		{
			name: 'Marketplace',
			tag: 'Belanja kebutuhan',
			href: 'https://marketplace.portalsi.com/',
			domain: 'marketplace.portalsi.com',
			icon: Store,
			bg: 'https://portalsi.com/marketplace.webp',
			theme: 'mkt',
			label: 'Membuka Marketplace'
		}
	];

	// Dua baris marquee dari postingan acak (digandakan agar loop mulus).
	const half = Math.ceil(data.posts.length / 2);
	const rowA = $derived([...data.posts.slice(0, half), ...data.posts.slice(0, half)]);
	const rowB = $derived([...data.posts.slice(half), ...data.posts.slice(half)]);

	const stats = $derived([
		{ icon: Users, label: 'Anggota', value: compact(data.stats.members) },
		{ icon: Images, label: 'Postingan', value: compact(data.stats.posts) },
		{ icon: ShoppingBag, label: 'Produk', value: compact(data.stats.products) }
	]);

	// Transisi lingkaran melebar saat memilih layanan.
	let overlay = $state<{ x: number; y: number; color: string; label: string } | null>(null);
	let expanding = $state(false);
	function launch(event: MouseEvent, app: (typeof apps)[number]) {
		if (typeof window === 'undefined') return;
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
		event.preventDefault();
		const el = (event.currentTarget as HTMLElement).getBoundingClientRect();
		const color = app.theme === 'meet' ? '#1f7a45' : app.theme === 'mkt' ? '#8a4b16' : '#e86a17';
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
	<div class="aura"></div>

	<header class="top">
		<a class="brand" href="/test-landing-page">
			<img src="https://portalsi.com/favicon.png" alt="" />
			<span>Portal <b>SI</b></span>
		</a>
		<a class="help" href="https://wa.me/6281350880733"><HelpCircle size={15} /> Bantuan</a>
	</header>

	<!-- ===== BENTO ===== -->
	<section class="bento">
		<div class="hero-tile">
			<p class="salam">Assalamu'alaikum 👋</p>
			<h1>Satu Portal,<br /><span class="accent">All&nbsp;In&nbsp;One.</span></h1>
			<p class="lead">Silaturahmi, belajar, meeting, dan belanja — <b>semua dalam satu tempat.</b></p>
			<div class="stat-row">
				{#each stats as s (s.label)}
					{@const I = s.icon}
					<div class="stat">
						<I size={16} />
						<span class="v">{s.value}</span>
						<span class="l">{s.label}</span>
					</div>
				{/each}
			</div>
			<a class="cta" href="https://app.portalsi.com/">Masuk ke Portal SI <ArrowRight size={17} /></a>
		</div>

		{#each apps as app (app.href)}
			{@const Icon = app.icon}
			<a
				class="svc {app.theme}"
				href={app.href}
				style={`background-image:linear-gradient(135deg,var(--g1),var(--g2)),url('${app.bg}')`}
				onclick={(e) => launch(e, app)}
			>
				<span class="svc-ico"><Icon size={22} /></span>
				<span class="svc-body">
					<b>{app.name}</b>
					<small>{app.tag}</small>
				</span>
				<span class="svc-go"><ArrowUpRight size={20} /></span>
				<span class="svc-domain">{app.domain}</span>
			</a>
		{/each}
	</section>

	<!-- ===== LIVE MARQUEE ===== -->
	{#if data.posts.length}
		<section class="live">
			<div class="live-head">
				<h2><span class="dot"></span> Denyut komunitas</h2>
				<a class="more" href="https://app.portalsi.com/explore">Jelajahi <ArrowRight size={14} /></a>
			</div>
			<div class="marquee">
				<div class="mrow">
					{#each rowA as post, i (i)}
						<a class="mp" href={`/posts/${post.id}`} aria-hidden={i >= half}>
							{#if post.imageUrl}<img class="mp-img" src={post.imageUrl} alt="" loading="lazy" />{/if}
							{#if post.isVideo}<span class="mp-play"><Play size={12} fill="currentColor" /></span>{/if}
							<span class="mp-tag">
								{#if post.user?.avatarUrl}<img class="mp-av" src={post.user.avatarUrl} alt="" />{/if}
								<b>{post.user?.username}</b>{#if post.user?.verified}<BadgeCheck size={11} />{/if}
							</span>
						</a>
					{/each}
				</div>
				<div class="mrow rev">
					{#each rowB as post, i (i)}
						<a class="mp" href={`/posts/${post.id}`} aria-hidden={i >= rowB.length / 2}>
							{#if post.imageUrl}<img class="mp-img" src={post.imageUrl} alt="" loading="lazy" />{/if}
							{#if post.isVideo}<span class="mp-play"><Play size={12} fill="currentColor" /></span>{/if}
							<span class="mp-tag">
								{#if post.user?.avatarUrl}<img class="mp-av" src={post.user.avatarUrl} alt="" />{/if}
								<b>{post.user?.username}</b>{#if post.user?.verified}<BadgeCheck size={11} />{/if}
							</span>
						</a>
					{/each}
				</div>
			</div>
		</section>
	{/if}

	<!-- ===== ANNOUNCEMENTS + PRODUCTS ===== -->
	<section class="duo">
		{#if data.announcements.length}
			<div class="panel">
				<div class="panel-head"><h2><Megaphone size={17} /> Pengumuman</h2></div>
				<div class="ann-list">
					{#each data.announcements as a (a.id)}
						<article class="ann">
							{#if a.imageUrl}<img src={a.imageUrl} alt="" loading="lazy" />{:else}<span class="ann-ph"><Megaphone size={18} /></span>{/if}
							<div>
								<b>{#if a.pinned}<span class="pin">📌</span>{/if}{a.title}</b>
								<p>{a.excerpt}</p>
							</div>
						</article>
					{/each}
				</div>
			</div>
		{/if}

		{#if data.products.length}
			<div class="panel">
				<div class="panel-head">
					<h2><Store size={17} /> Marketplace</h2>
					<a class="more" href="https://marketplace.portalsi.com/">Semua <ArrowRight size={14} /></a>
				</div>
				<div class="prod-scroll">
					{#each data.products as p (p.id)}
						<a class="prod" href={p.url}>
							<span class="prod-img">{#if p.imageUrl}<img src={p.imageUrl} alt="" loading="lazy" />{/if}</span>
							<b>{p.name}</b>
							<span class="prod-price">{rupiah(p.price)}</span>
							{#if p.rating > 0}<span class="prod-rate"><Star size={11} fill="currentColor" /> {p.rating.toFixed(1)}</span>{/if}
						</a>
					{/each}
				</div>
			</div>
		{/if}
	</section>

	<footer class="foot">© {new Date().getFullYear()} Portal SI · Satu Portal, All In One.</footer>
</div>

{#if overlay}
	<div
		class="overlay"
		class:expand={expanding}
		style={`--x:${overlay.x}px;--y:${overlay.y}px;--c:${overlay.color}`}
	>
		<div class="ov-mark"><span class="spin"></span><span>{overlay.label}…</span></div>
	</div>
{/if}

<style>
	.land {
		--cream: #f5f0e8;
		--card: #fff;
		--ink: #1a1714;
		--ink-soft: #6a6155;
		--orange: #e86a17;
		--orange-dark: #b8530e;
		--green: #2a5e3a;
		--gold: #c99a2e;
		--border: rgb(26 23 20 / 8%);
		position: relative;
		min-height: 100vh;
		max-width: 1160px;
		margin: 0 auto;
		padding: 0 18px 40px;
		overflow: hidden;
		color: var(--ink);
		font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
	}
	:global(body) {
		background: #f3ede3;
	}
	.aura {
		position: fixed;
		inset: -20% -10% auto;
		height: 60vh;
		z-index: -1;
		background:
			radial-gradient(60% 60% at 18% 20%, rgb(232 106 23 / 20%), transparent 70%),
			radial-gradient(55% 55% at 85% 15%, rgb(42 94 58 / 18%), transparent 70%);
		filter: blur(20px);
		pointer-events: none;
	}
	.top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 18px 2px;
	}
	.brand {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		color: var(--ink);
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-weight: 800;
		font-size: 1.15rem;
		text-decoration: none;
	}
	.brand img {
		width: 24px;
		height: 24px;
	}
	.brand b {
		color: var(--orange);
	}
	.help {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 8px 14px;
		background: rgb(255 255 255 / 70%);
		border: 1px solid var(--border);
		border-radius: 999px;
		color: var(--ink-soft);
		font-size: 0.82rem;
		font-weight: 600;
		text-decoration: none;
		backdrop-filter: blur(8px);
	}
	.help:hover {
		background: #fff;
	}

	/* ===== BENTO ===== */
	.bento {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		grid-template-areas:
			'hero hero app  app'
			'hero hero meet mkt';
		grid-auto-rows: minmax(168px, 1fr);
		gap: 14px;
		margin-top: 6px;
	}
	.hero-tile {
		grid-area: hero;
		display: flex;
		flex-direction: column;
		padding: 30px 28px;
		background: linear-gradient(150deg, #fffdf9, #f6efe3);
		border: 1px solid var(--border);
		border-radius: 26px;
		box-shadow: 0 18px 40px -26px rgb(26 23 20 / 40%);
		animation: rise 0.5s ease both;
	}
	.salam {
		margin: 0 0 6px;
		color: var(--ink-soft);
		font-weight: 600;
		font-size: 0.9rem;
	}
	.hero-tile h1 {
		margin: 0;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-weight: 800;
		font-size: clamp(2.1rem, 4vw, 3.3rem);
		line-height: 1.03;
		letter-spacing: -0.02em;
	}
	.accent {
		background: linear-gradient(135deg, #e86a17, #f58a3e 35%, #2a5e3a 75%);
		background-size: 220% 220%;
		-webkit-background-clip: text;
		background-clip: text;
		color: transparent;
		animation: grad 5s ease-in-out infinite;
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
	.lead {
		margin: 14px 0 0;
		max-width: 30ch;
		color: var(--ink-soft);
		font-size: 0.96rem;
		line-height: 1.5;
	}
	.lead b {
		color: var(--orange-dark);
	}
	.stat-row {
		display: flex;
		gap: 10px;
		margin: auto 0 16px;
		padding-top: 18px;
	}
	.stat {
		display: flex;
		flex-direction: column;
		gap: 1px;
		padding: 10px 14px;
		background: rgb(255 255 255 / 70%);
		border: 1px solid var(--border);
		border-radius: 14px;
		color: var(--orange-dark);
	}
	.stat .v {
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-weight: 800;
		font-size: 1.1rem;
		color: var(--ink);
	}
	.stat .l {
		color: var(--ink-soft);
		font-size: 0.72rem;
	}
	.cta {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		align-self: flex-start;
		padding: 12px 20px;
		background: var(--ink);
		border-radius: 14px;
		color: #fff;
		font-weight: 700;
		font-size: 0.9rem;
		text-decoration: none;
		transition: transform 0.2s ease, background 0.2s ease;
	}
	.cta:hover {
		transform: translateY(-2px);
		background: #000;
	}

	/* service tiles */
	.svc {
		position: relative;
		display: flex;
		flex-wrap: wrap;
		align-content: flex-start;
		gap: 12px;
		padding: 20px;
		overflow: hidden;
		border-radius: 22px;
		background-size: cover !important;
		background-position: center !important;
		color: #fff;
		text-decoration: none;
		box-shadow: 0 16px 34px -22px rgb(26 23 20 / 60%);
		animation: rise 0.5s ease both;
		transition: transform 0.22s ease, box-shadow 0.22s ease;
	}
	.svc.app {
		grid-area: app;
		--g1: rgb(232 106 23 / 92%);
		--g2: rgb(155 66 8 / 88%);
	}
	.svc.meet {
		grid-area: meet;
		--g1: rgb(42 94 58 / 93%);
		--g2: rgb(18 56 33 / 90%);
	}
	.svc.mkt {
		grid-area: mkt;
		--g1: rgb(201 122 46 / 93%);
		--g2: rgb(42 94 58 / 88%);
	}
	.svc:nth-of-type(2) {
		animation-delay: 0.06s;
	}
	.svc:nth-of-type(3) {
		animation-delay: 0.12s;
	}
	.svc:hover {
		transform: translateY(-4px);
		box-shadow: 0 24px 44px -22px rgb(26 23 20 / 70%);
	}
	.svc-ico {
		display: grid;
		width: 42px;
		height: 42px;
		place-items: center;
		background: rgb(255 255 255 / 22%);
		border: 1px solid rgb(255 255 255 / 30%);
		border-radius: 13px;
		backdrop-filter: blur(4px);
	}
	.svc-body {
		flex: 1;
		min-width: 0;
		align-self: center;
	}
	.svc-body b {
		display: block;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.08rem;
		line-height: 1.1;
	}
	.svc-body small {
		opacity: 0.85;
		font-size: 0.78rem;
	}
	.svc-go {
		display: grid;
		width: 34px;
		height: 34px;
		place-items: center;
		background: rgb(255 255 255 / 20%);
		border-radius: 50%;
		transition: transform 0.2s ease, background 0.2s ease;
	}
	.svc:hover .svc-go {
		background: #fff;
		color: var(--ink);
		transform: rotate(45deg);
	}
	.svc-domain {
		width: 100%;
		margin-top: auto;
		padding-top: 6px;
		opacity: 0.85;
		font-size: 0.74rem;
	}
	.svc.app .svc-domain {
		margin-top: 30px;
	}

	/* ===== LIVE MARQUEE ===== */
	.live {
		margin-top: 30px;
	}
	.live-head,
	.panel-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 14px;
	}
	.live-head h2,
	.panel-head h2 {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin: 0;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.25rem;
	}
	.dot {
		width: 9px;
		height: 9px;
		border-radius: 50%;
		background: var(--orange);
		box-shadow: 0 0 0 0 rgb(232 106 23 / 60%);
		animation: pulse 1.8s ease-out infinite;
	}
	@keyframes pulse {
		to {
			box-shadow: 0 0 0 10px rgb(232 106 23 / 0%);
		}
	}
	.more {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		color: var(--orange-dark);
		font-size: 0.83rem;
		font-weight: 600;
		text-decoration: none;
	}
	.marquee {
		display: flex;
		flex-direction: column;
		gap: 12px;
		overflow: hidden;
		-webkit-mask-image: linear-gradient(90deg, transparent, #000 4%, #000 96%, transparent);
		mask-image: linear-gradient(90deg, transparent, #000 4%, #000 96%, transparent);
	}
	.mrow {
		display: flex;
		gap: 12px;
		width: max-content;
		animation: scrollL 42s linear infinite;
	}
	.mrow.rev {
		animation: scrollR 50s linear infinite;
	}
	.marquee:hover .mrow {
		animation-play-state: paused;
	}
	@keyframes scrollL {
		to {
			transform: translateX(-50%);
		}
	}
	@keyframes scrollR {
		from {
			transform: translateX(-50%);
		}
		to {
			transform: translateX(0);
		}
	}
	.mp {
		position: relative;
		width: 158px;
		height: 158px;
		flex: none;
		overflow: hidden;
		border-radius: 18px;
		background: var(--cream);
		box-shadow: 0 8px 20px -12px rgb(26 23 20 / 45%);
	}
	.mp-img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		transition: transform 0.35s ease;
	}
	.mp:hover .mp-img {
		transform: scale(1.07);
	}
	.mp-play {
		position: absolute;
		top: 8px;
		right: 8px;
		color: #fff;
		filter: drop-shadow(0 1px 2px rgb(0 0 0 / 55%));
	}
	.mp-tag {
		position: absolute;
		inset: auto 0 0;
		display: flex;
		align-items: center;
		gap: 5px;
		padding: 20px 9px 8px;
		background: linear-gradient(transparent, rgb(0 0 0 / 62%));
		color: #fff;
	}
	.mp-av {
		width: 20px !important;
		height: 20px !important;
		flex: none;
		object-fit: cover;
		border: 1.5px solid rgb(255 255 255 / 80%);
		border-radius: 50%;
	}
	.mp-tag b {
		overflow: hidden;
		font-size: 0.74rem;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	/* ===== DUO ===== */
	.duo {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 16px;
		margin-top: 30px;
	}
	.panel {
		padding: 18px;
		background: var(--card);
		border: 1px solid var(--border);
		border-radius: 22px;
		box-shadow: 0 14px 34px -26px rgb(26 23 20 / 50%);
	}
	.ann-list {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}
	.ann {
		display: flex;
		gap: 12px;
		align-items: center;
	}
	.ann img,
	.ann-ph {
		display: grid;
		width: 60px;
		height: 60px;
		flex: none;
		place-items: center;
		object-fit: cover;
		border-radius: 13px;
		background: var(--cream);
		color: var(--orange-dark);
	}
	.ann b {
		display: block;
		font-size: 0.9rem;
	}
	.pin {
		margin-right: 4px;
	}
	.ann p {
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		margin: 2px 0 0;
		color: var(--ink-soft);
		font-size: 0.8rem;
		line-height: 1.45;
	}
	.prod-scroll {
		display: flex;
		gap: 12px;
		overflow-x: auto;
		padding-bottom: 6px;
		scroll-snap-type: x mandatory;
	}
	.prod-scroll::-webkit-scrollbar {
		height: 5px;
	}
	.prod-scroll::-webkit-scrollbar-thumb {
		background: var(--border);
		border-radius: 999px;
	}
	.prod {
		display: flex;
		flex-direction: column;
		width: 130px;
		flex: none;
		scroll-snap-align: start;
		color: var(--ink);
		text-decoration: none;
	}
	.prod-img {
		display: block;
		aspect-ratio: 1;
		overflow: hidden;
		border-radius: 13px;
		background: var(--cream);
	}
	.prod-img img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		transition: transform 0.3s ease;
	}
	.prod:hover .prod-img img {
		transform: scale(1.06);
	}
	.prod b {
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		margin-top: 7px;
		font-size: 0.8rem;
		font-weight: 600;
		line-height: 1.3;
	}
	.prod-price {
		margin-top: 3px;
		color: var(--orange-dark);
		font-weight: 800;
		font-size: 0.84rem;
	}
	.prod-rate {
		display: inline-flex;
		align-items: center;
		gap: 3px;
		margin-top: 2px;
		color: var(--gold);
		font-size: 0.72rem;
		font-weight: 700;
	}
	.foot {
		margin-top: 34px;
		text-align: center;
		color: var(--ink-soft);
		font-size: 0.8rem;
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

	/* ===== overlay ===== */
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
	.ov-mark {
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
	.overlay.expand .ov-mark {
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

	/* ===== responsive ===== */
	@media (max-width: 820px) {
		.bento {
			grid-template-columns: 1fr 1fr;
			grid-template-areas:
				'hero hero'
				'app  app'
				'meet mkt';
			grid-auto-rows: minmax(140px, auto);
		}
		.duo {
			grid-template-columns: 1fr;
		}
	}
	@media (max-width: 520px) {
		.bento {
			grid-template-columns: 1fr;
			grid-template-areas:
				'hero'
				'app'
				'meet'
				'mkt';
		}
		.stat-row {
			flex-wrap: wrap;
		}
		.mp {
			width: 132px;
			height: 132px;
		}
	}
	@media (prefers-reduced-motion: reduce) {
		.accent,
		.dot,
		.mrow,
		.hero-tile,
		.svc {
			animation: none !important;
		}
	}
</style>
