<script module lang="ts">
	import { SvelteMap } from 'svelte/reactivity';
	const savedPositions = new SvelteMap<string, number>();
	let activeAudio: HTMLAudioElement | null = null;
</script>

<script lang="ts">
	import { Music2, Pause, Play } from '@lucide/svelte';
	import { onMount } from 'svelte';
	let {
		src,
		title,
		artist,
		start = 0,
		clipDuration = 15,
		autoStart = false
	}: {
		src?: string;
		title: string;
		artist: string;
		start?: number;
		clipDuration?: number;
		autoStart?: boolean;
	} = $props();
	let root: HTMLDivElement;
	let audio = $state<HTMLAudioElement>();
	let playing = $state(false);
	let autoplayBlocked = $state(false);
	const key = $derived(src ?? `${title}-${artist}`);
	function playableStart() {
		return audio && Number.isFinite(audio.duration) && start < audio.duration
			? Math.max(0, start)
			: 0;
	}

	async function play() {
		if (!src || !audio) return;
		if (activeAudio && activeAudio !== audio) activeAudio.pause();
		activeAudio = audio;
		const saved = savedPositions.get(key);
		const resumeAt = saved !== undefined && saved < audio.duration ? saved : playableStart();
		audio.currentTime = resumeAt;
		try {
			await audio.play();
			autoplayBlocked = false;
		} catch {
			autoplayBlocked = true;
		}
	}
	function pause() {
		if (audio) audio.pause();
	}

	onMount(() => {
		if (!src) return;
		// Mode detail/modal: langsung putar saat dibuka (bukan menunggu hover/viewport).
		if (autoStart) {
			void play();
			return () => pause();
		}
		// Ikat pemutaran ke visibilitas POSTINGAN (kartu), bukan baris detail musik ini.
		// Aktif saat kartu melintasi pita tengah layar (bekerja untuk kartu tinggi maupun pendek).
		const target = root.closest('.post-card') ?? root.closest('.media') ?? root;
		// Kalau kartu punya video, JANGAN mulai musik saat video masih loading.
		// Musik baru main ketika video benar-benar mulai ('playing'), dan ikut jeda
		// saat video buffering ('waiting') / dijeda — supaya audio & video sinkron.
		const videoEl = target.querySelector('video') as HTMLVideoElement | null;
		let inView = false;

		const videoActive = () =>
			!!videoEl && !videoEl.paused && !videoEl.ended && videoEl.readyState >= 3;

		const onVideoPlaying = () => {
			if (inView) void play();
		};
		const onVideoStall = () => pause();

		if (videoEl) {
			videoEl.addEventListener('playing', onVideoPlaying);
			videoEl.addEventListener('waiting', onVideoStall);
			videoEl.addEventListener('pause', onVideoStall);
			videoEl.addEventListener('ended', onVideoStall);
		}

		const observer = new IntersectionObserver(
			([entry]) => {
				inView = entry.isIntersecting;
				if (!inView) {
					pause();
					return;
				}
				if (!videoEl) {
					void play(); // post gambar/audio: langsung main
				} else if (videoActive()) {
					void play(); // video sudah berjalan: sinkronkan sekarang
				}
				// selain itu: tunggu event 'playing' dari video.
			},
			{ rootMargin: '-45% 0px -45% 0px', threshold: 0 }
		);
		observer.observe(target);
		return () => {
			observer.disconnect();
			if (videoEl) {
				videoEl.removeEventListener('playing', onVideoPlaying);
				videoEl.removeEventListener('waiting', onVideoStall);
				videoEl.removeEventListener('pause', onVideoStall);
				videoEl.removeEventListener('ended', onVideoStall);
			}
			pause();
		};
	});
</script>

<div class="viewport-music" bind:this={root}>
	<Music2 size={13} />
	<span><strong>{title}</strong> — {artist}</span>
	{#if src}<audio
			bind:this={audio}
			{src}
			preload="metadata"
			onplay={() => (playing = true)}
			onpause={() => (playing = false)}
			ontimeupdate={(event) => {
				const target = event.currentTarget;
				savedPositions.set(key, target.currentTime);
				const safeStart = playableStart();
				const safeEnd = Math.min(
					safeStart + clipDuration,
					target.duration || safeStart + clipDuration
				);
				if (target.currentTime >= safeEnd) target.currentTime = safeStart;
			}}
			onended={(event) => {
				const target = event.currentTarget;
				target.currentTime = playableStart();
				void target.play().catch(() => undefined);
			}}
		></audio><button
			onclick={() => (playing ? pause() : void play())}
			aria-label={playing ? 'Jeda musik' : 'Putar musik'}
			title={autoplayBlocked ? 'Ketuk untuk mengaktifkan musik' : undefined}
			>{#if playing}<Pause size={13} fill="currentColor" />{:else}<Play
					size={13}
					fill="currentColor"
				/>{/if}</button
		>{/if}
</div>

<style>
	.viewport-music {
		display: flex;
		min-width: 0;
		align-items: center;
		gap: 5px;
	}
	.viewport-music > span {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.viewport-music strong {
		color: var(--color-text);
		font-weight: 720;
	}
	button {
		display: grid;
		width: 25px;
		height: 25px;
		flex: none;
		padding: 0;
		place-items: center;
		background: var(--color-primary-soft);
		border: 0;
		border-radius: 50%;
		color: var(--color-primary-strong);
	}
</style>
