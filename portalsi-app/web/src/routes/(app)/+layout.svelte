<script lang="ts">
	import type { Snippet } from 'svelte';
	import { page } from '$app/state';
	import AppShell from '$lib/components/layout/AppShell.svelte';
	import { sessionUser } from '$lib/stores/session';
	import ModerationNotice from '$lib/components/moderation/ModerationNotice.svelte';
	import type { LayoutProps } from './$types';
	let { children, data }: { children: Snippet; data: LayoutProps['data'] } = $props();
	const immersive = $derived(/^\/(?:create|stories)\//.test(page.url.pathname));
	// Sediakan identitas pengguna ke seluruh komponen (mis. cek moderator).
	$effect(() => {
		sessionUser.set(data.user ?? null);
	});
</script>

<!-- Modal pemberitahuan: postingan pemilik yang dimoderasi (muncul saat login/refresh). -->
{#if data.user}<ModerationNotice />{/if}

{#if data.user && !immersive}
	<AppShell user={data.user}>{@render children()}</AppShell>
{:else}
	{@render children()}
{/if}
