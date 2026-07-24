<script lang="ts">
	import { onMount, tick } from 'svelte';
	import { z } from 'zod';
	import { ChevronLeft, ChevronRight, LoaderCircle, ShieldCheck } from '@lucide/svelte';
	import { clientRequest } from '$lib/api/client';
	import { portal } from '$lib/actions/portal';

	const schema = z.object({
		policies: z.array(
			z.object({
				id: z.coerce.number().int().positive(),
				title: z.string(),
				description: z.string().nullish(),
				slides: z
					.array(
						z.object({
							title: z.string().nullish(),
							body: z.string().nullish(),
							image_url: z.string().nullish()
						})
					)
					.catch([]),
				read_seconds: z.coerce.number().int().nonnegative().catch(5),
				require_agreement: z.boolean().catch(true),
				agreement_text: z.string().nullish(),
				version: z.coerce.number().int().positive().catch(1)
			})
		)
	});

	type Policy = z.infer<typeof schema>['policies'][number];

	let queue = $state<Policy[]>([]);
	let slide = $state(0);
	let secondsLeft = $state(0);
	let agreed = $state(false);
	let submitting = $state(false);

	const current = $derived(queue[0] ?? null);
	const slides = $derived(current?.slides?.length ? current.slides : [{ title: current?.title, body: current?.description, image_url: '' }]);
	const isLastSlide = $derived(current ? slide >= slides.length - 1 : false);
	const canContinue = $derived(
		!!current &&
			isLastSlide &&
			secondsLeft <= 0 &&
			(!current.require_agreement || agreed) &&
			!submitting
	);

	onMount(async () => {
		try {
			const res = await clientRequest('policies/active', { schema });
			queue = res.policies;
		} catch {
			queue = [];
		}
	});

	// Timer wajib-baca: reset tiap kali kebijakan aktif berganti.
	$effect(() => {
		const c = current;
		if (!c) return;
		// reset state saat kebijakan berganti
		untrackReset(c);
		secondsLeft = c.read_seconds;
		const id = setInterval(() => {
			secondsLeft = Math.max(0, secondsLeft - 1);
			if (secondsLeft <= 0) clearInterval(id);
		}, 1000);
		return () => clearInterval(id);
	});

	// Reset slide & persetujuan tanpa memicu ulang efek timer.
	function untrackReset(_c: Policy) {
		slide = 0;
		agreed = false;
	}

	function next() {
		if (slide < slides.length - 1) slide += 1;
	}
	function prev() {
		if (slide > 0) slide -= 1;
	}

	async function acceptCurrent() {
		const c = current;
		if (!c || !canContinue) return;
		submitting = true;
		try {
			await clientRequest(`policies/${c.id}/accept`, { method: 'POST' });
			queue = queue.slice(1);
			await tick();
		} catch {
			/* biarkan modal terbuka; pengguna coba lagi */
		} finally {
			submitting = false;
		}
	}
</script>

