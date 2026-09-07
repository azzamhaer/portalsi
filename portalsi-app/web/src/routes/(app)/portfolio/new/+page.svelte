<script lang="ts">
	import type { PageProps } from './$types';
	import { t } from '$lib/i18n';
	let { data, form }: PageProps = $props();
</script>

<svelte:head><title>Tambah portfolio — Portal SI</title></svelte:head>
<main class="form surface">
	<a href="/portfolio">← Portfolio</a>
	<h1>{$t('pf.addWork')}</h1>
	<form method="POST" enctype="multipart/form-data">
		<label
			><span>{$t('pfn.forUser')}</span><input
				name="target_username"
				required
				value={data.username}
				placeholder={$t('pfn.usernamePh')}
			/><small
				>{$t('pfn.hint')}</small
			></label
		>
		<label
			><span>{$t('pfn.aspect')}</span><select name="aspect" required
				><option value="quran">{$t('pf.catQuran')}</option><option value="it">{$t('pf.catTech')}</option><option
					value="bahasa">{$t('pf.catLang')}</option
				><option value="karakter">{$t('pf.catChar')}</option></select
			></label
		><label><span>{$t('form.title')}</span><input name="title" required maxlength="255" /></label><label
			><span>{$t('form.desc')}</span><textarea name="description" rows="5"></textarea></label
		><label
			><span>{$t('form.year')}</span><input
				name="year"
				type="number"
				min="2000"
				max={new Date().getFullYear()}
			/></label
		><label
			><span>{$t('pfn.media')}</span><input
				name="media"
				type="file"
				accept="image/jpeg,image/png,application/pdf"
			/></label
		>{#if form?.message}<p>{form.message}</p>{/if}<button>{$t('pfn.publish')}</button>
	</form>
</main>

<style>
	.form {
		width: min(100% - 32px, 620px);
		margin: 28px auto;
		padding: 24px;
	}
	.form > a {
		color: var(--color-primary-strong);
		font-size: 0.78rem;
		font-weight: 700;
	}
	h1 {
		margin: 18px 0;
		font-size: 1.3rem;
	}
	form,
	label {
		display: grid;
	}
	form {
		gap: 14px;
	}
	label {
		gap: 6px;
	}
	label span {
		font-size: 0.77rem;
		font-weight: 700;
	}
	label small {
		color: var(--color-muted);
		font-size: 0.68rem;
	}
	input,
	textarea,
	select {
		padding: 11px;
		background: var(--color-surface-soft);
		border: 1px solid var(--color-border);
		border-radius: 10px;
	}
	button {
		min-height: 44px;
		background: var(--color-primary);
		border: 0;
		border-radius: 11px;
		color: white;
		font-weight: 720;
	}
	form > p {
		color: var(--color-danger);
		font-size: 0.74rem;
	}
</style>
