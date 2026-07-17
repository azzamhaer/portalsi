<script lang="ts">
	import { Eye, LoaderCircle, LockKeyhole, Mail, ShieldCheck, TriangleAlert } from '@lucide/svelte';
	import AuthShell from '$lib/components/auth/AuthShell.svelte';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();
	const f = $derived(
		form as { success?: boolean; message?: string; tokenError?: boolean } | null
	);
	let revealPassword = $state(false);
	let submitting = $state(false);
	const invalidLink = $derived(!data.token);
</script>

<svelte:head><title>Reset kata sandi — Portal SI</title></svelte:head>

<AuthShell mode="simple">
	{#if f?.success}
		<div class="result">
			<div class="badge ok"><ShieldCheck size={26} /></div>
			<h1>Kata sandi diperbarui</h1>
			<p>Kata sandi kamu berhasil diubah. Silakan masuk dengan kata sandi baru.</p>
			<a class="cta" href="/login">Masuk sekarang</a>
		</div>
	{:else if invalidLink || f?.tokenError}
		<div class="result">
			<div class="badge warn"><TriangleAlert size={26} /></div>
			<h1>Tautan tidak berlaku</h1>
			<p>
				{f?.message ||
					'Tautan reset ini tidak valid atau sudah kedaluwarsa. Minta tautan baru untuk melanjutkan.'}
			</p>
			<a class="cta" href="/forgot-password">Minta tautan baru</a>
			<a class="ghost" href="/login">Kembali ke masuk</a>
		</div>
	{:else}
		<div class="heading">
			<p class="eyebrow">Pemulihan akun</p>
			<h1>Buat kata sandi baru</h1>
			<p>Masukkan kata sandi baru untuk akun kamu.</p>
		</div>
		{#if f?.message}<div class="form-alert" role="alert">{f.message}</div>{/if}
		<form method="POST" class="reset" onsubmit={() => (submitting = true)}>
			<input type="hidden" name="token" value={data.token} />
			<label>
				<span>Email</span>
				<div class="input-icon">
					<Mail size={18} />
					<input type="email" name="email" required value={data.email} autocomplete="email" />
				</div>
			</label>
			<label>
				<span>Kata sandi baru</span>
				<div class="input-icon">
					<LockKeyhole size={18} />
					<input
						type={revealPassword ? 'text' : 'password'}
						name="password"
						required
						minlength="6"
						autocomplete="new-password"
						placeholder="Minimal 6 karakter"
					/>
					<button
						type="button"
						onclick={() => (revealPassword = !revealPassword)}
						aria-label={revealPassword ? 'Sembunyikan' : 'Tampilkan'}><Eye size={18} /></button
					>
				</div>
			</label>
			<label>
				<span>Ulangi kata sandi</span>
				<div class="input-icon">
					<LockKeyhole size={18} />
					<input
						type={revealPassword ? 'text' : 'password'}
						name="password_confirmation"
						required
						minlength="6"
						autocomplete="new-password"
						placeholder="Ketik ulang kata sandi"
					/>
				</div>
			</label>
			<button class="submit" type="submit" disabled={submitting}>
				{#if submitting}<LoaderCircle size={17} class="button-spin" /> Memproses…{:else}Reset kata sandi{/if}
			</button>
		</form>
		<p class="switch"><a href="/login">Kembali ke masuk</a></p>
	{/if}
</AuthShell>

<style>
	.heading h1 {
		margin: 0;
		font-size: clamp(1.7rem, 5vw, 2.1rem);
		letter-spacing: -0.04em;
	}
	.heading > p:last-child {
		margin: 8px 0 20px;
		color: var(--color-muted);
		font-size: 0.92rem;
	}
	.reset {
		display: grid;
		gap: 14px;
	}
	.reset label {
		display: grid;
		gap: 6px;
	}
	.reset label span {
		font-size: 0.78rem;
		font-weight: 700;
	}
	.input-icon {
		display: flex;
		align-items: center;
		padding-left: 13px;
		background: white;
		border: 1px solid var(--color-border-strong);
		border-radius: 12px;
		color: var(--color-subtle);
	}
	.input-icon:focus-within {
		border-color: var(--color-primary);
		box-shadow: var(--focus-ring);
	}
	.input-icon input {
		border: 0;
		box-shadow: none;
		background: transparent;
	}
	.input-icon input:focus {
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
		cursor: pointer;
	}
	.submit {
		min-height: 46px;
		background: var(--color-primary);
		border: 0;
		border-radius: 12px;
		color: white;
		font-weight: 720;
	}
	.form-alert {
		margin-bottom: 14px;
		padding: 11px 12px;
		background: var(--color-danger-soft);
		border: 1px solid #f1c5c1;
		border-radius: 11px;
		color: var(--color-danger);
		font-size: 0.78rem;
	}
	.switch {
		margin: 20px 0 0;
		text-align: center;
		font-size: 0.85rem;
	}
	.switch a {
		color: var(--color-primary-strong);
		font-weight: 720;
	}

	/* Halaman hasil (sukses / tautan tidak valid / kedaluwarsa) */
	.result {
		display: grid;
		justify-items: center;
		text-align: center;
		gap: 6px;
		padding: 8px 0;
	}
	.result h1 {
		margin: 8px 0 0;
		font-size: clamp(1.6rem, 5vw, 2rem);
		letter-spacing: -0.04em;
	}
	.result > p {
		max-width: 30rem;
		margin: 4px 0 14px;
		color: var(--color-muted);
		font-size: 0.94rem;
	}
	.badge {
		display: grid;
		width: 58px;
		height: 58px;
		place-items: center;
		border-radius: 18px;
	}
	.badge.ok {
		background: #d9f7e6;
		color: #0f7a48;
	}
	.badge.warn {
		background: var(--color-danger-soft);
		color: var(--color-danger);
	}
	.cta {
		display: inline-grid;
		place-items: center;
		min-width: 200px;
		min-height: 46px;
		padding: 0 18px;
		background: var(--color-primary);
		border-radius: 12px;
		color: white;
		font-weight: 720;
	}
	.ghost {
		margin-top: 6px;
		color: var(--color-muted);
		font-weight: 640;
		font-size: 0.85rem;
	}
	:global(.button-spin) {
		animation: reset-spin 0.8s linear infinite;
	}
	@keyframes reset-spin {
		to {
			transform: rotate(360deg);
		}
	}
</style>
