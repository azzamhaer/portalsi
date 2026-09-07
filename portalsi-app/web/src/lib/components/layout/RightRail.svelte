<script lang="ts">
	import { Circle } from '@lucide/svelte';
	import { t } from '$lib/i18n';
	import StoryAvatarLink from '$lib/components/story/StoryAvatarLink.svelte';
	import UserBadges from '$lib/components/ui/UserBadges.svelte';
	import { clientRequest } from '$lib/api/client';
	import type { PortalUser } from '$lib/types/domain';
	let {
		suggestions,
		onlineUsers = [],
		onlineCount = null
	}: {
		suggestions: PortalUser[];
		onlineUsers?: PortalUser[];
		onlineCount?: number | null;
	} = $props();
	const currentYear = new Date().getFullYear();

	// Batasi jumlah avatar yang tampil agar tidak overflow ke kanan di desktop.
	const MAX_ONLINE = 9;
	const shownOnline = $derived(onlineUsers.slice(0, MAX_ONLINE));
	const extraOnline = $derived(Math.max(0, onlineUsers.length - MAX_ONLINE));

	let followed = $state<Set<number>>(new Set());
	let busy = $state<Set<number>>(new Set());
	async function toggleFollow(userId: number) {
		if (busy.has(userId)) return;
		const isNow = followed.has(userId);
		busy = new Set(busy).add(userId);
		const next = new Set(followed);
		isNow ? next.delete(userId) : next.add(userId);
		followed = next;
		try {
			await clientRequest(isNow ? `unfollow/${userId}` : `follow/${userId}`, {
				method: isNow ? 'DELETE' : 'POST'
			});
		} catch {
			const rb = new Set(followed);
			isNow ? rb.add(userId) : rb.delete(userId);
			followed = rb;
		} finally {
			const b = new Set(busy);
			b.delete(userId);
			busy = b;
		}
	}
</script>

<aside class="right-rail" aria-label={$t('rail.ariaContext')}>
	<section class="rail-card online">
		<div class="section-head">
			<h2>{$t('rail.activeNow')}</h2>
			<span
				><Circle size={8} fill="currentColor" />{onlineCount === null
					? $t('rail.statusUnavailable')
					: $t('rail.online').replace('{n}', String(onlineCount))}</span
			>
		</div>
		<div class="online-avatars">
			{#each shownOnline as user (user.id)}<StoryAvatarLink
					userId={user.id}
					username={user.username}
					name={user.fullName}
					avatarUrl={user.avatarUrl}
					size="md"
					hasStory={user.hasStory}
					seen={user.storyViewed}
				/>{/each}
			{#if extraOnline > 0}<span class="online-more">+{extraOnline}</span>{/if}
			{#if onlineUsers.length === 0}<small>{$t('rail.noActiveFollowers')}</small>{/if}
		</div>
	</section>

	<section class="rail-card suggestions">
		<div class="section-head">
			<h2>{$t('rail.findFriends')}</h2>
			<a href="/explore">{$t('common.seeAll')}</a>
		</div>
		{#each suggestions as user (user.id)}
			<div class="suggestion">
				<StoryAvatarLink
					userId={user.id}
					username={user.username}
					name={user.fullName}
					avatarUrl={user.avatarUrl}
					size="sm"
					hasStory={user.hasStory}
					seen={user.storyViewed}
				/>
				<a href={`/u/${user.username}`} class="user-copy">
					<strong
						>{user.fullName}<UserBadges verified={user.badgeVerified} role={user.role} /></strong
					>
					<small>@{user.username}</small>
				</a>
				<button
					class="follow"
					class:on={followed.has(user.id)}
					onclick={() => toggleFollow(user.id)}
					disabled={busy.has(user.id)}
					>{followed.has(user.id)
						? $t('common.following')
						: user.followsYou
							? $t('rail.followBack')
							: $t('common.follow')}</button
				>
			</div>
		{/each}
	</section>

	<footer>
		<a href="/legal/privasi">{$t('foot.privacy')}</a><span>·</span><a href="/legal/syarat">{$t('foot.terms')}</a><span
			>·</span
		><a href="/legal/kebijakan">{$t('foot.policy')}</a><span>·</span><a href="/announcements">{$t('foot.announcements')}</a
		><span>·</span><a href="/marketplace">{$t('nav.marketplace')}</a>
		<p>© {currentYear} Portal SI</p>
	</footer>
</aside>

<style>
	.right-rail {
		display: grid;
		align-content: start;
		gap: 16px;
		width: var(--right-rail-width);
	}

	.rail-card {
		padding: 17px;
		background: rgb(255 255 255 / 82%);
		border: 1px solid var(--color-border);
		border-radius: var(--radius-lg);
		box-shadow: var(--shadow-xs);
		backdrop-filter: blur(12px);
		min-width: 0;
		overflow: hidden;
	}

	.section-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 14px;
	}

	.section-head h2 {
		margin: 0;
		font-size: 0.9rem;
	}

	.section-head span,
	.section-head a {
		display: flex;
		align-items: center;
		gap: 5px;
		color: var(--color-secondary);
		font-size: 0.72rem;
		font-weight: 700;
	}

	.online-avatars {
		display: flex;
		align-items: center;
		max-width: 100%;
		overflow: hidden;
	}

	.online-more {
		margin-left: 4px;
		flex: 0 0 auto;
		font-size: 0.72rem;
		font-weight: 600;
		color: var(--color-muted);
		white-space: nowrap;
	}

	.online-avatars > small {
		color: var(--color-muted);
		font-size: 0.7rem;
	}

	.online-avatars :global(a + a) {
		margin-left: -9px;
	}

	.online-avatars :global(a) {
		border-radius: 50%;
	}

	.suggestion {
		display: grid;
		grid-template-columns: auto 1fr auto;
		align-items: center;
		gap: 9px;
		padding: 9px 0;
	}

	.suggestion + .suggestion {
		border-top: 1px solid var(--color-border);
	}

	.user-copy {
		display: grid;
		min-width: 0;
	}

	.user-copy strong {
		display: flex;
		align-items: center;
		gap: 3px;
		font-size: 0.8rem;
	}

	.user-copy small {
		color: var(--color-muted);
		font-size: 0.72rem;
	}

	.follow {
		flex: 0 0 auto;
		padding: 6px 14px;
		background: var(--color-primary);
		border: 1px solid var(--color-primary);
		border-radius: 999px;
		color: white;
		font-size: 0.74rem;
		font-weight: 720;
		cursor: pointer;
	}
	.follow.on {
		background: transparent;
		color: var(--color-muted);
		border-color: var(--color-border-strong);
	}
	.follow:disabled {
		opacity: 0.6;
	}

	footer {
		padding: 0 6px;
		color: var(--color-subtle);
		font-size: 0.7rem;
	}

	footer a:hover {
		color: var(--color-text);
	}

	footer span {
		margin: 0 5px;
	}

	footer p {
		margin-top: 7px;
	}
</style>
