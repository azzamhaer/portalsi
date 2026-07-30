<script lang="ts">
	import { ArrowRight, LogIn, Video, Store, Megaphone, Play, BadgeCheck, HelpCircle } from '@lucide/svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const rupiah = (n: number) =>
		new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);

	const apps = [
		{
			name: 'Portal SI App',
			desc: 'Media sosial Islami yang menghubungkan silaturahmi, berbagi inspirasi, dan bertumbuh dalam kebaikan.',
			href: 'https://app.portalsi.com/',
			domain: 'app.portalsi.com',
			icon: LogIn,
			accent: 'orange'
		},
		{
			name: 'Portal SI Meet',
			desc: 'Video meeting open source, instan dan gratis. Host login pakai akun Portal SI, peserta cukup dengan nama.',
			href: 'https://meet.portalsi.com/',
			domain: 'meet.portalsi.com',
			icon: Video,
			accent: 'green'
		},
		{
			name: 'Marketplace',
			desc: 'Marketplace terpercaya untuk menemukan produk, jasa, dan kebutuhan komunitas dalam satu tempat.',
			href: 'https://marketplace.portalsi.com/',
			domain: 'marketplace.portalsi.com',
			icon: Store,
			accent: 'orange'
		}
	];
</script>

<svelte:head>
	<title>Portal SI — Satu Portal, All In One</title>
	<meta name="description" content="Portal SI — media sosial Islami, video meeting, dan marketplace komunitas dalam satu tempat." />
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
	<link
		href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
		rel="stylesheet"
	/>
</svelte:head>

