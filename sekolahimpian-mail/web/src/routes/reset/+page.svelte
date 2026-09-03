<script lang="ts">
	import { Eye, EyeOff, LoaderCircle, ShieldCheck } from '@lucide/svelte';
	import AuthFields from '$lib/components/auth/AuthFields.svelte';
	import AuthShell from '$lib/components/auth/AuthShell.svelte';
	import { t } from '$lib/i18n';
	let { form, data } = $props();
	let showPw = $state(false);
	let showConfirm = $state(false);
	let password = $state('');
	let confirm = $state('');
	let submitting = $state(false);
	const mismatch = $derived(confirm.length > 0 && password !== confirm);
</script>

<svelte:head><title>Setel kata sandi — SI Mail</title></svelte:head>

<AuthShell>
	<div class="heading">
		<p class="eyebrow">{$t('reset.eyebrow')}</p>
		<h1>{$t('reset.title')}</h1>
		<p>{$t('reset.sub')}</p>
	</div>

	{#if form?.success}
		<div class="form-ok" role="status">{form.message || $t('reset.done')}</div>
		<p class="switch"><a href="/login">{$t('reset.backToLogin')}</a></p>
	{:else if !data?.valid}
		<div class="form-alert" role="alert">{$t('reset.invalid')}</div>
		<p class="switch"><a href="/login">{$t('reset.toLogin')}</a></p>
	{:else}
		<div class="notice"><ShieldCheck size={16} /> <span>{$t('reset.notice')}</span></div>
		{#if form?.message}<div class="form-alert" role="alert">{form.message}</div>{/if}
		<form method="POST" onsubmit={() => (submitting = true)}>
			<input type="hidden" name="token" value={data.token} />
			<input type="hidden" name="email" value={data.email} />
			<AuthFields>
				<label>
					<span>{$t('reset.newPassword')}</span>
					<div class="pw-field">
						<input name="password" type={showPw ? 'text' : 'password'} autocomplete="new-password" placeholder={$t('reg.pwPlaceholder')} bind:value={password} />
						<button type="button" onclick={() => (showPw = !showPw)} aria-label="Tampilkan">{#if showPw}<EyeOff size={18} />{:else}<Eye size={18} />{/if}</button>
					</div>
				</label>
				<label>
					<span>{$t('reset.repeat')}</span>
					<div class="pw-field">
						<input name="confirm" type={showConfirm ? 'text' : 'password'} autocomplete="new-password" placeholder={$t('reg.repeatPlaceholder')} bind:value={confirm} aria-invalid={mismatch ? 'true' : undefined} />
						<button type="button" onclick={() => (showConfirm = !showConfirm)} aria-label="Tampilkan">{#if showConfirm}<EyeOff size={18} />{:else}<Eye size={18} />{/if}</button>
					</div>
					{#if mismatch}<small class="field-error">{$t('reg.mismatch')}</small>{/if}
				</label>
				<button class="auth-primary" type="submit" disabled={submitting || mismatch || password.length < 6}>
					{#if submitting}<LoaderCircle size={17} class="button-spin" /> {$t('reset.saving')}{:else}{$t('reset.save')}{/if}
				</button>
			</AuthFields>
		</form>
	{/if}
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
		margin: 10px 0 22px;
		color: var(--color-muted);
		font-size: 0.9rem;
	}
	.notice {
		display: flex;
		gap: 8px;
		align-items: flex-start;
		margin-bottom: 14px;
		padding: 10px 12px;
		background: #eef4ff;
		border: 1px solid #cfe0ff;
		border-radius: 11px;
		color: #234a86;
		font-size: 0.8rem;
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
		text-align: center;
		font-size: 0.86rem;
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
</style>
