<script lang="ts">
	import { onMount } from 'svelte';
	import { Bell, X, LoaderCircle } from '@lucide/svelte';
	import { portal } from '$lib/actions/portal';
	import { pushSupported, pushPermission, enablePush } from '$lib/push';

	const DISMISS_KEY = 'portalsi-push-prompt-v1';

	let open = $state(false);
	let busy = $state(false);
	let done = $state<'' | 'granted' | 'denied'>('');

	onMount(() => {
		if (!pushSupported()) return;
		// Hanya saat izin masih 'default' (belum granted/denied). Kalau default, user pasti
		// belum berlangganan, jadi tak perlu cek langganan (yang bisa menggantung di SW).
		if (pushPermission() !== 'default') return;
		try {
			if (localStorage.getItem(DISMISS_KEY)) return;
		} catch {
			/* localStorage bisa diblokir; lanjut saja */
		}
		// Muncul halus beberapa saat setelah masuk.
		setTimeout(() => (open = true), 1400);
	});

	function remember() {
		try {
			localStorage.setItem(DISMISS_KEY, '1');
		} catch {
			/* abaikan */
		}
	}

	function dismiss() {
		remember();
		open = false;
	}

	async function accept() {
		if (busy) return;
		busy = true;
		const res = await enablePush();
		busy = false;
		remember();
		if (res === 'granted') {
			done = 'granted';
			setTimeout(() => (open = false), 1200);
		} else {
			// denied / error / unsupported → tutup saja tanpa memaksa.
			open = false;
		}
	}
</script>

{#if open}
	<div class="pp-scrim" use:portal role="dialog" aria-modal="true" aria-label="Aktifkan notifikasi">
		<div class="pp-card">
			<button class="pp-x" onclick={dismiss} aria-label="Tutup"><X size={18} /></button>
			<div class="pp-hero">
				<img src="https://portalsi.com/notification.webp" alt="" loading="eager" />
				<span class="pp-bell"><Bell size={26} /></span>
			</div>
			<div class="pp-body">
				{#if done === 'granted'}
					<h2>Notifikasi aktif 🎉</h2>
					<p>Sekarang kamu akan langsung tahu setiap ada aktivitas penting.</p>
				{:else}
					<h2>Jangan sampai ketinggalan</h2>
					<p>
						Aktifkan notifikasi biar kamu langsung tahu saat ada yang menyukai, mengomentari, atau
						mengajakmu berkolaborasi. <strong>Bukan promosi</strong> — murni aktivitas akunmu.
					</p>
					<div class="pp-actions">
						<button class="pp-later" onclick={dismiss} disabled={busy}>Nanti saja</button>
						<button class="pp-ok" onclick={accept} disabled={busy}>
							{#if busy}<LoaderCircle size={17} class="pp-spin" /> Menyiapkan…{:else}Oke, aktifkan{/if}
						</button>
					</div>
				{/if}
			</div>
		</div>
	</div>
{/if}

<style>
	.pp-scrim {
		position: fixed;
		inset: 0;
		z-index: 3300;
		display: grid;
		place-items: center;
		padding: 16px;
		background: rgb(12 10 8 / 62%);
		backdrop-filter: blur(4px);
		animation: pp-fade 0.25s ease;
	}
	.pp-card {
		position: relative;
		width: min(100%, 400px);
		overflow: hidden;
		background: var(--color-surface, #fff);
		border-radius: 22px;
		box-shadow: 0 30px 70px rgb(0 0 0 / 45%);
		animation: pp-pop 0.35s cubic-bezier(0.16, 1, 0.3, 1);
	}
	.pp-x {
		position: absolute;
		top: 12px;
		right: 12px;
		z-index: 2;
		display: grid;
		width: 34px;
		height: 34px;
		place-items: center;
		padding: 0;
		background: rgb(0 0 0 / 35%);
		border: 0;
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
		backdrop-filter: blur(2px);
	}
	.pp-hero {
		position: relative;
		aspect-ratio: 16 / 10;
		background: linear-gradient(135deg, var(--color-primary), var(--color-primary-strong, #1f6f43));
		overflow: hidden;
	}
	.pp-hero img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.pp-bell {
		position: absolute;
		left: 20px;
		bottom: -22px;
		display: grid;
		width: 52px;
		height: 52px;
		place-items: center;
		background: var(--color-surface, #fff);
		border-radius: 16px;
		box-shadow: 0 10px 24px rgb(0 0 0 / 25%);
		color: var(--color-primary);
		animation: pp-ring 2.4s ease-in-out infinite;
		transform-origin: 50% 10%;
	}
	.pp-body {
		padding: 34px 22px 22px;
	}
	.pp-body h2 {
		margin: 0 0 8px;
		font-size: 1.28rem;
		line-height: 1.2;
	}
	.pp-body p {
		margin: 0;
		color: var(--color-muted);
		font-size: 0.9rem;
		line-height: 1.55;
	}
	.pp-actions {
		display: flex;
		gap: 10px;
		margin-top: 18px;
	}
	.pp-actions button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
		min-height: 46px;
		border-radius: 13px;
		font-size: 0.88rem;
		font-weight: 720;
		cursor: pointer;
	}
	.pp-later {
		flex: 0 0 auto;
		padding: 0 16px;
		background: transparent;
		border: 1px solid var(--color-border);
		color: var(--color-text);
	}
	.pp-ok {
		flex: 1;
		background: var(--color-primary);
		border: 0;
		color: #fff;
	}
	.pp-actions button:disabled {
		opacity: 0.6;
		cursor: default;
	}
	:global(.pp-spin) {
		animation: pp-spin 0.8s linear infinite;
	}
	@keyframes pp-fade {
		from {
			opacity: 0;
		}
	}
	@keyframes pp-pop {
		from {
			opacity: 0;
			transform: translateY(16px) scale(0.96);
		}
	}
	@keyframes pp-ring {
		0%,
		60%,
		100% {
			transform: rotate(0);
		}
		70% {
			transform: rotate(14deg);
		}
		80% {
			transform: rotate(-10deg);
		}
		90% {
			transform: rotate(6deg);
		}
	}
	@keyframes pp-spin {
		to {
			transform: rotate(360deg);
		}
	}
	@media (prefers-reduced-motion: reduce) {
		.pp-scrim,
		.pp-card,
		.pp-bell {
			animation: none;
		}
	}
</style>
