<script lang="ts">
	import { enhance } from '$app/forms';
	import { untrack } from 'svelte';
	import { Lock, Globe, Check } from '@lucide/svelte';
	import type { PageProps } from './$types';
	let { data, form }: PageProps = $props();
	let isPrivate = $state(untrack(() => data.isPrivate));
	let submitting = $state(false);
	let modal = $state<{ private: boolean } | null>(null);
</script>

<svelte:head><title>Privasi akun — Portal SI</title></svelte:head>
<main class="settings-form surface">
	<a class="back" href="/settings">← Pengaturan</a>
	<h1>Privasi akun</h1>
	<p class="lede">Akun privat memerlukan persetujuan Anda sebelum orang lain dapat melihat postingan.</p>

	<form
		method="POST"
		use:enhance={() => {
			submitting = true;
			return async ({ update, result }) => {
				await update({ reset: false, invalidateAll: false });
				submitting = false;
				if (result.type === 'success') modal = { private: isPrivate };
			};
		}}
	>
		<button
			type="button"
			class="toggle-card"
			class:active={isPrivate}
			disabled={submitting}
			onclick={() => (isPrivate = !isPrivate)}
		>
			<span class="toggle-ico">
				{#if isPrivate}<Lock size={20} />{:else}<Globe size={20} />{/if}
			</span>
			<span class="toggle-text">
				<strong>{isPrivate ? 'Akun privat' : 'Akun publik'}</strong>
				<small>{isPrivate ? 'Pengikut baru harus Anda setujui.' : 'Siapa pun dapat melihat postingan Anda.'}</small>
			</span>
			<span class="switch" aria-hidden="true"><span class="knob"></span></span>
		</button>
		<input type="checkbox" name="is_private" bind:checked={isPrivate} hidden />

		{#if form?.message && !modal}<p class:success={form.success}>{form.message}</p>{/if}

		<button class="save" type="submit" disabled={submitting}>
			{#if submitting}<span class="spinner" aria-hidden="true"></span> Menyimpan…{:else}Simpan privasi{/if}
		</button>
	</form>
</main>

{#if modal}
	<div class="modal-scrim" role="presentation" onclick={() => (modal = null)}>
		<div class="modal" role="dialog" aria-modal="true" onclick={(e) => e.stopPropagation()}>
			<span class="modal-ico"><Check size={26} /></span>
			<strong>Privasi diperbarui</strong>
			<p>
				Akun Anda sekarang <b>{modal.private ? 'privat' : 'publik'}</b>.
				{modal.private
					? 'Permintaan mengikuti akan menunggu persetujuan Anda.'
					: 'Postingan Anda dapat dilihat semua orang.'}
			</p>
			<button type="button" onclick={() => (modal = null)}>Selesai</button>
		</div>
	</div>
{/if}

<style>
	.settings-form {
		width: min(100% - 32px, 600px);
		margin: 28px auto;
		padding: 24px;
	}
	.back {
		color: var(--color-primary-strong);
		font-size: 0.78rem;
		font-weight: 700;
	}
	h1 {
		margin: 18px 0 5px;
		font-size: 1.3rem;
	}
	.lede {
		color: var(--color-muted);
		font-size: 0.82rem;
	}
	form {
		display: grid;
		gap: 16px;
		margin-top: 22px;
	}
	.toggle-card {
		display: flex;
		align-items: center;
		gap: 13px;
		width: 100%;
		padding: 15px;
		background: var(--color-surface-soft);
		border: 1px solid var(--color-border);
		border-radius: 14px;
		text-align: left;
		cursor: pointer;
		transition:
			border-color 0.18s ease,
			background 0.18s ease;
	}
	.toggle-card:disabled {
		opacity: 0.6;
		cursor: not-allowed;
	}
	.toggle-card.active {
		border-color: var(--color-primary);
		background: var(--color-primary-soft, rgb(59 130 246 / 8%));
	}
	.toggle-ico {
		display: grid;
		place-items: center;
		width: 40px;
		height: 40px;
		flex: 0 0 auto;
		border-radius: 11px;
		background: var(--color-surface, #fff);
		color: var(--color-muted);
	}
	.toggle-card.active .toggle-ico {
		background: var(--color-primary);
		color: #fff;
	}
	.toggle-text {
		display: grid;
		gap: 2px;
		flex: 1 1 auto;
	}
	.toggle-text strong {
		font-size: 0.94rem;
	}
	.toggle-text small {
		color: var(--color-muted);
		font-size: 0.78rem;
	}
	.switch {
		position: relative;
		width: 46px;
		height: 27px;
		flex: 0 0 auto;
		border-radius: 999px;
		background: var(--color-border);
		transition: background 0.2s ease;
	}
	.toggle-card.active .switch {
		background: var(--color-primary);
	}
	.knob {
		position: absolute;
		top: 3px;
		left: 3px;
		width: 21px;
		height: 21px;
		border-radius: 50%;
		background: #fff;
		box-shadow: 0 1px 3px rgb(0 0 0 / 25%);
		transition: transform 0.2s ease;
	}
	.toggle-card.active .knob {
		transform: translateX(19px);
	}
	.save {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		min-height: 46px;
		background: var(--color-primary);
		border: 0;
		border-radius: 12px;
		color: white;
		font-weight: 720;
		cursor: pointer;
		transition: opacity 0.15s ease;
	}
	.save:disabled {
		opacity: 0.7;
		cursor: progress;
	}
	.spinner {
		width: 15px;
		height: 15px;
		border: 2px solid rgb(255 255 255 / 45%);
		border-top-color: #fff;
		border-radius: 50%;
		animation: psi-spin 0.7s linear infinite;
	}
	@keyframes psi-spin {
		to {
			transform: rotate(360deg);
		}
	}
	form > p {
		margin: 0;
		color: var(--color-danger);
		font-size: 0.76rem;
	}
	form > p.success {
		color: var(--color-secondary);
	}

	.modal-scrim {
		position: fixed;
		inset: 0;
		z-index: 60;
		display: grid;
		place-items: center;
		padding: 20px;
		background: rgb(0 0 0 / 45%);
		animation: psi-fade 0.15s ease;
	}
	.modal {
		display: grid;
		justify-items: center;
		gap: 8px;
		width: min(100%, 340px);
		padding: 26px 22px 20px;
		background: var(--color-surface, #fff);
		border-radius: 18px;
		text-align: center;
		animation: psi-pop 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
	}
	.modal-ico {
		display: grid;
		place-items: center;
		width: 52px;
		height: 52px;
		margin-bottom: 4px;
		border-radius: 50%;
		background: var(--color-secondary-soft, #e7f6ee);
		color: var(--color-secondary, #1a9d5a);
	}
	.modal strong {
		font-size: 1.05rem;
	}
	.modal p {
		margin: 0;
		color: var(--color-muted);
		font-size: 0.84rem;
		line-height: 1.45;
	}
	.modal button {
		margin-top: 10px;
		width: 100%;
		min-height: 44px;
		background: var(--color-primary);
		border: 0;
		border-radius: 12px;
		color: #fff;
		font-weight: 700;
		cursor: pointer;
	}
	@keyframes psi-fade {
		from {
			opacity: 0;
		}
	}
	@keyframes psi-pop {
		from {
			opacity: 0;
			transform: scale(0.94);
		}
	}
	@media (max-width: 767px) {
		.settings-form {
			width: 100%;
			margin: 0;
			border: 0;
			border-radius: 0;
		}
	}
</style>
