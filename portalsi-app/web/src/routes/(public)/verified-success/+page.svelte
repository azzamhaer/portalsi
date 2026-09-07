<script lang="ts">
	import { BadgeCheck, MailCheck, TriangleAlert } from '@lucide/svelte';
	import { get } from 'svelte/store';
	import { t } from '$lib/i18n';
	import { page } from '$app/state';

	const state = $derived(page.url.searchParams.get('email'));
	const view = $derived.by(() => {
		switch (state) {
			case 'changed':
				return {
					ok: true,
					title: get(t)('auth.vsEmailChanged'),
					body: get(t)('auth.vsEmailChangedMsg')
				};
			case 'taken':
				return {
					ok: false,
					title: get(t)('auth.vsEmailUsed'),
					body: get(t)('auth.vsEmailUsedMsg')
				};
			case 'invalid':
				return {
					ok: false,
					title: get(t)('auth.vsInvalid'),
					body: get(t)('auth.vsInvalidMsg')
				};
			default:
				return {
					ok: true,
					title: get(t)('auth.vsVerified'),
					body: get(t)('auth.vsVerifiedMsg')
				};
		}
	});
</script>

<svelte:head><title>{view.title} — Portal SI</title></svelte:head>
<main class:error={!view.ok}>
	{#if !view.ok}<TriangleAlert size={48} />{:else if state === 'changed'}<MailCheck
			size={48}
		/>{:else}<BadgeCheck size={48} />{/if}
	<h1>{view.title}</h1>
	<p>{view.body}</p>
	<a href="/login">Masuk ke Portal SI</a>
</main>

<style>
	main {
		display: grid;
		min-height: 100vh;
		place-content: center;
		justify-items: center;
		padding: 24px;
		background: var(--color-canvas);
		text-align: center;
		color: var(--color-secondary);
	}
	main.error {
		color: var(--color-danger);
	}
	h1 {
		margin: 16px 0 6px;
		color: var(--color-text);
		font-size: 1.5rem;
	}
	p {
		max-width: 28rem;
		margin: 0;
		color: var(--color-muted);
	}
	a {
		margin-top: 18px;
		padding: 12px 17px;
		background: var(--color-primary);
		border-radius: 11px;
		color: white;
		font-weight: 720;
	}
</style>
