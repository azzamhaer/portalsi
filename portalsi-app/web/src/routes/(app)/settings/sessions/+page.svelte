<script lang="ts">
	import { MonitorSmartphone, Trash2 } from '@lucide/svelte';
	import { get } from 'svelte/store';
	import { t } from '$lib/i18n';
	import { untrack } from 'svelte';
	import { clientRequest } from '$lib/api/client';
	import { confirmAction } from '$lib/ui/confirm';
	import { relativeTimeId } from '$lib/utils/time';
	import SectionPage from '$lib/components/layout/SectionPage.svelte';
	import type { PageProps } from './$types';
	let { data }: PageProps = $props();
	let sessions = $state(untrack(() => [...data.sessions]));
	let message = $state('');
	async function remove(id: number) {
		if (
			!(await confirmAction({
				title: get(t)('ses.revokeTitle'),
				description: get(t)('ses.revokeMsg'),
				confirmLabel: get(t)('ses.revoke'),
				tone: 'danger'
			}))
		)
			return;
		message = '';
		try {
			await clientRequest(`login-histories/${id}`, { method: 'DELETE' });
			const wasCurrent = sessions.find((item) => item.id === id)?.is_current;
			sessions = sessions.filter((item) => item.id !== id);
			if (wasCurrent) window.location.assign('/logout');
		} catch (error) {
			message = error instanceof Error ? error.message : get(t)('ses.clearFailed');
		}
	}
	async function removeAll() {
		if (
			!(await confirmAction({
				title: get(t)('ses.logoutAllTitle'),
				description: get(t)('ses.logoutAllMsg'),
				confirmLabel: get(t)('ses.logoutAll'),
				tone: 'danger'
			}))
		)
			return;
		try {
			await clientRequest('login-histories', { method: 'DELETE' });
			window.location.assign('/logout');
		} catch (error) {
			message = error instanceof Error ? error.message : get(t)('ses.logoutAllFailed');
		}
	}
</script>

<svelte:head><title>Riwayat login — Portal SI</title></svelte:head>
<SectionPage
	eyebrow="Keamanan"
	title="Riwayat login"
	description="Periksa perangkat yang masuk dan cabut sesi kapan saja."
	><div class="sessions surface">
		<div class="session-actions">
			<button onclick={removeAll} disabled={sessions.length === 0}>{$t('ses.logoutAllDevices')}</button>
		</div>
		{#each sessions as item (item.id)}<article class:current={item.is_current}>
				<span><MonitorSmartphone size={20} /></span>
				<div>
					<strong
						>{item.device || get(t)('ses.unknownDevice')} · {item.browser ||
							'Browser'}{#if item.is_current}<em class="badge">{$t('ses.thisSession')}</em>{:else if item.is_active}<em
								class="badge active"><span class="dot"></span>{$t('rail.activeNow')}</em
							>{/if}</strong
					><small
						>{item.platform || get(t)('ses.unknownPlatform')}{#if item.location} · {item.location}{/if} ·
						{item.ip_address || 'IP tidak tersedia'}</small
					><time>{relativeTimeId(item.login_at)}</time>
				</div>
				<button
					onclick={() => remove(item.id)}
					aria-label={item.is_current ? get(t)('ses.endThis') : get(t)('ses.clearHistory')}
					><Trash2 size={17} /></button
				>
			</article>{/each}{#if sessions.length === 0}<p>
				Belum ada riwayat login.
			</p>{/if}{#if message}<p aria-live="polite">{message}</p>{/if}
	</div></SectionPage
>

<style>
	.sessions {
		overflow: hidden;
	}
	.sessions article {
		display: grid;
		grid-template-columns: auto 1fr auto;
		align-items: center;
		gap: 12px;
		padding: 14px 16px;
		border-bottom: 1px solid var(--color-border);
	}
	.sessions article.current {
		background: var(--color-secondary-soft);
	}
	.badge {
		margin-left: 8px;
		padding: 2px 8px;
		background: var(--color-secondary);
		border-radius: 999px;
		color: white;
		font-size: 0.6rem;
		font-weight: 700;
		font-style: normal;
		letter-spacing: 0.03em;
		vertical-align: middle;
	}
	.badge.active {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		background: #12a150;
		color: white;
	}
	.badge.active .dot {
		width: 6px;
		height: 6px;
		border-radius: 50%;
		background: #b8f5c9;
		box-shadow: 0 0 0 0 rgb(184 245 201 / 70%);
		animation: pulse-dot 1.6s ease-out infinite;
	}
	@keyframes pulse-dot {
		0% {
			box-shadow: 0 0 0 0 rgb(184 245 201 / 60%);
		}
		70% {
			box-shadow: 0 0 0 5px rgb(184 245 201 / 0%);
		}
		100% {
			box-shadow: 0 0 0 0 rgb(184 245 201 / 0%);
		}
	}
	.session-actions {
		display: flex;
		justify-content: flex-end;
		padding: 12px 16px;
		border-bottom: 1px solid var(--color-border);
	}
	.session-actions button {
		min-height: 38px;
		padding: 0 13px;
		border: 0;
		border-radius: 10px;
		background: var(--color-danger);
		color: white;
		font-size: 0.72rem;
		font-weight: 720;
	}
	.session-actions button:disabled {
		opacity: 0.5;
	}
	article > span {
		display: grid;
		width: 42px;
		height: 42px;
		place-items: center;
		background: var(--color-primary-soft);
		border-radius: 12px;
		color: var(--color-primary-strong);
	}
	article div {
		display: grid;
	}
	article strong {
		font-size: 0.82rem;
	}
	article small,
	article time,
	.sessions > p {
		color: var(--color-muted);
		font-size: 0.7rem;
	}
	article button {
		display: grid;
		width: 38px;
		height: 38px;
		place-items: center;
		background: transparent;
		border: 0;
		color: var(--color-danger);
	}
	.sessions > p {
		padding: 24px;
		text-align: center;
	}
</style>
