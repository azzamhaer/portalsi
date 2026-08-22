<script lang="ts">
	import { Eye, EyeOff, LoaderCircle, LockKeyhole, UserRound } from '@lucide/svelte';
	import AuthFields from '$lib/components/auth/AuthFields.svelte';
	import AuthShell from '$lib/components/auth/AuthShell.svelte';
	let { form } = $props();
	let reveal = $state(false);
	let loginValue = $state(form?.login ?? '');
	let passwordValue = $state('');
	let submitting = $state(false);
</script>

<svelte:head><title>Masuk — Sekolah Impian Mail</title></svelte:head>

<AuthShell>
	<div class="heading">
		<p class="eyebrow">SELAMAT DATANG KEMBALI</p>
		<h1>Masuk ke Sekolah Impian Mail</h1>
		<p>Buka email dan lanjutkan bisnismu.</p>
	</div>
	{#if form?.message}<div class="form-alert" role="alert">{form.message}</div>{/if}
	<form method="POST" onsubmit={() => (submitting = true)}>
		<AuthFields>
			<label>
				<span>Username atau email</span>
				<div class="input-icon">
					<UserRound size={18} /><input name="login" autocomplete="username" placeholder="nama@contoh.id" bind:value={loginValue} />
				</div>
			</label>
			<label>
				<span>Kata sandi</span>
				<div class="input-icon">
					<LockKeyhole size={18} /><input
						name="password"
						type={reveal ? 'text' : 'password'}
						autocomplete="current-password"
						placeholder="Masukkan kata sandi"
						bind:value={passwordValue}
					/><button type="button" onclick={() => (reveal = !reveal)} aria-label="Tampilkan kata sandi">
						{#if reveal}<EyeOff size={18} />{:else}<Eye size={18} />{/if}
					</button>
				</div>
			</label>
			<button class="auth-primary" type="submit" disabled={submitting}>
				{#if submitting}<LoaderCircle size={17} class="button-spin" /> Memproses…{:else}Masuk{/if}
			</button>
		</AuthFields>
	</form>
	<p class="switch">Belum punya akun? <a href="/register">Daftar sekarang</a></p>
</AuthShell>

<style>
	.heading h1 {
		margin: 0;
		font-size: clamp(1.9rem, 5vw, 2.55rem);
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
		max-width: 25rem;
		margin: 10px 0 28px;
		color: var(--color-muted);
		font-size: 0.94rem;
	}
	.input-icon {
		display: flex;
		align-items: center;
		background: white;
		border: 1px solid var(--color-border-strong);
		border-radius: 12px;
		color: var(--color-subtle);
		padding-left: 13px;
	}
	.input-icon:focus-within {
		border-color: var(--color-primary);
		box-shadow: var(--focus-ring);
	}
	.input-icon :global(input) {
		border: 0;
		box-shadow: none;
		height: 47px;
	}
	.input-icon :global(input:focus) {
		box-shadow: none;
	}
	.input-icon button {
		display: grid;
		width: 45px;
		height: 45px;
		place-items: center;
		padding: 0;
		background: transparent;
		border: 0;
		border-radius: 10px;
		cursor: pointer;
		color: var(--color-muted);
	}
	.switch {
		margin: 24px 0 0;
		color: var(--color-muted);
		font-size: 0.85rem;
		text-align: center;
	}
	.switch a {
		color: var(--color-primary-strong);
		font-weight: 720;
	}
	.form-alert {
		margin-bottom: 16px;
		padding: 11px 12px;
		background: var(--color-danger-soft);
		border: 1px solid #f1c5c1;
		border-radius: 11px;
		color: var(--color-danger);
		font-size: 0.82rem;
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
