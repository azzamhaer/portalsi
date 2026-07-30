<script lang="ts">
	import {
		ArrowRight,
		ArrowUpRight,
		ChevronDown,
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
			tag: 'Sosial media Islami',
			long: 'Media sosial Islami yang menghubungkan silaturahmi, berbagi inspirasi, dan bertumbuh dalam kebaikan.',
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
			long: 'Video meeting open source, instan dan gratis. Host login pakai akun Portal SI, peserta bisa gabung cukup dengan nama.',
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
			long: 'Marketplace terpercaya untuk menemukan produk, jasa, dan kebutuhan komunitas dalam satu tempat.',
			href: 'https://marketplace.portalsi.com/',
			domain: 'marketplace.portalsi.com',
			icon: Store,
			bg: 'https://portalsi.com/marketplace.webp',
			theme: 'mkt',
			label: 'Membuka Marketplace'
		}
	];

	const half = Math.ceil(data.posts.length / 2);
	const rowA = $derived([...data.posts.slice(0, half), ...data.posts.slice(0, half)]);
	const rowB = $derived([...data.posts.slice(half), ...data.posts.slice(half)]);

	// Pengumuman: accordion di kartu hero (bisa di-expand; tampilkan sebagian dulu bila banyak).
	let expandedId = $state<number | null>(null);
	let showAll = $state(false);
	const visibleAnns = $derived(showAll ? data.announcements : data.announcements.slice(0, 3));
	function toggle(id: number) {
		expandedId = expandedId === id ? null : id;
	}
	function roleLabel(role: string) {
		return { dev: 'Dev', teacher: 'Ustadz', parent: 'Wali', student: '' }[role] ?? '';
	}

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

			{#if data.announcements.length}
				<div class="ann">
					<div class="ann-head"><Megaphone size={15} /> Pengumuman</div>
					<div class="ann-items">
						{#each visibleAnns as a (a.id)}
							<article class="pa" class:open={expandedId === a.id}>
								<div class="pa-row">
									{#if a.author}
										<a class="pa-author" href={a.author.url} title={a.author.fullName}>
											{#if a.author.avatarUrl}<img src={a.author.avatarUrl} alt="" />{/if}
											<span>@{a.author.username}</span>
											{#if a.author.verified}<BadgeCheck size={13} class="v" />{/if}
											{#if roleLabel(a.author.role)}<i class="role">{roleLabel(a.author.role)}</i>{/if}
										</a>
									{/if}
									<button class="pa-toggle" onclick={() => toggle(a.id)}>
										{#if a.pinned}<span class="pin">📌</span>{/if}
										<span class="pa-title">{a.title}</span>
										<ChevronDown size={16} class="chev" />
									</button>
								</div>
								{#if expandedId === a.id}
									<div class="pa-body">
										{#if a.content}<p>{a.content}</p>{/if}
										{#if a.imageUrl}<img class="pa-photo" src={a.imageUrl} alt="" />{/if}
									</div>
								{/if}
							</article>
						{/each}
					</div>
					{#if data.announcements.length > 3}
						<button class="ann-all" onclick={() => (showAll = !showAll)}>
							{showAll ? 'Tampilkan sedikit' : `Lihat ${data.announcements.length - 3} lainnya`}
						</button>
					{/if}
				</div>
			{/if}

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
			<div class="sec-head">
				<h2><span class="dot"></span> Denyut komunitas</h2>
				<a class="more" href="https://app.portalsi.com/explore">Jelajahi <ArrowRight size={14} /></a>
			</div>
			<div class="marquee">
				{#each [rowA, rowB] as row, r (r)}
					<div class="mrow" class:rev={r === 1}>
						{#each row as post, i (i)}
							<div class="mp" aria-hidden={i >= row.length / 2}>
								<a class="mp-media" href={`/posts/${post.id}`} aria-label={post.caption || 'Postingan'}>
									{#if post.imageUrl}<img src={post.imageUrl} alt="" loading="lazy" />{/if}
									{#if post.isVideo}<span class="mp-play"><Play size={12} fill="currentColor" /></span>{/if}
								</a>
								{#if post.user?.username}
									<a class="mp-tag" href={`https://app.portalsi.com/u/${post.user.username}`}>
										{#if post.user.avatarUrl}<img class="mp-av" src={post.user.avatarUrl} alt="" />{/if}
										<b>{post.user.username}</b>{#if post.user.verified}<BadgeCheck size={11} />{/if}
									</a>
								{/if}
							</div>
						{/each}
					</div>
				{/each}
			</div>
		</section>
	{/if}

	<!-- ===== PENJELASAN LAYANAN ===== -->
	<section class="about">
		<div class="sec-head"><h2>Kenapa Portal SI?</h2></div>
		<div class="about-grid">
			{#each apps as app (app.href)}
				{@const Icon = app.icon}
				<div class="ab {app.theme}">
					<span class="ab-ico"><Icon size={20} /></span>
					<b>{app.name}</b>
					<p>{app.long}</p>
					<a class="ab-link" href={app.href}>{app.domain} <ArrowUpRight size={14} /></a>
				</div>
			{/each}
		</div>
	</section>

	<!-- ===== PRODUCTS ===== -->
	{#if data.products.length}
		<section class="mkt">
			<div class="sec-head">
				<h2><Store size={18} /> Dari Marketplace</h2>
				<a class="more" href="https://marketplace.portalsi.com/">Lihat semua <ArrowRight size={14} /></a>
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
		</section>
	{/if}

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
		grid-auto-rows: minmax(172px, 1fr);
		gap: 14px;
		margin-top: 6px;
	}
	.hero-tile {
		grid-area: hero;
		display: flex;
		flex-direction: column;
		min-height: 0;
		padding: 28px 26px;
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
		font-size: clamp(2rem, 3.6vw, 3.1rem);
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
		margin: 12px 0 0;
		max-width: 34ch;
		color: var(--ink-soft);
		font-size: 0.94rem;
		line-height: 1.5;
	}
	.lead b {
		color: var(--orange-dark);
	}

	/* announcements accordion */
	.ann {
		display: flex;
		flex-direction: column;
		min-height: 0;
		margin: 16px 0;
		padding-top: 14px;
		border-top: 1px solid var(--border);
	}
	.ann-head {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		margin-bottom: 8px;
		color: var(--ink-soft);
		font-size: 0.74rem;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}
	.ann-items {
		display: flex;
		flex-direction: column;
		gap: 6px;
		overflow-y: auto;
		max-height: 240px;
	}
	.pa {
		background: rgb(255 255 255 / 60%);
		border: 1px solid var(--border);
		border-radius: 13px;
	}
	.pa-row {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 10px;
	}
	.pa-author {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		flex: none;
		max-width: 42%;
		padding: 3px 8px 3px 3px;
		background: var(--cream);
		border-radius: 999px;
		color: var(--ink);
		font-size: 0.76rem;
		font-weight: 600;
		text-decoration: none;
	}
	.pa-author:hover {
		background: var(--orange);
		color: #fff;
	}
	.pa-author img {
		width: 20px;
		height: 20px;
		border-radius: 50%;
		object-fit: cover;
	}
	.pa-author span {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.pa-author :global(.v) {
		color: var(--orange);
	}
	.pa-author:hover :global(.v) {
		color: #fff;
	}
	.role {
		padding: 1px 6px;
		background: rgb(42 94 58 / 12%);
		border-radius: 999px;
		color: var(--green);
		font-size: 0.62rem;
		font-style: normal;
		font-weight: 700;
	}
	.pa-author:hover .role {
		background: rgb(255 255 255 / 25%);
		color: #fff;
	}
	.pa-toggle {
		display: flex;
		align-items: center;
		gap: 6px;
		flex: 1;
		min-width: 0;
		padding: 0;
		background: none;
		border: 0;
		color: var(--ink);
		font-size: 0.84rem;
		font-weight: 600;
		text-align: left;
		cursor: pointer;
	}
	.pin {
		flex: none;
	}
	.pa-title {
		overflow: hidden;
		flex: 1;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.pa-toggle :global(.chev) {
		flex: none;
		color: var(--ink-soft);
		transition: transform 0.2s ease;
	}
	.pa.open .pa-toggle :global(.chev) {
		transform: rotate(180deg);
	}
	.pa-body {
		padding: 0 12px 12px;
		color: var(--ink-soft);
		font-size: 0.84rem;
		line-height: 1.55;
	}
	.pa-body p {
		margin: 0 0 8px;
		white-space: pre-line;
	}
	.pa-photo {
		width: 100%;
		max-height: 220px;
		object-fit: cover;
		border-radius: 11px;
	}
	.ann-all {
		align-self: flex-start;
		margin-top: 8px;
		padding: 0;
		background: none;
		border: 0;
		color: var(--orange-dark);
		font-size: 0.8rem;
		font-weight: 700;
		cursor: pointer;
	}
	.cta {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		align-self: flex-start;
		margin-top: auto;
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
		transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
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

	/* ===== section heads ===== */
	.live,
	.about,
	.mkt {
		margin-top: 30px;
	}
	.sec-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 14px;
	}
	.sec-head h2 {
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

	/* ===== marquee ===== */
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
	}
	.mp-media {
		display: block;
		width: 100%;
		height: 100%;
		overflow: hidden;
		border-radius: 18px;
		background: var(--cream);
		box-shadow: 0 8px 20px -12px rgb(26 23 20 / 45%);
	}
	.mp-media img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		transition: transform 0.35s ease;
	}
	.mp:hover .mp-media img {
		transform: scale(1.07);
	}
	.mp-play {
		position: absolute;
		top: 8px;
		right: 8px;
		color: #fff;
		filter: drop-shadow(0 1px 2px rgb(0 0 0 / 55%));
		pointer-events: none;
	}
	.mp-tag {
		position: absolute;
		inset: auto 6px 6px;
		display: flex;
		align-items: center;
		gap: 5px;
		padding: 5px 8px;
		border-radius: 999px;
		background: linear-gradient(transparent, rgb(0 0 0 / 62%));
		color: #fff;
		text-decoration: none;
		transition: background 0.2s ease, transform 0.2s ease;
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
	/* Desktop: hover username → chip solid berwarna, rounded, jelas bisa diklik. */
	@media (hover: hover) {
		.mp-tag {
			inset: auto 8px 8px;
		}
		.mp-tag:hover {
			background: var(--orange);
			transform: translateY(-2px);
			box-shadow: 0 8px 18px -8px rgb(232 106 23 / 80%);
		}
	}

	/* ===== about ===== */
	.about-grid {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 14px;
	}
	.ab {
		display: flex;
		flex-direction: column;
		padding: 20px;
		background: var(--card);
		border: 1px solid var(--border);
		border-top: 3px solid var(--orange);
		border-radius: 20px;
		box-shadow: 0 14px 34px -26px rgb(26 23 20 / 50%);
	}
	.ab.meet {
		border-top-color: var(--green);
	}
	.ab.mkt {
		border-top-color: var(--gold);
	}
	.ab-ico {
		display: grid;
		width: 40px;
		height: 40px;
		place-items: center;
		margin-bottom: 12px;
		background: var(--cream);
		border-radius: 12px;
		color: var(--orange-dark);
	}
	.ab.meet .ab-ico {
		color: var(--green);
	}
	.ab b {
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.08rem;
	}
	.ab p {
		flex: 1;
		margin: 6px 0 14px;
		color: var(--ink-soft);
		font-size: 0.9rem;
		line-height: 1.55;
	}
	.ab-link {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		color: var(--orange-dark);
		font-size: 0.8rem;
		font-weight: 700;
		text-decoration: none;
	}

	/* ===== products ===== */
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
		width: 150px;
		flex: none;
		scroll-snap-align: start;
		color: var(--ink);
		text-decoration: none;
	}
	.prod-img {
		display: block;
		aspect-ratio: 1;
		overflow: hidden;
		border-radius: 14px;
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
		margin-top: 8px;
		font-size: 0.82rem;
		font-weight: 600;
		line-height: 1.3;
	}
	.prod-price {
		margin-top: 3px;
		color: var(--orange-dark);
		font-weight: 800;
		font-size: 0.86rem;
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
			grid-auto-rows: minmax(150px, auto);
		}
		.about-grid {
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
		.mp {
			width: 132px;
			height: 132px;
		}
		.pa-author {
			max-width: 46%;
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
