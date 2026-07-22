<script lang="ts">
	import { env } from '$env/dynamic/public';
	import { untrack } from 'svelte';
	import { replaceState } from '$app/navigation';
	import { Hash, LoaderCircle, Play, Search as SearchIcon, X } from '@lucide/svelte';
	import { clientRequest } from '$lib/api/client';
	import { searchResponseSchema, type SearchResponse } from '$lib/schemas/search';
	import Avatar from '$lib/components/ui/Avatar.svelte';
	import StoryAvatarLink from '$lib/components/story/StoryAvatarLink.svelte';
	import UserBadges from '$lib/components/ui/UserBadges.svelte';
	import { normalizeMediaUrl } from '$lib/utils/media';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
	const mediaBaseUrl = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';

	type Tab = 'all' | 'users' | 'tags' | 'posts';
	const tabs: { id: Tab; label: string }[] = [
		{ id: 'all', label: 'Semua' },
		{ id: 'users', label: 'Akun' },
		{ id: 'tags', label: 'Tag' },
		{ id: 'posts', label: 'Konten' }
	];

	let query = $state(untrack(() => data.initialQuery));
	let tab = $state<Tab>(untrack(() => data.initialTab as Tab));
	let loading = $state(false);
	let results = $state<SearchResponse>({ users: [], hashtags: [], posts: [] });
	let inputEl = $state<HTMLInputElement>();

	function syncUrl() {
		const params = new URLSearchParams();
		if (query.trim()) params.set('q', query.trim());
		if (tab !== 'all') params.set('tab', tab);
		replaceState(`/search${params.toString() ? `?${params}` : ''}`, {});
	}

	// Pencarian dengan debounce; jalan ulang saat query atau tab berubah.
	$effect(() => {
		const q = query.trim();
		const activeTab = tab;
		if (q.length < 1) {
			results = { users: [], hashtags: [], posts: [] };
			loading = false;
			return;
		}
		loading = true;
		const controller = new AbortController();
		const timer = setTimeout(async () => {
			try {
				const res = await clientRequest(
					`search?q=${encodeURIComponent(q)}&type=${activeTab}`,
					{ schema: searchResponseSchema, signal: controller.signal }
				);
				results = res;
			} catch {
				if (!controller.signal.aborted) results = { users: [], hashtags: [], posts: [] };
			} finally {
				if (!controller.signal.aborted) loading = false;
			}
		}, 260);
		return () => {
			clearTimeout(timer);
			controller.abort();
		};
	});

	function pickTag(tagName: string) {
		query = `#${tagName}`;
		tab = 'posts';
		syncUrl();
	}

	const showUsers = $derived(tab === 'all' || tab === 'users');
	const showTags = $derived(tab === 'all' || tab === 'tags');
	const showPosts = $derived(tab === 'all' || tab === 'posts');
	const hasAny = $derived(
		results.users.length > 0 || results.hashtags.length > 0 || results.posts.length > 0
	);
</script>

<svelte:head><title>Pencarian — Portal SI</title></svelte:head>

