<script lang="ts">
	import { Mail, LogOut } from '@lucide/svelte';
	let { data, children } = $props();
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
	<header class="top">
		<a class="brand" href="/">
			<span class="logo"><Mail size={18} /></span>
			<span>Portal <b>SI</b> Mail</span>
		</a>
		{#if data.user}
			<form method="POST" action="/logout" class="userbox">
				<span class="uname">{data.user.full_name || data.user.username}</span>
				<button class="logout" title="Keluar" aria-label="Keluar"><LogOut size={16} /></button>
			</form>
		{/if}
	</header>
	<main class="main">
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
		padding: 14px 20px;
		border-bottom: 1px solid rgba(26, 23, 20, 0.08);
		background: #fff;
	}
	.brand {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		text-decoration: none;
		color: #1a1714;
		font-weight: 800;
		font-family: 'Plus Jakarta Sans', sans-serif;
	}
	.brand b {
		color: #e86a17;
	}
	.logo {
		display: grid;
		place-items: center;
		width: 32px;
		height: 32px;
		border-radius: 9px;
		background: rgba(31, 111, 235, 0.12);
		color: #1f6feb;
	}
	.userbox {
		display: flex;
		align-items: center;
		gap: 10px;
		margin: 0;
	}
	.uname {
		font-size: 0.88rem;
		color: #6a6155;
	}
	.logout {
		display: grid;
		place-items: center;
		width: 34px;
		height: 34px;
		border-radius: 8px;
		border: 1px solid rgba(26, 23, 20, 0.1);
		background: #fff;
		color: #6a6155;
		cursor: pointer;
	}
	.logout:hover {
		background: #f5f0e8;
	}
	.main {
		flex: 1;
		display: flex;
		justify-content: center;
		padding: 34px 18px 56px;
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
</style>