<div class="land">
	<div class="bg-glow glow-1"></div>
	<div class="bg-glow glow-2"></div>

	<header class="land-top">
		<a class="brand" href="/test-landing-page">
			<img src="https://portalsi.com/favicon.png" alt="" />
			<span>Portal <b>SI</b></span>
		</a>
		<a class="help" href="https://app.portalsi.com/welcome"><HelpCircle size={16} /> Bantuan</a>
	</header>

	<section class="hero">
		<p class="salam">Assalamu'alaikum 👋</p>
		<h1>Satu Portal,<br /><span class="grad">All In One.</span></h1>
		<p class="lead">Pilih layanan yang Anda butuhkan, <span class="hl">semua dalam satu tempat.</span></p>
	</section>

	<section class="apps">
		{#each apps as app (app.href)}
			{@const Icon = app.icon}
			<a class="app-card" class:green={app.accent === 'green'} href={app.href}>
				<span class="app-ico"><Icon size={22} /></span>
				<h3>{app.name}</h3>
				<p>{app.desc}</p>
				<div class="app-foot">
					<span>{app.domain}</span>
					<ArrowRight size={18} />
				</div>
			</a>
		{/each}
	</section>

	{#if data.posts.length}
		<section class="block">
			<div class="block-head">
				<h2>Yang sedang dibagikan</h2>
				<a href="https://app.portalsi.com/explore">Jelajahi <ArrowRight size={15} /></a>
			</div>
			<div class="posts">
				{#each data.posts as post (post.id)}
					<a class="post" href={`/posts/${post.id}`} aria-label={post.caption || 'Postingan'}>
						{#if post.imageUrl}
							<img src={post.imageUrl} alt="" loading="lazy" />
						{/if}
						{#if post.isVideo}<span class="post-badge"><Play size={14} fill="currentColor" /></span>{/if}
						<div class="post-over">
							{#if post.user}
								<span class="post-user">
									{#if post.user.avatarUrl}<img class="pu-av" src={post.user.avatarUrl} alt="" />{/if}
									<b>{post.user.username}</b>
									{#if post.user.verified}<BadgeCheck size={13} />{/if}
								</span>
							{/if}
						</div>
					</a>
				{/each}
			</div>
		</section>
	{/if}

	{#if data.announcements.length}
		<section class="block">
			<div class="block-head">
				<h2><Megaphone size={18} /> Pengumuman</h2>
			</div>
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
				<a href="https://marketplace.portalsi.com/">Lihat semua <ArrowRight size={15} /></a>
			</div>
			<div class="products">
				{#each data.products as p (p.id)}
					<a class="product" href={p.url}>
						<div class="prod-img">{#if p.imageUrl}<img src={p.imageUrl} alt="" loading="lazy" />{/if}</div>
						<div class="prod-body">
							<strong>{p.name}</strong>
							<div class="prod-price">
								<span>{rupiah(p.price)}</span>
								{#if p.originalPrice > p.price}<del>{rupiah(p.originalPrice)}</del>{/if}
							</div>
						</div>
					</a>
				{/each}
			</div>
		</section>
	{/if}

	<footer class="land-foot">© {new Date().getFullYear()} Portal SI. All rights reserved.</footer>
</div>

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
	.bg-glow {
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
	}
	.glow-2 {
		bottom: -160px;
		right: -160px;
		width: 440px;
		height: 440px;
		background: radial-gradient(circle, rgb(42 94 58 / 18%), transparent 70%);
	}
	.land > :not(.bg-glow) {
		position: relative;
		z-index: 1;
	}
	.land-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		max-width: 1080px;
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
	}
	.help:hover {
		background: var(--green-soft);
	}
	.hero {
		max-width: 720px;
		margin: 40px auto 8px;
		text-align: center;
	}
	.salam {
		margin: 0 0 10px;
		color: var(--ink-soft);
		font-weight: 600;
	}
	.hero h1 {
		margin: 0;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-weight: 800;
		font-size: clamp(2.4rem, 7vw, 4rem);
		line-height: 1.02;
		letter-spacing: -0.02em;
	}
	.grad {
		color: var(--orange);
	}
	.lead {
		margin: 20px auto 0;
		max-width: 460px;
		color: var(--ink-soft);
		font-size: 1.02rem;
	}
	.hl {
		color: var(--orange-dark);
		font-weight: 600;
	}
	.apps {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 18px;
		max-width: 1080px;
		margin: 40px auto 0;
	}
	.app-card {
		position: relative;
		display: flex;
		flex-direction: column;
		padding: 24px 22px;
		background: var(--card);
		border: 1px solid var(--border);
		border-top: 4px solid var(--orange);
		border-radius: 20px;
		box-shadow: 0 12px 30px rgb(26 23 20 / 6%);
		color: var(--ink);
		text-decoration: none;
		transition: transform 0.18s ease, box-shadow 0.18s ease;
	}
	.app-card.green {
		border-top-color: var(--green);
	}
	.app-card:hover {
		transform: translateY(-4px);
		box-shadow: 0 20px 44px rgb(26 23 20 / 12%);
	}
	.app-ico {
		display: grid;
		width: 46px;
		height: 46px;
		place-items: center;
		background: var(--orange-soft);
		border-radius: 14px;
		color: var(--orange-dark);
	}
	.app-card.green .app-ico {
		background: var(--green-soft);
		color: var(--green);
	}
	.app-card h3 {
		margin: 16px 0 8px;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.2rem;
	}
	.app-card p {
		margin: 0;
		color: var(--ink-soft);
		font-size: 0.9rem;
		line-height: 1.55;
	}
	.app-foot {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: 20px;
		padding-top: 14px;
		border-top: 1px solid var(--border);
		color: var(--ink-soft);
		font-size: 0.8rem;
	}
	.block {
		max-width: 1080px;
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
		font-size: 1.4rem;
	}
	.block-head a {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		color: var(--orange-dark);
		font-size: 0.85rem;
		font-weight: 600;
		text-decoration: none;
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
		border-radius: 16px;
		background: var(--cream);
	}
	.post img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		transition: transform 0.3s ease;
	}
	.post:hover img {
		transform: scale(1.05);
	}
	.post-badge {
		position: absolute;
		top: 8px;
		right: 8px;
		color: #fff;
		filter: drop-shadow(0 1px 2px rgb(0 0 0 / 50%));
	}
	.post-over {
		position: absolute;
		inset: auto 0 0;
		padding: 22px 10px 8px;
		background: linear-gradient(transparent, rgb(0 0 0 / 55%));
	}
	.post-user {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		color: #fff;
		font-size: 0.76rem;
	}
	.pu-av {
		width: 18px;
		height: 18px;
		border-radius: 50%;
		object-fit: cover;
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
		border-radius: 16px;
	}
	.ann img {
		width: 82px;
		height: 82px;
		flex: none;
		object-fit: cover;
		border-radius: 12px;
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
		overflow: hidden;
		background: var(--card);
		border: 1px solid var(--border);
		border-radius: 16px;
		color: var(--ink);
		text-decoration: none;
		transition: transform 0.18s ease, box-shadow 0.18s ease;
	}
	.product:hover {
		transform: translateY(-3px);
		box-shadow: 0 14px 30px rgb(26 23 20 / 10%);
	}
	.prod-img {
		aspect-ratio: 1;
		background: var(--cream);
	}
	.prod-img img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.prod-body {
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
	.prod-price {
		display: flex;
		align-items: baseline;
		gap: 6px;
		margin-top: 6px;
	}
	.prod-price span {
		color: var(--orange-dark);
		font-weight: 800;
		font-size: 0.9rem;
	}
	.prod-price del {
		color: var(--ink-soft);
		opacity: 0.6;
		font-size: 0.74rem;
	}
	.land-foot {
		max-width: 1080px;
		margin: 56px auto 0;
		text-align: center;
		color: var(--ink-soft);
		font-size: 0.82rem;
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
</style>
