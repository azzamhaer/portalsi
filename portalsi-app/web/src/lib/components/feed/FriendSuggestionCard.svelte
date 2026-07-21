<script lang="ts">
	import { Users } from '@lucide/svelte';
	import StoryAvatarLink from '$lib/components/story/StoryAvatarLink.svelte';
	import UserBadges from '$lib/components/ui/UserBadges.svelte';
	import { clientRequest } from '$lib/api/client';
	import type { PortalUser } from '$lib/types/domain';
	let { users }: { users: PortalUser[] } = $props();

	let followed = $state<Set<number>>(new Set());
	let busy = $state<Set<number>>(new Set());
	async function toggleFollow(userId: number) {
		if (busy.has(userId)) return;
		const isNow = followed.has(userId);
		busy = new Set(busy).add(userId);
		const next = new Set(followed);
		isNow ? next.delete(userId) : next.add(userId);
		followed = next; // optimistic
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

{#if users.length}<section class="friend-card" aria-label="Temukan teman">
		<header>
			<span><Users size={17} /></span>
			<div>
				<h2>Temukan teman</h2>
				<small>Orang yang mungkin Anda kenal</small>
			</div>
			<a href="/explore">Lihat semua</a>
		</header>
		<div class="friend-strip">
			{#each users as user (user.id)}<article>
					<StoryAvatarLink
						userId={user.id}
						username={user.username}
						name={user.fullName}
						avatarUrl={user.avatarUrl}
						size="lg"
						hasStory={user.hasStory}
						seen={user.storyViewed}
					/>
					<a class="identity" href={`/u/${user.username}`}
						><strong
							>{user.fullName}<UserBadges verified={user.badgeVerified} role={user.role} /></strong
						><small>@{user.username}</small></a
					>
					<button
						class="follow"
						class:on={followed.has(user.id)}
						onclick={() => toggleFollow(user.id)}
						disabled={busy.has(user.id)}
						>{followed.has(user.id)
							? 'Mengikuti'
							: user.followsYou
								? 'Ikuti balik'
								: 'Ikuti'}</button
					>
				</article>{/each}
		</div>
	</section>{/if}

<style>
	.friend-card {
		display: none;
		overflow: hidden;
		padding: 15px 0;
		background: linear-gradient(135deg, #fff8eb, #f1faf7);
		border-block: 1px solid var(--color-border);
	}
	header {
		display: flex;
		align-items: center;
		gap: 9px;
		padding: 0 15px 12px;
	}
	header > span {
		display: grid;
		width: 34px;
		height: 34px;
		place-items: center;
		background: var(--color-primary-soft);
		border-radius: 10px;
		color: var(--color-primary-strong);
	}
	header > div {
		display: grid;
		margin-right: auto;
	}
	h2 {
		margin: 0;
		font-size: 0.86rem;
	}
	header small {
		color: var(--color-muted);
		font-size: 0.62rem;
	}
	header > a {
		color: var(--color-primary-strong);
		font-size: 0.68rem;
		font-weight: 750;
	}
	.friend-strip {
		display: flex;
		gap: 9px;
		overflow-x: auto;
		padding: 0 15px 4px;
		scroll-snap-type: x proximity;
	}
	article {
		display: grid;
		width: 154px;
		min-width: 154px;
		justify-items: center;
		gap: 6px;
		padding: 14px 10px 10px;
		background: white;
		border: 1px solid var(--color-border);
		border-radius: 15px;
		scroll-snap-align: start;
		box-shadow: var(--shadow-xs);
	}
	.identity {
		display: grid;
		max-width: 100%;
		justify-items: center;
	}
	strong {
		display: flex;
		max-width: 100%;
		align-items: center;
		gap: 2px;
		overflow: hidden;
		font-size: 0.72rem;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.identity small {
		color: var(--color-muted);
		font-size: 0.62rem;
	}
	.follow {
		width: 100%;
		padding: 7px 10px;
		background: var(--color-primary);
		border: 1px solid var(--color-primary);
		border-radius: 10px;
		color: white;
		font-size: 0.72rem;
		font-weight: 750;
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
	@media (max-width: 1199px) {
		.friend-card {
			display: block;
		}
	}
</style>