<div class="search-page">
	<div class="search-bar">
		<SearchIcon size={19} />
		<input
			bind:this={inputEl}
			bind:value={query}
			oninput={syncUrl}
			placeholder="Cari akun, #tag, atau konten…"
			aria-label="Kotak pencarian"
			autocomplete="off"
		/>
		{#if query}<button
				class="clear"
				onclick={() => {
					query = '';
					syncUrl();
					inputEl?.focus();
				}}
				aria-label="Bersihkan"><X size={17} /></button
			>{/if}
	</div>

	<nav class="search-tabs" aria-label="Jenis pencarian">
		{#each tabs as t (t.id)}
			<button
				class:active={tab === t.id}
				onclick={() => {
					tab = t.id;
					syncUrl();
				}}>{t.label}</button
			>
		{/each}
	</nav>

	{#if query.trim().length < 1}
		<div class="search-hint">
			<SearchIcon size={30} />
			<p>Cari orang, tagar, atau kata dalam postingan.</p>
		</div>
	{:else if loading && !hasAny}
		<div class="search-hint"><LoaderCircle class="spin" size={26} /></div>
	{:else if !hasAny}
		<div class="search-hint"><p>Tidak ada hasil untuk "{query.trim()}".</p></div>
	{:else}
		<div class="search-results">
			{#if showUsers && results.users.length > 0}
				<section>
					{#if tab === 'all'}<h2>Akun</h2>{/if}
					<ul class="user-list">
						{#each results.users as u (u.user_id)}
							<li>
								<StoryAvatarLink
									userId={u.user_id}
									username={u.username}
									name={u.full_name ?? u.username}
									avatarUrl={normalizeMediaUrl(u.profile_picture_thumb_url ?? u.profile_picture_url, mediaBaseUrl) ?? undefined}
									avatarFullUrl={normalizeMediaUrl(u.profile_picture_url, mediaBaseUrl) ?? undefined}
									hasStory={u.has_story ?? false}
									seen={u.story_viewed ?? false}
									size="md"
								/>
								<a class="u-info" href={`/u/${u.username}`}>
									<strong>{u.username}<UserBadges verified={u.is_verified} role={u.role} /></strong>
									<small>{u.full_name ?? u.username}{u.is_private ? ' · Private' : ''}</small>
								</a>
							</li>
						{/each}
					</ul>
				</section>
			{/if}

			{#if showTags && results.hashtags.length > 0}
				<section>
					{#if tab === 'all'}<h2>Tag</h2>{/if}
					<ul class="tag-list">
						{#each results.hashtags as t (t.tag)}
							<li>
								<button onclick={() => pickTag(t.tag)}>
									<span class="tag-ico"><Hash size={18} /></span>
									<span class="t-info">
										<strong>#{t.tag}</strong>
										<small>{t.posts_count.toLocaleString('id-ID')} postingan</small>
									</span>
								</button>
							</li>
						{/each}
					</ul>
				</section>
			{/if}

			{#if showPosts && results.posts.length > 0}
				<section>
					{#if tab === 'all'}<h2>Konten</h2>{/if}
					<div class="post-grid">
						{#each results.posts as p (p.post_id)}
							<a href={`/posts/${p.post_id}`} aria-label={`Postingan @${p.user.username}`}>
								<img
									src={normalizeMediaUrl(p.thumbnail_url ?? p.media_url, mediaBaseUrl) ??
										'/assets/logo.png'}
									alt={p.caption ?? `Postingan @${p.user.username}`}
									loading="lazy"
									onerror={(e) => {
										const img = e.currentTarget as HTMLImageElement;
										const orig = normalizeMediaUrl(p.media_url, mediaBaseUrl);
										if (!p.is_video && orig && img.src !== orig) img.src = orig;
										else if (!img.src.endsWith('/assets/logo.png')) img.src = '/assets/logo.png';
									}}
								/>
								{#if p.is_video}<span class="badge"><Play size={15} fill="currentColor" /></span>{/if}
							</a>
						{/each}
					</div>
				</section>
			{/if}
		</div>
	{/if}
</div>

<style>
	.search-page {
		width: min(100% - 24px, 720px);
		margin: 0 auto;
		padding: 14px 0 90px;
	}
	.search-bar {
		position: sticky;
		top: 8px;
		z-index: 4;
		display: flex;
		align-items: center;
		gap: 9px;
		padding: 0 14px;
		height: 48px;
		background: var(--color-surface, #fff);
		border: 1px solid var(--color-border);
		border-radius: 14px;
		box-shadow: var(--shadow-sm);
		color: var(--color-muted);
	}
	.search-bar input {
		flex: 1;
		min-width: 0;
		border: 0;
		background: none;
		font-size: 0.95rem;
		color: var(--color-text);
	}
	.search-bar input:focus {
		outline: none;
	}
	.search-bar .clear {
		display: grid;
		place-items: center;
		width: 26px;
		height: 26px;
		background: var(--color-surface-soft, #eef0f3);
		border: 0;
		border-radius: 50%;
		color: var(--color-muted);
		cursor: pointer;
	}
	.search-tabs {
		display: flex;
		gap: 6px;
		margin: 12px 0 4px;
		overflow-x: auto;
		scrollbar-width: none;
	}
	.search-tabs::-webkit-scrollbar {
		display: none;
	}
	.search-tabs button {
		flex: none;
		padding: 7px 15px;
		background: var(--color-surface-soft, #f1f2f4);
		border: 0;
		border-radius: 999px;
		font-size: 0.82rem;
		font-weight: 700;
		color: var(--color-muted);
		cursor: pointer;
	}
	.search-tabs button.active {
		background: var(--color-primary);
		color: #fff;
	}
	.search-hint {
		display: grid;
		place-items: center;
		gap: 10px;
		padding: 60px 20px;
		color: var(--color-muted);
		font-size: 0.86rem;
		text-align: center;
	}
	.search-results section {
		margin-top: 16px;
	}
	.search-results h2 {
		margin: 0 0 8px;
		font-size: 0.78rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: var(--color-muted);
	}
	.user-list,
	.tag-list {
		display: grid;
		gap: 2px;
		list-style: none;
		margin: 0;
		padding: 0;
	}
	.user-list li,
	.tag-list button {
		display: flex;
		align-items: center;
		gap: 11px;
		width: 100%;
		padding: 8px 6px;
		background: none;
		border: 0;
		border-radius: 12px;
		text-align: left;
		cursor: pointer;
	}
	.user-list li:hover,
	.tag-list button:hover {
		background: var(--color-surface-soft, #f4f5f7);
	}
	.u-info {
		flex: 1;
	}
	.u-info,
	.t-info {
		display: grid;
		min-width: 0;
	}
	.u-info strong,
	.t-info strong {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		font-size: 0.9rem;
	}
	.u-info small,
	.t-info small {
		color: var(--color-muted);
		font-size: 0.76rem;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.tag-ico {
		display: grid;
		place-items: center;
		width: 44px;
		height: 44px;
		flex: none;
		background: var(--color-secondary-soft, #eef1f6);
		border-radius: 50%;
		color: var(--color-secondary, #4b5563);
	}
	.post-grid {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 1px;
	}
	.post-grid a {
		position: relative;
		aspect-ratio: 1;
		overflow: hidden;
		border-radius: 4px;
		background: var(--color-surface-soft, #eee);
	}
	.post-grid img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.post-grid .badge {
		position: absolute;
		top: 6px;
		right: 6px;
		color: #fff;
		filter: drop-shadow(0 1px 3px rgb(0 0 0 / 55%));
	}
	:global(.spin) {
		animation: s-spin 0.8s linear infinite;
	}
	@keyframes s-spin {
		to {
			transform: rotate(360deg);
		}
	}
	@media (min-width: 768px) {
		.post-grid {
			grid-template-columns: repeat(4, minmax(0, 1fr));
		}
	}
</style>
