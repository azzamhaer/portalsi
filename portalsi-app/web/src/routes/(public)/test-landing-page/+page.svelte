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
		MessageSquarePlus,
		Star,
		X,
		LoaderCircle
	} from '@lucide/svelte';
	import { enhance } from '$app/forms';
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
			long: 'Video meeting instan & gratis. Host login pakai akun Portal SI, peserta cukup dengan nama.',
			href: 'https://meet.portalsi.com/',
			domain: 'meet.portalsi.com',
			icon: Video,
			bg: 'https://portalsi.com/meet.webp',
			theme: 'meet',
			label: 'Membuka Meet'
		},
		{
			name: 'Marketplace',
			long: 'Temukan produk, jasa, dan kebutuhan komunitas dari penjual tepercaya dalam satu tempat.',
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

	let expandedId = $state<number | null>(null);
	let showAll = $state(false);
	const visibleAnns = $derived(showAll ? data.announcements : data.announcements.slice(0, 3));
	function toggle(id: number) {
		expandedId = expandedId === id ? null : id;
	}
	function roleLabel(role: string) {
		return { dev: 'Dev', teacher: 'Ustadz', parent: 'Wali' }[role] ?? '';
	}

	// ── Contact Us modal ──
	let contactOpen = $state(false);
	let captcha = $state(data.captcha);
	let captchaAnswer = $state('');
	let submitting = $state(false);
	let feedback = $state<{ ok: boolean; text: string } | null>(null);
	let sent = $state(false);
	function openContact() {
		contactOpen = true;
		feedback = null;
		sent = false;
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
								<button class="pa-toggle" onclick={() => toggle(a.id)}>
									{#if a.pinned}<span class="pin">📌</span>{/if}
									<span class="pa-title">{a.title}</span>
									<ChevronDown size={16} class="chev" />
								</button>
								{#if a.author}
									<a class="pa-author" href={a.author.url} title={a.author.fullName}>
										{#if a.author.avatarUrl}<img src={a.author.avatarUrl} alt="" />{/if}
										<span>@{a.author.username}</span>
										{#if a.author.verified}<BadgeCheck size={12} class="v" />{/if}
										{#if roleLabel(a.author.role)}<i class="role">{roleLabel(a.author.role)}</i>{/if}
									</a>
								{/if}
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
		</div>

		{#each apps as app (app.href)}
			{@const Icon = app.icon}
			<a
				class="svc {app.theme}"
				href={app.href}
				style={`background-image:linear-gradient(140deg,var(--g1),var(--g2)),url('${app.bg}')`}
				onclick={(e) => launch(e, app)}
			>
				<span class="svc-top">
					<span class="svc-ico"><Icon size={20} /></span>
					<span class="svc-go"><ArrowUpRight size={18} /></span>
				</span>
				<b class="svc-name">{app.name}</b>
				<p class="svc-desc">{app.long}</p>
				<span class="svc-domain">{app.domain}</span>
			</a>
		{/each}

		<button class="svc contact" onclick={openContact}>
			<span class="svc-top">
				<span class="svc-ico"><MessageSquarePlus size={20} /></span>
				<span class="svc-go"><ArrowUpRight size={18} /></span>
			</span>
			<b class="svc-name">Contact Us</b>
			<p class="svc-desc">Punya saran, kritik, atau pertanyaan? Kirim langsung ke tim kami.</p>
			<span class="svc-domain">Buka formulir saran</span>
		</button>
	</section>

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

<!-- ===== CONTACT MODAL ===== -->
{#if contactOpen}
	<div class="cm-scrim" role="presentation" onclick={() => (contactOpen = false)}>
		<form
			class="cm-card"
			method="POST"
			action="?/contact"
			onclick={(e) => e.stopPropagation()}
			use:enhance={() => {
				submitting = true;
				feedback = null;
				return async ({ result }) => {
					submitting = false;
					if (result.type === 'success' && (result.data as any)?.success) {
						sent = true;
						feedback = { ok: true, text: String((result.data as any).message ?? 'Terkirim!') };
					} else if (result.type === 'failure') {
						const d = result.data as any;
						feedback = { ok: false, text: String(d?.message ?? 'Gagal mengirim.') };
						if (d?.captcha) captcha = d.captcha;
						captchaAnswer = '';
					} else {
						feedback = { ok: false, text: 'Gagal mengirim. Coba lagi.' };
					}
				};
			}}
		>
			<header class="cm-head">
				<div><strong>Contact Us</strong><small>Kirim saran atau pertanyaan ke tim Portal SI.</small></div>
				<button type="button" class="cm-x" onclick={() => (contactOpen = false)} aria-label="Tutup"><X size={18} /></button>
			</header>

			{#if sent}
				<div class="cm-done">
					<span class="cm-check"><BadgeCheck size={30} /></span>
					<p>{feedback?.text}</p>
					<button type="button" class="cm-submit" onclick={() => (contactOpen = false)}>Tutup</button>
				</div>
			{:else}
				<label class="cm-field">Nama<input name="name" maxlength="120" required placeholder="Nama Anda" /></label>
				<label class="cm-field">Email<input name="email" type="email" maxlength="190" required placeholder="email@contoh.com" /></label>
				<label class="cm-field">No. telepon <em>(opsional)</em><input name="phone" maxlength="40" placeholder="08xxxxxxxxxx" /></label>
				<label class="cm-field">Pesan / saran<textarea name="message" rows="3" maxlength="2000" required placeholder="Tulis pesan Anda…"></textarea></label>

				<div class="cm-captcha">
					<span class="cm-q">Berapa hasil <b>{captcha.question || '…'}</b>?</span>
					<input
						class="cm-ans"
						name="captcha_answer"
						inputmode="numeric"
						autocomplete="off"
						required
						bind:value={captchaAnswer}
						placeholder="?"
					/>
					<input type="hidden" name="captcha_token" value={captcha.token} />
				</div>

				{#if feedback && !feedback.ok}<p class="cm-err">{feedback.text}</p>{/if}

				<button class="cm-submit" type="submit" disabled={submitting}>
					{#if submitting}<LoaderCircle size={16} class="cm-spin" /> Mengirim…{:else}Kirim saran{/if}
				</button>
				<p class="cm-note">Maksimal 3 pesan per hari.</p>
			{/if}
		</form>
	</div>
{/if}

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
			'hero hero app  meet'
			'hero hero mkt  contact';
		grid-auto-rows: minmax(196px, 1fr);
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
		margin-top: 16px;
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
		gap: 7px;
		overflow-y: auto;
		max-height: 260px;
	}
	.pa {
		padding: 9px 11px;
		background: rgb(255 255 255 / 60%);
		border: 1px solid var(--border);
		border-radius: 13px;
	}
	.pa-toggle {
		display: flex;
		align-items: center;
		gap: 7px;
		width: 100%;
		padding: 0;
		background: none;
		border: 0;
		color: var(--ink);
		font-size: 0.86rem;
		font-weight: 700;
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
	.pa-author {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		max-width: 100%;
		margin-top: 7px;
		padding: 3px 9px 3px 3px;
		background: var(--cream);
		border-radius: 999px;
		color: var(--ink);
		font-size: 0.74rem;
		font-weight: 600;
		text-decoration: none;
	}
	.pa-author:hover {
		background: var(--orange);
		color: #fff;
	}
	.pa-author img {
		width: 19px;
		height: 19px;
		border-radius: 50%;
		object-fit: cover;
	}
	.pa-author span {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.pa-author :global(.v) {
		flex: none;
		color: var(--orange);
	}
	.pa-author:hover :global(.v) {
		color: #fff;
	}
	.role {
		flex: none;
		padding: 1px 6px;
		background: rgb(42 94 58 / 12%);
		border-radius: 999px;
		color: var(--green);
		font-size: 0.6rem;
		font-style: normal;
		font-weight: 700;
	}
	.pa-author:hover .role {
		background: rgb(255 255 255 / 25%);
		color: #fff;
	}
	.pa-body {
		margin-top: 8px;
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

	/* ===== service + contact tiles ===== */
	.svc {
		position: relative;
		display: flex;
		flex-direction: column;
		padding: 18px;
		overflow: hidden;
		border-radius: 22px;
		background-size: cover !important;
		background-position: center !important;
		color: #fff;
		text-align: left;
		text-decoration: none;
		box-shadow: 0 16px 34px -22px rgb(26 23 20 / 60%);
		animation: rise 0.5s ease both;
		transition: transform 0.22s ease, box-shadow 0.22s ease;
		cursor: pointer;
	}
	.svc.app {
		grid-area: app;
		--g1: rgb(232 106 23 / 92%);
		--g2: rgb(155 66 8 / 9%);
	}
	.svc.meet {
		grid-area: meet;
		--g1: rgb(42 94 58 / 93%);
		--g2: rgb(18 56 33 / 9%);
	}
	.svc.mkt {
		grid-area: mkt;
		--g1: rgb(201 122 46 / 93%);
		--g2: rgb(42 94 58 / 9%);
	}
	.svc.contact {
		grid-area: contact;
		border: 0;
		background: linear-gradient(140deg, #2b2621, #4a4038);
	}
	.svc:nth-of-type(3) {
		animation-delay: 0.06s;
	}
	.svc:nth-of-type(4) {
		animation-delay: 0.12s;
	}
	.svc:hover {
		transform: translateY(-4px);
		box-shadow: 0 24px 44px -22px rgb(26 23 20 / 70%);
	}
	.svc-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 14px;
	}
	.svc-ico {
		display: grid;
		width: 40px;
		height: 40px;
		place-items: center;
		background: rgb(255 255 255 / 22%);
		border: 1px solid rgb(255 255 255 / 30%);
		border-radius: 12px;
		backdrop-filter: blur(4px);
	}
	.svc-go {
		display: grid;
		width: 32px;
		height: 32px;
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
	.svc-name {
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.12rem;
		line-height: 1.1;
	}
	.svc-desc {
		margin: 7px 0 0;
		color: rgb(255 255 255 / 88%);
		font-size: 0.82rem;
		line-height: 1.45;
	}
	.svc-domain {
		margin-top: auto;
		padding-top: 12px;
		opacity: 0.85;
		font-size: 0.74rem;
	}

	/* ===== section heads ===== */
	.live,
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
		inset: auto 8px 8px;
		display: flex;
		align-items: center;
		gap: 5px;
		padding: 5px 9px 5px 5px;
		border-radius: 999px;
		background: linear-gradient(transparent, rgb(0 0 0 / 62%));
		color: #fff;
		text-decoration: none;
		transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
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
	@media (hover: hover) {
		.mp-tag:hover {
			background: var(--orange);
			transform: translateY(-2px);
			box-shadow: 0 8px 18px -8px rgb(232 106 23 / 80%);
		}
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

	/* ===== contact modal ===== */
	.cm-scrim {
		position: fixed;
		inset: 0;
		z-index: 900;
		display: grid;
		place-items: center;
		padding: 16px;
		background: rgb(15 12 9 / 60%);
		backdrop-filter: blur(4px);
		animation: fade 0.2s ease;
	}
	.cm-card {
		width: min(100%, 440px);
		max-height: 92vh;
		overflow-y: auto;
		padding: 20px;
		background: #fff;
		border-radius: 22px;
		box-shadow: 0 30px 70px rgb(0 0 0 / 45%);
		animation: pop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
	}
	.cm-head {
		display: flex;
		align-items: flex-start;
		gap: 10px;
		margin-bottom: 14px;
	}
	.cm-head strong {
		display: block;
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.1rem;
	}
	.cm-head small {
		color: var(--ink-soft);
		font-size: 0.8rem;
	}
	.cm-x {
		display: grid;
		width: 32px;
		height: 32px;
		flex: none;
		margin-left: auto;
		place-items: center;
		padding: 0;
		background: var(--cream);
		border: 0;
		border-radius: 50%;
		color: var(--ink-soft);
		cursor: pointer;
	}
	.cm-field {
		display: block;
		margin-bottom: 10px;
		color: var(--ink);
		font-size: 0.8rem;
		font-weight: 600;
	}
	.cm-field em {
		color: var(--ink-soft);
		font-weight: 400;
	}
	.cm-field input,
	.cm-field textarea {
		width: 100%;
		margin-top: 4px;
		padding: 10px 12px;
		background: var(--cream);
		border: 1px solid var(--border);
		border-radius: 11px;
		font: inherit;
		font-weight: 400;
		resize: vertical;
	}
	.cm-field input:focus,
	.cm-field textarea:focus,
	.cm-ans:focus {
		outline: 2px solid var(--orange);
		outline-offset: 0;
	}
	.cm-captcha {
		display: flex;
		align-items: center;
		gap: 10px;
		margin: 4px 0 12px;
		padding: 10px 12px;
		background: var(--cream);
		border: 1px solid var(--border);
		border-radius: 12px;
	}
	.cm-q {
		flex: 1;
		font-size: 0.86rem;
		font-weight: 600;
	}
	.cm-q b {
		color: var(--orange-dark);
	}
	.cm-ans {
		width: 74px;
		padding: 8px 10px;
		background: #fff;
		border: 1px solid var(--border);
		border-radius: 10px;
		font: inherit;
		text-align: center;
	}
	.cm-err {
		margin: 0 0 10px;
		color: #c0392b;
		font-size: 0.82rem;
		font-weight: 600;
	}
	.cm-submit {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 7px;
		width: 100%;
		min-height: 46px;
		background: var(--ink);
		border: 0;
		border-radius: 13px;
		color: #fff;
		font-size: 0.9rem;
		font-weight: 700;
		cursor: pointer;
	}
	.cm-submit:disabled {
		opacity: 0.6;
		cursor: default;
	}
	.cm-note {
		margin: 8px 0 0;
		text-align: center;
		color: var(--ink-soft);
		font-size: 0.72rem;
	}
	.cm-done {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 10px;
		padding: 14px 0 4px;
		text-align: center;
	}
	.cm-check {
		display: grid;
		width: 58px;
		height: 58px;
		place-items: center;
		background: var(--green-soft, #e1efe2);
		border-radius: 50%;
		color: var(--green);
	}
	.cm-done p {
		margin: 0;
		color: var(--ink-soft);
		font-size: 0.9rem;
	}
	:global(.cm-spin) {
		animation: spin 0.8s linear infinite;
	}
	@keyframes fade {
		from {
			opacity: 0;
		}
	}
	@keyframes pop {
		from {
			opacity: 0;
			transform: translateY(14px) scale(0.97);
		}
	}

	/* ===== overlay transisi ===== */
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
				'app  meet'
				'mkt  contact';
			grid-auto-rows: minmax(168px, auto);
		}
	}
	@media (max-width: 520px) {
		.bento {
			grid-template-columns: 1fr;
			grid-template-areas:
				'hero'
				'app'
				'meet'
				'mkt'
				'contact';
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
