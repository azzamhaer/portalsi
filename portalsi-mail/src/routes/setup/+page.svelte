<script lang="ts">
	import { enhance } from '$app/forms';
	import { AtSign, ShieldAlert, X } from '@lucide/svelte';
	import { t } from '$lib/i18n';
	let { data, form } = $props();
	let submitting = $state(false);
	let local = $state(form?.local ?? '');
	let confirmOpen = $state(false);
	let formEl: HTMLFormElement | null = null;

	const clean = $derived((local || '').toLowerCase().trim());
	const valid = $derived(/^[a-z][a-z0-9._-]{2,}$/.test(clean));
	const allDomains = $derived([data.domain, ...(((data.aliasDomains as string[]) ?? []))]);
	const hasAlias = $derived(allDomains.length > 1);

	function askConfirm() {
		if (!valid) return;
		confirmOpen = true;
	}
	function doSubmit() {
		confirmOpen = false;
		formEl?.requestSubmit();
	}
</script>

<div class="card">
	<div class="ico"><AtSign size={22} /></div>
	<h1>{$t('setup.title')}</h1>
	<p class="sub">{@html $t('setup.sub')}</p>

	{#if form?.message}<div class="err">{form.message}</div>{/if}

	<form
		bind:this={formEl}
		method="POST"
		use:enhance={() => {
			submitting = true;
			return async ({ update }) => {
				submitting = false;
				await update();
			};
		}}
	>
		<label class="field">
			{$t('setup.name')}
			<span class="addr">
				<input
					name="local_part"
					bind:value={local}
					placeholder={$t('setup.namePlaceholder')}
					autocomplete="off"
					spellcheck="false"
					required
				/>
				<span class="suffix">@{data.domain}</span>
			</span>
		</label>
		<div class="preview">
			{$t('setup.yourAddress')}
			{#each allDomains as d}<b class="pv-addr">{clean || $t('setup.namePlaceholder')}@{d}</b>{/each}
		</div>
		<button type="button" class="btn" onclick={askConfirm} disabled={submitting || !valid}>
			{#if submitting}<span class="spin"></span>{:else}{$t('setup.create')}{/if}
		</button>

		{#if confirmOpen}
			<div class="cf-bg" role="presentation" onclick={() => (confirmOpen = false)}>
				<div class="cf" role="dialog" aria-modal="true" onclick={(e) => e.stopPropagation()}>
					<button class="cf-x" onclick={() => (confirmOpen = false)} aria-label={$t('common.close')}><X size={16} /></button>
					<div class="cf-ico"><ShieldAlert size={26} /></div>
					<h2>{$t('setup.confirmTitle')}</h2>
					<p>{$t('setup.confirmIntro')}</p>
					{#each allDomains as d}<div class="cf-addr">{clean}@{d}</div>{/each}
					<p class="cf-warn">{@html $t('setup.permanentWarn')}</p>
					<div class="cf-actions">
						<button type="button" class="cf-cancel" onclick={() => (confirmOpen = false)}>{$t('setup.recheck')}</button>
						<button type="submit" class="btn" onclick={doSubmit} disabled={submitting}>
							{#if submitting}<span class="spin"></span>{:else}{$t('setup.createPermanent')}{/if}
						</button>
					</div>
				</div>
			</div>
		{/if}
	</form>
</div>

<style>
	.ico {
		display: grid;
		place-items: center;
		width: 48px;
		height: 48px;
		border-radius: 14px;
		background: rgba(31, 111, 235, 0.12);
		color: #1f6feb;
		margin-bottom: 14px;
	}
	.addr {
		display: flex;
		align-items: stretch;
		margin-top: 5px;
		border: 1px solid rgba(26, 23, 20, 0.14);
		border-radius: 11px;
		overflow: hidden;
		background: #faf7f2;
	}
	.addr input {
		flex: 1;
		border: 0;
		background: transparent;
		padding: 11px 12px;
		font: inherit;
		font-weight: 400;
		min-width: 0;
	}
	.addr input:focus {
		outline: none;
	}
	.addr:focus-within {
		outline: 2px solid #1f6feb;
		background: #fff;
	}
	.suffix {
		display: flex;
		align-items: center;
		padding: 0 12px;
		background: #eef1f5;
		color: #5f6368;
		font-size: 0.9rem;
		white-space: nowrap;
	}
	.preview {
		margin: 4px 0 16px;
		font-size: 0.85rem;
		color: #5f6368;
	}
	.preview b {
		color: #1a1714;
	}
	.pv-addr {
		display: block;
		color: #1f6feb;
		margin-top: 3px;
	}
	:global(.btn:disabled) {
		opacity: 0.6;
		cursor: default;
	}
	.cf-bg {
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, 0.45);
		display: grid;
		place-items: center;
		z-index: 100;
		padding: 20px;
	}
	.cf {
		position: relative;
		width: min(94vw, 400px);
		background: #fff;
		border-radius: 18px;
		padding: 26px 24px 22px;
		text-align: center;
		box-shadow: 0 24px 60px rgba(0, 0, 0, 0.34);
	}
	.cf-x {
		position: absolute;
		top: 14px;
		right: 14px;
		border: 0;
		background: transparent;
		cursor: pointer;
		color: #9aa0a6;
	}
	.cf-ico {
		display: grid;
		place-items: center;
		width: 56px;
		height: 56px;
		border-radius: 50%;
		background: #fff2e6;
		color: #e37400;
		margin: 0 auto 12px;
	}
	.cf h2 {
		margin: 0 0 6px;
		font-size: 1.15rem;
	}
	.cf p {
		margin: 0 0 8px;
		color: #5f6368;
		font-size: 0.9rem;
	}
	.cf-addr {
		font-weight: 700;
		font-size: 1.05rem;
		color: #1f6feb;
		background: #eef4ff;
		border-radius: 10px;
		padding: 10px;
		margin: 4px 0 10px;
		word-break: break-all;
	}
	.cf-warn {
		font-size: 0.82rem !important;
		color: #b06a00 !important;
		background: #fff8ec;
		border: 1px solid #f3dca8;
		border-radius: 10px;
		padding: 9px 11px;
	}
	.cf-actions {
		display: flex;
		gap: 10px;
		margin-top: 16px;
	}
	.cf-cancel {
		flex: 1;
		padding: 12px;
		border: 1px solid #d5dae2;
		border-radius: 12px;
		background: #fff;
		font: inherit;
		font-weight: 600;
		color: #3c4043;
		cursor: pointer;
	}
	.cf-actions .btn {
		flex: 1.3;
	}
</style>
