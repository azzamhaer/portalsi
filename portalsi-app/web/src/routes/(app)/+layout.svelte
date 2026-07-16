<script lang="ts">
	import type { Snippet } from 'svelte';
	import { ShieldAlert } from '@lucide/svelte';
	import { page } from '$app/state';
	import AppShell from '$lib/components/layout/AppShell.svelte';
	import type { LayoutProps } from './$types';
	let { children, data }: { children: Snippet; data: LayoutProps['data'] } = $props();
	const immersive = $derived(/^\/(?:create|stories)\//.test(page.url.pathname));
</script>

{#if data.user?.isBanned}
	<a class="ban-banner" href="/banned">
		<ShieldAlert size={16} />
		<span>Akun kamu diblokir — sebagian fitur dinonaktifkan. Ketuk untuk lihat alasan & ajukan banding.</span>
	</a>
{/if}

{#if data.user && !immersive}
	<AppShell user={data.user}>{@render children()}</AppShell>
{:else}
	{@render children()}
{/if}

<style>
	.ban-banner {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		z-index: 200;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		padding: 8px 14px;
		background: #b3283a;
		color: #fff;
		font-size: 0.8rem;
		font-weight: 600;
		text-decoration: none;
		text-align: center;
		box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
	}
	.ban-banner span {
		max-width: 60ch;
	}
</style>
