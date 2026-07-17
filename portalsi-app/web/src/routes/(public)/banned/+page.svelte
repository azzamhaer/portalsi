<script lang="ts">
	import { LoaderCircle, LogOut, ShieldAlert } from '@lucide/svelte';
	import AuthShell from '$lib/components/auth/AuthShell.svelte';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();
	// Akses longgar: ActionData berupa union (sukses / gagal), hindari error tipe strict.
	const f = $derived(
		form as { success?: boolean; message?: string; values?: { message?: string } } | null
	);
	let submitting = $state(false);
	let messageValue = $state('');

	const statusLabel: Record<string, string> = {
		pending: 'Menunggu peninjauan',
		approved: 'Disetujui',
		rejected: 'Ditolak'
	};

	const hasPending = $derived(data.appeals.some((a) => a.status === 'pending'));
</script>

<svelte:head><title>Akun Diblokir — Portal SI</title></svelte:head>

<AuthShell>
	<div class="heading">
		<div class="badge"><ShieldAlert size={22} /></div>
		<h1>Akun kamu diblokir</h1>
		<p>
			{#if data.username}Hai @{data.username}, akun{:else}Akun{/if} kamu untuk sementara tidak dapat
			mengakses Portal SI.
		</p>
	</div>

	<div class="reason">
		<span>Alasan</span>
		<p>{data.banReason || 'Tidak ada alasan spesifik yang dicantumkan admin.'}</p>
	</div>

	{#if f?.success}
		<div class="ok" role="status">Banding kamu terkirim. Tim akan meninjau akunmu.</div>
	{:else if f?.message}
		<div class="form-alert" role="alert">{f.message}</div>
	{/if}

	{#if data.appeals.length > 0}
		<div class="appeals">
			<span class="appeals-title">Riwayat banding</span>
			{#each data.appeals as appeal (appeal.appeal_id)}
				<div class="appeal">
					<div class="appeal-head">
						<span class="tag tag-{appeal.status}">{statusLabel[appeal.status] ?? appeal.status}</span>
					</div>
					<p class="appeal-msg">{appeal.message}</p>
					{#if appeal.admin_response}
						<p class="appeal-resp">Tanggapan admin: {appeal.admin_response}</p>
					{/if}
				</div>
			{/each}
		</div>
	{/if}

	{#if hasPending}
		<p class="switch">Banding kamu sedang ditinjau. Kamu akan diberi tahu setelah ada keputusan.</p>
	{:else}
		<form method="POST" onsubmit={() => (submitting = true)}>
			<label>
				<span>Ajukan banding</span>
				<textarea
					name="message"
					rows="4"
					placeholder="Jelaskan kenapa akunmu sebaiknya ditinjau ulang…"
					bind:value={messageValue}
				></textarea>
			</label>
			<button class="submit" type="submit" disabled={submitting}>
				{#if submitting}<LoaderCircle size={17} class="button-spin" /> Mengirim…{:else}Kirim banding{/if}
			</button>
		</form>
	{/if}

	<a class="logout" href="/logout" data-sveltekit-preload-data="off"><LogOut size={16} /> Keluar dari akun</a>
</AuthShell>

<style>
	.heading h1 {
		margin: 12px 0 0;
		font-size: clamp(1.7rem, 5vw, 2.3rem);
		letter-spacing: -0.045em;
		line-height: 1.1;
	}
	.heading > p {
		max-width: 26rem;
		margin: 10px 0 22px;
		color: var(--color-muted);
		font-size: 0.94rem;
	}
	.badge {
		display: grid;
		width: 46px;
		height: 46px;
		place-items: center;
		border-radius: 14px;
		background: var(--color-danger-soft);
		color: var(--color-danger);
	}
	.reason {
		padding: 13px 14px;
		background: var(--color-danger-soft);
		border: 1px solid #f1c5c1;
		border-radius: 12px;
		margin-bottom: 18px;
	}
	.reason span {
		display: block;
		font-size: 0.68rem;
		font-weight: 760;
		letter-spacing: 0.05em;
		text-transform: uppercase;
		color: var(--color-danger);
	}
	.reason p {
		margin: 4px 0 0;
		color: var(--color-danger);
		font-size: 0.9rem;
	}
	textarea {
		width: 100%;
		border: 1px solid var(--color-border-strong);
		border-radius: 12px;
		padding: 11px 13px;
		font: inherit;
		resize: vertical;
	}
	textarea:focus {
		border-color: var(--color-primary);
		box-shadow: var(--focus-ring);
		outline: none;
	}
	label span {
		display: block;
		margin-bottom: 6px;
		font-size: 0.8rem;
		font-weight: 640;
	}
	.appeals {
		display: grid;
		gap: 10px;
		margin-bottom: 20px;
	}
	.appeals-title {
		font-size: 0.72rem;
		font-weight: 720;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: var(--color-subtle);
	}
	.appeal {
		padding: 11px 12px;
		border: 1px solid var(--color-border-strong);
		border-radius: 11px;
		background: white;
	}
	.appeal-msg {
		margin: 6px 0 0;
		font-size: 0.86rem;
	}
	.appeal-resp {
		margin: 8px 0 0;
		font-size: 0.82rem;
		color: var(--color-muted);
	}
	.tag {
		display: inline-block;
		padding: 2px 9px;
		border-radius: 999px;
		font-size: 0.7rem;
		font-weight: 700;
	}
	.tag-pending {
		background: #fff3d6;
		color: #9a6b00;
	}
	.tag-approved {
		background: #d9f7e6;
		color: #0f7a48;
	}
	.tag-rejected {
		background: var(--color-danger-soft);
		color: var(--color-danger);
	}
	.ok {
		margin-bottom: 16px;
		padding: 11px 12px;
		background: #d9f7e6;
		border: 1px solid #a9e7c4;
		border-radius: 11px;
		color: #0f7a48;
		font-size: 0.8rem;
	}
	.form-alert {
		margin-bottom: 16px;
		padding: 11px 12px;
		background: var(--color-danger-soft);
		border: 1px solid #f1c5c1;
		border-radius: 11px;
		color: var(--color-danger);
		font-size: 0.78rem;
	}
	.submit {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 7px;
		width: 100%;
		min-height: 48px;
		margin-top: 14px;
		background: var(--color-primary);
		border: 1px solid var(--color-primary);
		border-radius: 12px;
		color: white;
		font-size: 0.92rem;
		font-weight: 750;
		cursor: pointer;
		transition: background 140ms ease;
	}
	.submit:hover:enabled {
		background: var(--color-primary-strong);
	}
	.submit:disabled {
		opacity: 0.6;
		cursor: not-allowed;
	}
	.logout {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 7px;
		width: 100%;
		min-height: 46px;
		margin-top: 12px;
		background: transparent;
		border: 1px solid var(--color-border-strong);
		border-radius: 12px;
		color: var(--color-muted);
		font-size: 0.88rem;
		font-weight: 700;
		text-decoration: none;
	}
	.logout:hover {
		border-color: var(--color-danger);
		color: var(--color-danger);
	}
	.switch {
		margin: 20px 0 0;
		color: var(--color-muted);
		font-size: 0.85rem;
		text-align: center;
	}
	.switch a {
		color: var(--color-primary-strong);
		font-weight: 720;
	}
	:global(.button-spin) {
		animation: auth-spin 0.8s linear infinite;
	}
	@keyframes auth-spin {
		to {
			transform: rotate(360deg);
		}
	}
</style>
