<script lang="ts">
	import { LogOut, LayoutGrid, HelpCircle } from '@lucide/svelte';
	import { page } from '$app/stores';
	let { data, children } = $props();
	let path = $derived($page.url.pathname);
	let isAuth = $derived(path === '/login' || path === '/register');
	let isApp = $derived(path === '/');
	let flush = $derived(isApp || isAuth);
	let menuOpen = $state(false);
</script>

<svelte:head>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
	<link
		href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap"
		rel="stylesheet"
	/>
</svelte:head>

<div class="wrap">
	{#if !isAuth}
		<header class="top">
			<a class="brand" href="/" aria-label="SI Mail">
				<img src="/favicon.png" alt="SI Mail" />
			</a>
			{#if data.user}
				<div class="appswitch">
					<button class="quick" class:active={menuOpen} onclick={() => (menuOpen = !menuOpen)} aria-label="Menu" title="Menu"><LayoutGrid size={18} /></button>
					{#if menuOpen}
						<button class="menu-backdrop" onclick={() => (menuOpen = false)} aria-label="Tutup"></button>
						<div class="menu">
							<a href="https://wa.me/6281350880733?text=Assalamu%27alaikum%20wr.%20wb.%20%F0%9F%99%8F%0A%0ASaya%20ingin%20bertanya%20seputar%20layanan%20SI%20Mail.%20Boleh%20dibantu%3F%20Terima%20kasih%20sebelumnya." target="_blank" rel="noopener" class="menu-row"><HelpCircle size={16} /> Bantuan</a>
							<form method="POST" action="/logout"><button class="menu-row danger" type="submit"><LogOut size={16} /> Keluar</button></form>
						</div>
					{/if}
				</div>
			{/if}
		</header>
	{/if}
	<main class="main" class:flush>
		{@render children()}
	</main>
</div>

<style>
	:global(body) {
		margin: 0;
		background: #f3ede3;
		font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
		color: #1a1714;
		line-height: 1.5;
	}
	:global(*) {
		box-sizing: border-box;
	}
	.wrap {
		min-height: 100vh;
		display: flex;
		flex-direction: column;
	}
	.top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 56px;
		padding: 0 20px;
		border-bottom: 1px solid rgba(26, 23, 20, 0.08);
		background: #fff;
	}
	.brand {
		display: inline-flex;
		align-items: center;
		text-decoration: none;
	}
	.brand img {
		width: 34px;
		height: 34px;
		border-radius: 9px;
		display: block;
	}
	.appswitch {
		position: relative;
		display: flex;
		align-items: center;
		gap: 4px;
	}
	.quick {
		display: grid;
		place-items: center;
		width: 40px;
		height: 40px;
		border: 0;
		border-radius: 50%;
		background: transparent;
		color: #5f6368;
		cursor: pointer;
		text-decoration: none;
		transition: background 0.13s;
	}
	.quick:hover {
		background: #eef1f5;
		color: #1f6feb;
	}
	.quick.active {
		background: #eef1f5;
		color: #1f6feb;
		box-shadow: inset 0 0 0 1px #d5dae2;
	}
	.menu-backdrop {
		position: fixed;
		inset: 0;
		background: transparent;
		border: 0;
		z-index: 90;
		cursor: default;
	}
	.menu {
		position: absolute;
		top: 48px;
		right: 0;
		width: 260px;
		background: #fff;
		border: 1px solid #e6e9ef;
		border-radius: 16px;
		box-shadow: 0 18px 44px rgba(0, 0, 0, 0.16);
		padding: 12px;
		z-index: 95;
		animation: menuin 0.16s ease;
	}
	@keyframes menuin {
		from { opacity: 0; transform: translateY(-6px); }
		to { opacity: 1; transform: translateY(0); }
	}
	.menu-grid {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 4px;
	}
	.mg-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 6px;
		padding: 10px 4px;
		border-radius: 12px;
		text-decoration: none;
		color: #3c4043;
		font-size: 0.72rem;
		font-weight: 600;
		transition: background 0.12s;
	}
	.mg-item:hover {
		background: #f2f5f9;
	}
	.mg-ico {
		display: grid;
		place-items: center;
		width: 40px;
		height: 40px;
		border-radius: 12px;
		color: #fff;
	}
	.mg-ico.app { background: #e86a17; }
	.mg-ico.meet { background: #0b8043; }
	.mg-ico.market { background: #e3b100; }
	.mg-ico.web { background: #1f6feb; }
	.menu-sep {
		height: 1px;
		background: #eef1f5;
		margin: 8px 2px;
	}
	.menu-row {
		display: flex;
		align-items: center;
		gap: 10px;
		width: 100%;
		padding: 9px 10px;
		border: 0;
		border-radius: 10px;
		background: transparent;
		color: #3c4043;
		font-size: 0.86rem;
		font-weight: 500;
		text-decoration: none;
		cursor: pointer;
		text-align: left;
	}
	.menu-row:hover {
		background: #f2f5f9;
	}
	.menu-row.danger {
		color: #c0392b;
	}
	.menu-row.danger:hover {
		background: #fdecea;
	}
	.menu form {
		margin: 0;
	}
	/* dark mode header */
	:global(html.psdark) .top {
		background: #12161c;
		border-bottom-color: #252b34;
	}
	:global(html.psdark) .quick {
		color: #9aa4b2;
	}
	:global(html.psdark) .quick:hover,
	:global(html.psdark) .quick.active {
		background: #1b2029;
		color: #7fb0ff;
		box-shadow: none;
	}
	:global(html.psdark) .menu {
		background: #1b2029;
		border-color: #2c333d;
	}
	:global(html.psdark) .mg-item,
	:global(html.psdark) .menu-row {
		color: #c3ccd8;
	}
	:global(html.psdark) .mg-item:hover,
	:global(html.psdark) .menu-row:hover {
		background: #222831;
	}
	:global(html.psdark) .menu-sep {
		background: #2c333d;
	}
	.main {
		flex: 1;
		display: flex;
		justify-content: center;
		padding: 34px 18px 56px;
	}
	.main.flush {
		display: block;
		padding: 0;
	}

	/* ── kartu & form dipakai lintas halaman ── */
	:global(.card) {
		width: min(100%, 430px);
		align-self: flex-start;
		background: #fff;
		border: 1px solid rgba(26, 23, 20, 0.08);
		border-radius: 18px;
		padding: 26px 24px;
		box-shadow: 0 18px 40px -26px rgba(26, 23, 20, 0.4);
	}
	:global(.card h1) {
		font-family: 'Plus Jakarta Sans', sans-serif;
		font-size: 1.5rem;
		margin: 0 0 6px;
	}
	:global(.card .sub) {
		color: #6a6155;
		margin: 0 0 18px;
		font-size: 0.92rem;
	}
	:global(.field) {
		display: block;
		margin-bottom: 12px;
		font-size: 0.85rem;
		font-weight: 600;
		color: #3d352a;
	}
	:global(.field input) {
		width: 100%;
		margin-top: 5px;
		padding: 11px 12px;
		border: 1px solid rgba(26, 23, 20, 0.14);
		border-radius: 11px;
		font: inherit;
		font-weight: 400;
		background: #faf7f2;
	}
	:global(.field input:focus) {
		outline: 2px solid #1f6feb;
		background: #fff;
	}
	:global(.btn) {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		width: 100%;
		min-height: 46px;
		border: 0;
		border-radius: 12px;
		background: #1f6feb;
		color: #fff;
		font-weight: 700;
		font-size: 0.95rem;
		cursor: pointer;
	}
	:global(.btn:disabled) {
		opacity: 0.6;
		cursor: default;
	}
	:global(.btn.orange) {
		background: #e86a17;
	}
	:global(.err) {
		background: #fdecea;
		color: #b0281a;
		border: 1px solid #f5c6c0;
		border-radius: 10px;
		padding: 9px 12px;
		font-size: 0.85rem;
		margin-bottom: 12px;
	}
	:global(.ok) {
		background: #e6f4ea;
		color: #1a6b34;
		border: 1px solid #b7dfc2;
		border-radius: 10px;
		padding: 9px 12px;
		font-size: 0.85rem;
		margin-bottom: 12px;
	}
	:global(.muted) {
		color: #6a6155;
		font-size: 0.85rem;
	}
	:global(.altlink) {
		margin-top: 14px;
		text-align: center;
		font-size: 0.86rem;
		color: #6a6155;
	}
	:global(.altlink a) {
		color: #1f6feb;
		font-weight: 600;
		text-decoration: none;
	}
	:global(.spin) {
		display: inline-block;
		width: 18px;
		height: 18px;
		border-radius: 50%;
		border: 2.4px solid rgba(255, 255, 255, 0.4);
		border-top-color: #fff;
		animation: spinbtn 0.7s linear infinite;
	}
	@keyframes -global-spinbtn {
		to {
			transform: rotate(360deg);
		}
	}
</style>