{#if current}
	<div
		class="pg-scrim"
		use:portal
		role="dialog"
		aria-modal="true"
		aria-label={current.title}
	>
		<div class="pg-card">
			{#each [slides[slide]] as s (slide)}
				<div class="pg-slidewrap">
					{#if s.image_url}
						<div class="pg-hero"><img src={s.image_url} alt="" /></div>
					{:else}
						<div class="pg-hero pg-hero-fallback"><ShieldCheck size={44} /></div>
					{/if}
					<div class="pg-body">
						{#if slides.length > 1}
							<div class="pg-dots">
								{#each slides as _, i (i)}<span class:on={i === slide}></span>{/each}
							</div>
						{/if}
						{#if s.title}<h2>{s.title}</h2>{/if}
						{#if s.body}<p class="pg-text">{s.body}</p>{/if}
					</div>
				</div>
			{/each}

			<div class="pg-foot">
				{#if !isLastSlide}
					<div class="pg-nav">
						<button class="pg-ghost" onclick={prev} disabled={slide === 0}
							><ChevronLeft size={18} /> Kembali</button
						>
						<button class="pg-primary" onclick={next}
							>Lanjut <ChevronRight size={18} /></button
						>
					</div>
				{:else}
					{#if current.require_agreement}
						<label class="pg-agree">
							<input type="checkbox" bind:checked={agreed} />
							<span
								>{current.agreement_text ||
									'Saya telah membaca dan memahami kebijakan platform, serta siap menerima konsekuensi berupa moderasi konten maupun pembatasan/pemblokiran akun apabila melanggar syarat dan ketentuan yang berlaku.'}</span
							>
						</label>
					{/if}
					<div class="pg-nav">
						{#if slides.length > 1}
							<button class="pg-ghost" onclick={prev}><ChevronLeft size={18} /> Kembali</button>
						{/if}
						<button class="pg-primary pg-accept" onclick={acceptCurrent} disabled={!canContinue}>
							{#if submitting}<LoaderCircle size={17} class="pg-spin" /> Menyimpan…{:else if secondsLeft > 0}Baca dulu ({secondsLeft}s){:else}Setuju & lanjutkan{/if}
						</button>
					</div>
				{/if}
				{#if queue.length > 1}
					<small class="pg-more">{queue.length} kebijakan menunggu persetujuan</small>
				{/if}
			</div>
		</div>
	</div>
{/if}

<style>
	.pg-scrim {
		position: fixed;
		inset: 0;
		z-index: 3300;
		display: grid;
		place-items: center;
		padding: 16px;
		background: rgb(12 10 8 / 68%);
		backdrop-filter: blur(4px);
	}
	.pg-card {
		display: flex;
		flex-direction: column;
		width: min(100%, 460px);
		max-height: 92vh;
		overflow: hidden;
		background: var(--color-surface, #fff);
		border-radius: 22px;
		box-shadow: 0 30px 70px rgb(0 0 0 / 45%);
	}
	.pg-slidewrap {
		display: flex;
		flex-direction: column;
		overflow-y: auto;
	}
	.pg-hero {
		position: relative;
		width: 100%;
		aspect-ratio: 16 / 9;
		background: var(--color-canvas-deep, #f1ece3);
		flex: none;
	}
	.pg-hero img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.pg-hero-fallback {
		display: grid;
		place-items: center;
		aspect-ratio: 16 / 7;
		background: linear-gradient(135deg, var(--color-primary), var(--color-primary-strong, #1f6f43));
		color: #fff;
	}
	.pg-body {
		padding: 18px 22px 6px;
	}
	.pg-dots {
		display: flex;
		gap: 6px;
		margin-bottom: 12px;
	}
	.pg-dots span {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		background: var(--color-border, #d9d3c8);
	}
	.pg-dots span.on {
		width: 20px;
		border-radius: 4px;
		background: var(--color-primary);
	}
	.pg-body h2 {
		margin: 0 0 8px;
		font-size: 1.25rem;
		line-height: 1.25;
	}
	.pg-text {
		margin: 0;
		color: var(--color-text);
		font-size: 0.9rem;
		line-height: 1.6;
		white-space: pre-line;
	}
	.pg-foot {
		padding: 14px 22px 20px;
		border-top: 1px solid var(--color-border);
	}
	.pg-agree {
		display: flex;
		gap: 10px;
		align-items: flex-start;
		margin-bottom: 14px;
		padding: 12px;
		background: var(--color-canvas-deep, #f6f1e8);
		border-radius: 12px;
		font-size: 0.82rem;
		line-height: 1.5;
		cursor: pointer;
	}
	.pg-agree input {
		margin-top: 2px;
		width: 18px;
		height: 18px;
		flex: none;
		accent-color: var(--color-primary);
	}
	.pg-nav {
		display: flex;
		gap: 10px;
	}
	.pg-nav button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
		flex: 1;
		min-height: 46px;
		border-radius: 13px;
		font-size: 0.88rem;
		font-weight: 720;
		cursor: pointer;
	}
	.pg-ghost {
		background: transparent;
		border: 1px solid var(--color-border);
		color: var(--color-text);
		flex: 0 0 auto !important;
		padding: 0 16px;
	}
	.pg-primary {
		background: var(--color-primary);
		border: 0;
		color: #fff;
	}
	.pg-accept:disabled {
		opacity: 0.55;
		cursor: default;
	}
	.pg-more {
		display: block;
		margin-top: 12px;
		text-align: center;
		color: var(--color-muted);
		font-size: 0.72rem;
	}
	:global(.pg-spin) {
		animation: pg-spin 0.8s linear infinite;
	}
	@keyframes pg-spin {
		to {
			transform: rotate(360deg);
		}
	}
</style>
