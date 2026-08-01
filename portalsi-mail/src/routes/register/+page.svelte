<script lang="ts">
	import { Eye, EyeOff, LoaderCircle } from '@lucide/svelte';
	import AuthFields from '$lib/components/auth/AuthFields.svelte';
	import AuthShell from '$lib/components/auth/AuthShell.svelte';
	let { form } = $props();
	let showPw = $state(false);
	let showConfirm = $state(false);
	let password = $state('');
	let confirm = $state('');
	let submitting = $state(false);
	const mismatch = $derived(confirm.length > 0 && password !== confirm);
</script>

<svelte:head><title>Daftar — Portal SI Mail</title></svelte:head>

<AuthShell mode="register">
	<div class="heading">
		<p class="eyebrow">Mulai di sini</p>
		<h1>Buat akun Portal SI Mail</h1>
		<p>Daftar sekali, langsung punya identitas email pesantrenmu.</p>
	</div>
	{#if form?.success}
		<div class="form-ok" role="status">{form.message}</div>
	{:else}
		{#if form?.message}<div class="form-alert" role="alert">{form.message}</div>{/if}
		<form method="POST" onsubmit={() => (submitting = true)}>
			<AuthFields>
				<div class="two-fields">
					<label><span>Nama lengkap</span><input name="full_name" autocomplete="name" placeholder="Fulan Abdullah" value={form?.values?.full_name ?? ''} /></label>
					<label><span>Username</span><input name="username" autocomplete="username" placeholder="fulan123" value={form?.values?.username ?? ''} /></label>
				</div>
				<label><span>Email pemulihan</span><input name="email" type="email" autocomplete="email" placeholder="nama@contoh.id" value={form?.values?.email ?? ''} /></label>
				<label>
					<span>Kata sandi</span>
					<div class="pw-field">
						<input name="password" type={showPw ? 'text' : 'password'} autocomplete="new-password" placeholder="Minimal 6 karakter" bind:value={password} />
						<button type="button" onclick={() => (showPw = !showPw)} aria-label="Tampilkan">{#if showPw}<EyeOff size={18} />{:else}<Eye size={18} />{/if}</button>
					</div>
				</label>
				<label>
					<span>Ulangi kata sandi</span>
					<div class="pw-field">
						<input type={showConfirm ? 'text' : 'password'} autocomplete="new-password" placeholder="Ketik ulang" bind:value={confirm} aria-invalid={mismatch ? 'true' : undefined} />
						<button type="button" onclick={() => (showConfirm = !showConfirm)} aria-label="Tampilkan">{#if showConfirm}<EyeOff size={18} />{:else}<Eye size={18} />{/if}</button>
					</div>
					{#if mismatch}<small class="field-error">Kata sandi belum sama.</small>{/if}
				</label>
				<button class="auth-primary" type="submit" disabled={submitting || mismatch}>
					{#if submitting}<LoaderCircle size={17} class="button-spin" /> Memproses…{:else}Buat akun{/if}
				</button>
			</AuthFields>
		</form>
	{/if}
	<p class="switch">Sudah punya akun? <a href="/login">Masuk</a></p>
</AuthShell>

<style>
	.heading h1 {
		margin: 0;
		font-size: clamp(1.85rem, 5vw, 2.45rem);
		letter-spacing: -0.045em;
		line-height: 1.08;
	}
	.eyebrow {
		margin: 0 0 4px;
		color: var(--color-primary);
		font-weight: 700;
		font-size: 0.82rem;
	}
	.heading > p:last-child {
		margin: 10px 0 24px;
		color: var(--color-muted);
		font-size: 0.9rem;
	}
	.two-fields {
		display: grid;
		gap: 15px;
	}
	.pw-field {
		position: relative;
		display: flex;
		align-items: center;
	}
	.pw-field input {
		flex: 1;
		width: 100%;
		padding-right: 44px;
	}
	.pw-field button {
		position: absolute;
		right: 6px;
		display: grid;
		width: 34px;
		height: 34px;
		place-items: center;
		padding: 0;
		background: transparent;
		border: 0;
		color: var(--color-muted);
		cursor: pointer;
	}
	.switch {
		margin: 20px 0 0;
		color: var(--color-muted);
		font-size: 0.84rem;
		text-align: center;
	}
	.switch a {
		color: var(--color-primary-strong);
		font-weight: 720;
	}
	.form-alert {
		margin-bottom: 14px;
		padding: 11px 12px;
		background: var(--color-danger-soft);
		border: 1px solid #f1c5c1;
		border-radius: 11px;
		color: var(--color-danger);
		font-size: 0.82rem;
	}
	.form-ok {
		margin-bottom: 14px;
		padding: 12px 14px;
		background: #e7f6ec;
		border: 1px solid #b6e0c4;
		border-radius: 11px;
		color: #1a6b34;
		font-size: 0.88rem;
	}
	.field-error {
		color: var(--color-danger);
		font-size: 0.72rem;
		font-weight: 560;
	}
	:global(.button-spin) {
		animation: auth-spin 0.8s linear infinite;
	}
	@keyframes auth-spin {
		to {
			transform: rotate(360deg);
		}
	}
	@media (min-width: 520px) {
		.two-fields {
			grid-template-columns: 1fr 1fr;
		}
	}
</style>
