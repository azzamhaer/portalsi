<script lang="ts">
	import '../app.css';
	import { afterNavigate, beforeNavigate } from '$app/navigation';
	import type { Snippet } from 'svelte';
	import ConfirmDialog from '$lib/components/ui/ConfirmDialog.svelte';
	import GlobalProgress from '$lib/components/ui/GlobalProgress.svelte';
	import { resetProgress, startProgress } from '$lib/ui/progress';

	let { children }: { children: Snippet } = $props();
	beforeNavigate(({ from, to }) => {
		if (from?.url.href !== to?.url.href) {
			startProgress();
		}
	});
	// Reset total setelah navigasi selesai → bar tak pernah "nyangkut" walau ada
	// pasangan start/finish yang tidak seimbang (mis. request dibatalkan saat pindah).
	afterNavigate(() => resetProgress());
</script>

<svelte:head>
	<meta name="description" content="Portal SI — ruang berbagi, belajar, dan bertumbuh bersama." />
</svelte:head>

<GlobalProgress />
<ConfirmDialog />
{@render children()}
