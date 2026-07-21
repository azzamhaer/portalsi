<script module lang="ts">
	// Preferensi suara reels lintas-video: sekali user menyalakan suara, video reels
	// berikutnya ikut mencoba bersuara (tetap fallback ke muted bila browser memblokir),
	// sehingga suara terasa "jalan terus" saat scroll.
	let reelsSoundOn = false;
</script>

<script lang="ts">
	import {
		Expand,
		LoaderCircle,
		Pause,
		Play,
		Settings2,
		Shrink,
		Volume2,
		VolumeX
	} from '@lucide/svelte';
	type VideoSource = { quality: 'low' | 'medium' | 'original'; label: string; src: string };
	let {
		src,
		poster,
		label = 'Video postingan',
		fill = false,
		autoplay = false,
		active = undefined,
		forceMuted = false,
		preferSound = false,
		sources = [],
		onDoubleTap,
		minimal = false,
		loop = false,
		musicSrc,
		musicStart = 0,
		musicClip = 15
	}: {
		src: string;
		poster?: string;
		label?: string;
		fill?: boolean;
		autoplay?: boolean;
		/** Kendali putar eksplisit (reels): true=putar, false=jeda. Bila undefined, pakai IntersectionObserver. */
		active?: boolean | undefined;
		forceMuted?: boolean;
		preferSound?: boolean;
		sources?: VideoSource[];
		onDoubleTap?: () => void;
		minimal?: boolean;
		loop?: boolean;
		musicSrc?: string;
		musicStart?: number;
		musicClip?: number;
	} = $props();

	// Audio musik yang menyatu dengan video: ikut play/pause/seek video, dilooping pada klip.
	let musicAudio: HTMLAudioElement | undefined;
	function syncMusicPlay() {
		if (!musicAudio) return;
		if (musicAudio.currentTime < musicStart || musicAudio.currentTime > musicStart + musicClip)
			musicAudio.currentTime = musicStart;
		void musicAudio.play().catch(() => undefined);
	}
	function syncMusicPause() {
		musicAudio?.pause();
	}
	function syncMusicLoop() {
		if (musicAudio && musicAudio.currentTime > musicStart + musicClip)
			musicAudio.currentTime = musicStart;
	}

	// Daftar kualitas (fallback ke satu sumber 'Asli' bila tak ada varian).
	const available = $derived<VideoSource[]>(
		sources.length > 0 ? sources : [{ quality: 'original', label: 'Asli', src }]
	);

	function pickInitialQuality(list: VideoSource[]): VideoSource['quality'] {
		const has = (q: VideoSource['quality']) => list.some((s) => s.quality === q);
		if (typeof localStorage !== 'undefined') {
			try {
				const saved = localStorage.getItem('psi_video_quality') as VideoSource['quality'] | null;
				if (saved && has(saved)) return saved;
			} catch {
				/* abaikan */
			}
		}
		if (typeof navigator !== 'undefined') {
			const conn = (
				navigator as Navigator & { connection?: { effectiveType?: string; saveData?: boolean } }
			).connection;
			const et = conn?.effectiveType;
			if (conn?.saveData || et === 'slow-2g' || et === '2g')
				return has('low') ? 'low' : has('medium') ? 'medium' : 'original';
			if (et === '3g') return has('medium') ? 'medium' : has('low') ? 'low' : 'original';
		}
		// 4g / wifi / tak diketahui: default 'sedang' bila ada (hemat), user bisa naikkan.
		return has('medium') ? 'medium' : 'original';
	}

	let activeQuality = $state<VideoSource['quality']>(
		pickInitialQuality(sources.length > 0 ? sources : [{ quality: 'original', label: 'Asli', src }])
	);
	let qualityMenuOpen = $state(false);
	let resumeAt = 0;
	let resumePlaying = false;

	const activeSrc = $derived(
		available.find((s) => s.quality === activeQuality)?.src ?? src
	);

	function setQuality(q: VideoSource['quality']) {
		qualityMenuOpen = false;
		if (q === activeQuality || !video) return;
		resumeAt = video.currentTime;
		resumePlaying = !video.paused;
		activeQuality = q;
		try {
			localStorage.setItem('psi_video_quality', q);
		} catch {
			/* abaikan */
		}
	}
	let video: HTMLVideoElement;
	let root: HTMLDivElement;
	let loading = $state(true);
	let hasFrame = $state(false);
	// Apakah video SEHARUSNYA berputar (sedang di viewport). iOS kerap menolak/melewatkan
	// play() (timing decoder, belum siap, dsb.) sehingga video "mentok di frame pertama".
	// Solusinya: loop kecil yang terus mencoba play sampai benar-benar jalan — dihentikan
	// otomatis begitu video berputar, keluar viewport, atau di-pause manual oleh user.
	let shouldPlay = false;
	let userPaused = false;
	let playRetryTimer: ReturnType<typeof setTimeout> | null = null;

	function stopPlayRetry() {
		if (playRetryTimer) {
			clearTimeout(playRetryTimer);
			playRetryTimer = null;
		}
	}

	// Coba putar; jika belum jalan, jadwalkan percobaan ulang. Aman: berhenti sendiri saat
	// sudah playing / keluar viewport / user pause.
	function ensurePlaying() {
		if (!video || !shouldPlay || userPaused || failed || scrubbing) {
			stopPlayRetry();
			return;
		}
		if (!video.paused) {
			stopPlayRetry();
			return;
		}
		playWithFallback();
		stopPlayRetry();
		playRetryTimer = setTimeout(() => {
			playRetryTimer = null;
			if (shouldPlay && !userPaused && !failed && !scrubbing && video && video.paused)
				ensurePlaying();
		}, 350);
	}
	let failed = $state(false);
	let playing = $state(false);
	// Autoplay HARUS mulai muted agar dipercaya jalan di mobile (kebijakan browser memblokir
	// autoplay bersuara). preferSound berarti "nyalakan suara pada sentuhan pertama".
	let muted = $state(
		forceMuted ? true : minimal ? !reelsSoundOn : autoplay ? true : preferSound ? false : false
	);
	let current = $state(0);
	let duration = $state(0);
	let mediaAspect = $state('16 / 9');
	let isFullscreen = $state(false);

	// ---- Auto-hide kontrol (global untuk semua pemakaian SmartVideo) ----
	// Saat video diputar, bilah kontrol menyingkir agar tampilan bersih. Saat dijeda
	// (mis. user menyentuh video) kontrol muncul lagi, lalu menghilang sendiri setelah
	// beberapa detik. Di desktop, hover/unhover tetap mengendalikan tampilnya kontrol.
	const CONTROLS_HIDE_MS = 2600;
	let controlsVisible = $state(true);
	let hoveringPointer = $state(false);
	let controlsTimer: ReturnType<typeof setTimeout> | null = null;

	function clearControlsTimer() {
		if (controlsTimer) {
			clearTimeout(controlsTimer);
			controlsTimer = null;
		}
	}
	/** Tampilkan kontrol; `autoHide` menjadwalkan penyembunyian otomatis. */
	function revealControls(autoHide = true) {
		clearControlsTimer();
		controlsVisible = true;
		if (!autoHide || hoveringPointer || scrubbing || qualityMenuOpen) return;
		controlsTimer = setTimeout(() => {
			controlsTimer = null;
			// Jangan sembunyikan bila user sedang berinteraksi dengan kontrol.
			if (hoveringPointer || scrubbing || qualityMenuOpen) return;
			controlsVisible = false;
		}, CONTROLS_HIDE_MS);
	}
	function hideControlsNow() {
		clearControlsTimer();
		controlsVisible = false;
	}
	// Bersihkan timer saat komponen dilepas.
	$effect(() => () => clearControlsTimer());

	// Lacak status fullscreen agar tombol bisa toggle & keluar (termasuk WebKit).
	$effect(() => {
		function sync() {
			const doc = document as Document & { webkitFullscreenElement?: Element };
			const fs = Boolean(document.fullscreenElement || doc.webkitFullscreenElement);
			isFullscreen = fs;
			// Setelah keluar fullscreen, matikan lagi kontrol native (kembali ke kontrol kustom).
			if (!fs) video?.removeAttribute('controls');
		}
		document.addEventListener('fullscreenchange', sync);
		document.addEventListener('webkitfullscreenchange', sync);
		return () => {
			document.removeEventListener('fullscreenchange', sync);
			document.removeEventListener('webkitfullscreenchange', sync);
		};
	});

	function playWithFallback() {
		if (!video) return;
		// iOS: autoplay tanpa gesture HANYA diizinkan bila video muted + playsinline.
		// Pastikan status muted benar sebelum play saat autoplay.
		if (autoplay && !forceMuted && video.muted) video.defaultMuted = true;
		void video.play().catch(() => {
			if (!forceMuted && !video.muted) {
				video.muted = true;
				muted = true;
				video.defaultMuted = true;
				void video.play().catch(() => undefined);
			}
		});
	}

	function togglePlayback() {
		if (!video || failed) return;
		if (video.paused) {
			userPaused = false;
			ensurePlaying();
		} else {
			userPaused = true;
			stopPlayRetry();
			video.pause();
		}
	}

	// Saat komponen dibongkar (keluar/menggulir reels), jeda DAN batalkan unduhan video
	// agar koneksi bebas — kalau tidak, video preload="auto" yang masih mengunduh menyandera
	// pool koneksi browser sehingga gambar di halaman lain (explore/profil) tidak mau load.
	// Efek ini tanpa dependensi reaktif → cleanup hanya jalan saat komponen benar-benar dibongkar,
	// jadi aman melepas src (tidak mengganggu pemutaran yang sedang berjalan).
	$effect(() => {
		const el = video;
		return () => {
			stopPlayRetry();
			try {
				el?.pause();
				el?.removeAttribute('src');
				el?.load();
			} catch {
				/* abaikan */
			}
		};
	});

	// ---- Seek tipis untuk mode minimal (reels) ----
	let scrubbing = $state(false);
	let scrubTime = $state(0);
	let seekEl: HTMLDivElement;
	let previewCanvas: HTMLCanvasElement | undefined;
	let wasPlayingBeforeScrub = false;
	const progress = $derived(duration > 0 ? current / duration : 0);
	const scrubRatio = $derived(duration > 0 ? scrubTime / duration : 0);
	const previewLeftPct = $derived(Math.min(90, Math.max(10, scrubRatio * 100)));

	function pointerToTime(clientX: number) {
		if (!seekEl) return 0;
		const rect = seekEl.getBoundingClientRect();
		const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
		return ratio * (duration || 0);
	}
	function drawPreview() {
		if (!previewCanvas || !video?.videoWidth) return;
		const ctx = previewCanvas.getContext('2d');
		if (!ctx) return;
		const maxH = 132;
		const scale = maxH / video.videoHeight;
		previewCanvas.width = Math.max(1, Math.round(video.videoWidth * scale));
		previewCanvas.height = maxH;
		try {
			ctx.drawImage(video, 0, 0, previewCanvas.width, previewCanvas.height);
		} catch {
			/* abaikan */
		}
	}
	function startScrub(e: PointerEvent) {
		if (!video || !duration) return;
		e.stopPropagation();
		scrubbing = true;
		stopPlayRetry();
		wasPlayingBeforeScrub = !video.paused;
		video.pause();
		try {
			seekEl.setPointerCapture(e.pointerId);
		} catch {
			/* abaikan */
		}
		moveScrub(e);
	}
	function moveScrub(e: PointerEvent) {
		if (!scrubbing || !video) return;
		e.stopPropagation();
		const t = pointerToTime(e.clientX);
		scrubTime = t;
		video.currentTime = t;
	}
	function endScrub(e: PointerEvent) {
		if (!scrubbing) return;
		scrubbing = false;
		try {
			seekEl.releasePointerCapture(e.pointerId);
		} catch {
			/* abaikan */
		}
		if (wasPlayingBeforeScrub) ensurePlaying();
	}

	// Bedakan tap tunggal (play/pause) vs ganda (like) bila onDoubleTap disediakan.
	let tapTimer: ReturnType<typeof setTimeout> | null = null;
	function onVideoTap() {
		// Sentuhan pertama pada video autoplay yang di-mute: nyalakan suara (dalam gesture
		// pengguna sehingga diizinkan mobile), tanpa mem-pause videonya.
		if (preferSound && !forceMuted && video && video.muted) {
			video.muted = false;
			muted = false;
			if (minimal) reelsSoundOn = true;
			return;
		}
		if (!onDoubleTap) {
			togglePlayback();
			return;
		}
		if (tapTimer) {
			clearTimeout(tapTimer);
			tapTimer = null;
			onDoubleTap();
			return;
		}
		tapTimer = setTimeout(() => {
			tapTimer = null;
			togglePlayback();
		}, 260);
	}
	function seek(event: Event) {
		const value = Number((event.currentTarget as HTMLInputElement).value);
		if (Number.isFinite(value)) video.currentTime = value;
	}
	function format(value: number) {
		if (!Number.isFinite(value)) return '0:00';
		const minutes = Math.floor(value / 60);
		return `${minutes}:${Math.floor(value % 60)
			.toString()
			.padStart(2, '0')}`;
	}
	function fullscreen(event: MouseEvent) {
		event.stopPropagation();
		const doc = document as Document & {
			webkitFullscreenElement?: Element;
			webkitExitFullscreen?: () => void;
		};
		// Sudah fullscreen → keluar.
		if (document.fullscreenElement || doc.webkitFullscreenElement) {
			if (document.exitFullscreen) void document.exitFullscreen();
			else doc.webkitExitFullscreen?.();
			return;
		}
		// Fullscreen pada ELEMEN VIDEO (bukan container): browser menjaga rasio asli
		// (letterbox) + menyediakan kontrol native, termasuk tombol keluar.
		const el = video as HTMLVideoElement & {
			webkitEnterFullscreen?: () => void;
			webkitRequestFullscreen?: () => void;
		};
		if (!el) return;
		if (el.requestFullscreen) {
			el.setAttribute('controls', 'controls'); // desktop: kontrol native (ada tombol keluar)
			void el.requestFullscreen();
		} else if (el.webkitEnterFullscreen) {
			el.webkitEnterFullscreen(); // iOS: pakai native player
		} else if (el.webkitRequestFullscreen) {
			el.setAttribute('controls', 'controls');
			el.webkitRequestFullscreen();
		}
	}

	// Kendali putar EKSPLISIT (reels): halaman reels menentukan mana yang aktif berdasarkan
	// posisi scroll, jadi tak bergantung IntersectionObserver per-video (yang timing-nya rawan).
	$effect(() => {
		if (active === undefined) return;
		// Baca `active` agar efek reaktif terhadap perubahannya.
		const isActive = active;
		if (isActive) {
			shouldPlay = true;
			userPaused = false;
			// Coba nyalakan suara bila user sudah memilih sound (fallback muted bila diblokir).
			if (minimal && !forceMuted && reelsSoundOn && video) {
				video.muted = false;
				muted = false;
			}
			ensurePlaying();
		} else {
			shouldPlay = false;
			userPaused = false;
			stopPlayRetry();
			if (video && !video.paused) video.pause();
		}
	});

	// Autoplay ala Instagram: putar saat video masuk viewport, jeda saat digulir menjauh.
	$effect(() => {
		if (!autoplay || active !== undefined) return;
		const el = video;
		const container = root;
		if (!el || !container) return;
		// iOS: cerminkan atribut muted agar autoplay diizinkan sejak awal.
		if (!forceMuted && el.muted) el.defaultMuted = true;
		const observer = new IntersectionObserver(
			(entries) => {
				const entry = entries[0];
				if (!entry) return;
				if (entry.isIntersecting && entry.intersectionRatio >= 0.55) {
					shouldPlay = true;
					userPaused = false;
					ensurePlaying();
				} else {
					shouldPlay = false;
					userPaused = false;
					stopPlayRetry();
					if (!el.paused) el.pause();
				}
			},
			{ threshold: [0, 0.55, 1] }
		);
		observer.observe(container);
		return () => observer.disconnect();
	});
</script>

<div
	class="smart-video"
	bind:this={root}
	class:playing
	class:failed
	class:fill
	class:controls-visible={controlsVisible}
	style:aspect-ratio={fill ? undefined : mediaAspect}
	onpointerenter={(event) => {
		// Hanya pointer presisi (mouse/trackpad) yang dianggap "hover".
		if (event.pointerType !== 'mouse') return;
		hoveringPointer = true;
		revealControls(false);
	}}
	onpointermove={(event) => {
		if (event.pointerType !== 'mouse') return;
		hoveringPointer = true;
		revealControls(false);
	}}
	onpointerleave={(event) => {
		if (event.pointerType !== 'mouse') return;
		hoveringPointer = false;
		// Keluar hover: sembunyikan bila sedang diputar, jeda tetap tampil sebentar.
		if (playing) hideControlsNow();
		else revealControls();
	}}
	onpointerdown={(event) => {
		// Sentuhan di mana pun pada video memunculkan kembali kontrol.
		if (event.pointerType === 'mouse') return;
		revealControls();
	}}
>
	<video
		bind:this={video}
		src={activeSrc}
		{poster}
		bind:muted
		{loop}
		preload={active === false ? 'metadata' : autoplay ? 'auto' : 'metadata'}
		playsinline
		aria-label={label}
		onclick={onVideoTap}
		onloadstart={() => (loading = true)}
		onwaiting={() => {
			loading = true;
		}}
		oncanplay={() => {
				ensurePlaying();
			hasFrame = true;
			loading = false;
		}}
		onloadeddata={() => {
			hasFrame = true;
			loading = false;
			if (resumeAt > 0) {
				video.currentTime = resumeAt;
				resumeAt = 0;
				if (resumePlaying) playWithFallback();
			}
		}}
		onloadedmetadata={() => {
			duration = video.duration;
			if (video.videoWidth && video.videoHeight)
				mediaAspect = `${video.videoWidth} / ${video.videoHeight}`;
			loading = false;
		}}
		onplay={() => {
			playing = true;
			// Mulai diputar → bersihkan tampilan (kecuali kursor sedang di atas video).
			if (hoveringPointer) revealControls(false);
			else hideControlsNow();
			if (musicSrc) syncMusicPlay();
		}}
		onpause={() => {
			playing = false;
			// Dijeda → kontrol muncul, lalu hilang sendiri setelah beberapa detik.
			revealControls();
			if (musicSrc) syncMusicPause();
		}}
		onended={() => {
			playing = false;
			revealControls();
			if (musicSrc) syncMusicPause();
		}}
		ontimeupdate={() => {
			current = video.currentTime;
			duration = video.duration || duration;
			if (loading) loading = false;
				if (musicSrc) syncMusicLoop();
		}}
		onplaying={() => {
				playing = true;
				loading = false;
			}}
			onstalled={() => {
				if (video && video.readyState < 3) loading = true;
			}}
			onseeked={() => {
			if (scrubbing) drawPreview();
		}}
		onerror={() => {
			failed = true;
			loading = false;
		}}><track kind="captions" label="Takarir tidak tersedia" /></video
	>
	{#if musicSrc}<audio bind:this={musicAudio} src={musicSrc} preload="auto"></audio>{/if}
	{#if loading && !failed}<div class="video-loading" aria-label="Memuat video">
			<LoaderCircle class="spin" size={30} />
		</div>{/if}
	{#if failed}<div class="video-state error">
			<span>Video belum dapat diputar.</span><button
				onclick={(event) => {
					event.stopPropagation();
					hasFrame = false;
					loading = true;
					video.load();
					failed = false;
				}}>Coba lagi</button
			>
		</div>{/if}
	{#if !playing && !loading && !failed}<button
			class="center-play"
			aria-label="Putar video"
			onclick={(event) => {
				event.stopPropagation();
				togglePlayback();
			}}><Play size={56} fill="currentColor" strokeWidth={0} /></button
		>{/if}
	{#if minimal}
		<div class="minimal-controls">
			{#if !forceMuted}<button
					onclick={(event) => {
						event.stopPropagation();
						video.muted = !video.muted;
						muted = video.muted;
						if (minimal) reelsSoundOn = !video.muted;
					}}
					aria-label={muted ? 'Nyalakan suara' : 'Bisukan'}
					>{#if muted}<VolumeX size={18} />{:else}<Volume2 size={18} />{/if}</button
				>{/if}
			{#if available.length > 1}<div class="quality">
					<button
						class="quality-btn"
						onclick={(event) => {
							event.stopPropagation();
							qualityMenuOpen = !qualityMenuOpen;
						}}
						aria-label="Kualitas video"><Settings2 size={16} /></button
					>
					{#if qualityMenuOpen}<ul class="quality-menu">
							{#each available as opt (opt.quality)}<li>
									<button
										class:active={opt.quality === activeQuality}
										onclick={(event) => {
											event.stopPropagation();
											setQuality(opt.quality);
										}}>{opt.label}</button
									>
								</li>{/each}
						</ul>{/if}
				</div>{/if}
		</div>

		<!-- Seek tipis di paling bawah + preview saat drag -->
		<div
			class="seek"
			class:scrubbing
			bind:this={seekEl}
			role="slider"
			tabindex="0"
			aria-label="Geser posisi video"
			aria-valuemin="0"
			aria-valuemax={Math.round(duration) || 0}
			aria-valuenow={Math.round(current) || 0}
			onpointerdown={startScrub}
			onpointermove={moveScrub}
			onpointerup={endScrub}
			onpointercancel={endScrub}
		>
			{#if scrubbing}
				<div class="seek-preview" style:left={`${previewLeftPct}%`}>
					<canvas bind:this={previewCanvas}></canvas>
					<span>{format(scrubTime)} / {format(duration)}</span>
				</div>
			{/if}
			<div class="seek-track">
				<div class="seek-fill" style:width={`${(scrubbing ? scrubRatio : progress) * 100}%`}></div>
				<span class="seek-knob" style:left={`${(scrubbing ? scrubRatio : progress) * 100}%`}></span>
			</div>
		</div>
	{:else}
		<div class="video-controls">
		<button onclick={togglePlayback} aria-label={playing ? 'Jeda video' : 'Putar video'}
			>{#if playing}<Pause size={18} fill="currentColor" />{:else}<Play
					size={18}
					fill="currentColor"
				/>{/if}</button
		>
		<span>{format(current)}</span>
		<input
			aria-label="Posisi video"
			type="range"
			min="0"
			max={duration || 0}
			step="0.1"
			value={current}
			oninput={seek}
		/>
		<span>{format(duration)}</span>
		{#if !forceMuted}<button
				onclick={() => {
					video.muted = !video.muted;
					muted = video.muted;
				}}
				aria-label={muted ? 'Nyalakan suara' : 'Bisukan'}
				>{#if muted}<VolumeX size={18} />{:else}<Volume2 size={18} />{/if}</button
			>{/if}
		{#if available.length > 1}<div class="quality">
				<button
					class="quality-btn"
					onclick={(event) => {
						event.stopPropagation();
						qualityMenuOpen = !qualityMenuOpen;
					}}
					aria-label="Kualitas video"
					aria-expanded={qualityMenuOpen}
					><Settings2 size={16} /><small
						>{available.find((s) => s.quality === activeQuality)?.label ?? 'Asli'}</small
					></button
				>
				{#if qualityMenuOpen}<ul class="quality-menu">
						{#each available as opt (opt.quality)}<li>
								<button
									class:active={opt.quality === activeQuality}
									onclick={(event) => {
										event.stopPropagation();
										setQuality(opt.quality);
									}}>{opt.label}</button
								>
							</li>{/each}
					</ul>{/if}
			</div>{/if}
		<button
			class="fs-btn"
			onclick={fullscreen}
			aria-label={isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'}
			>{#if isFullscreen}<Shrink size={17} />{:else}<Expand size={17} />{/if}</button
		>
		</div>
	{/if}
</div>

<style>
	.smart-video {
		position: relative;
		width: 100%;
		max-height: 82vh;
		overflow: hidden;
		background: #0b0c0d;
		cursor: pointer;
	}
	.smart-video.fill {
		height: 100%;
		max-height: none;
		aspect-ratio: auto;
	}
	/* Saat fullscreen: isi layar penuh & abaikan aspect-ratio inline supaya video
	   (object-fit: contain) tampil dengan rasio aslinya, terpusat, dengan bar hitam. */
	.smart-video:fullscreen,
	.smart-video:-webkit-full-screen {
		width: 100vw;
		height: 100vh;
		max-height: none;
		aspect-ratio: auto !important;
		background: #000;
		cursor: default;
	}
	.smart-video:fullscreen video,
	.smart-video:-webkit-full-screen video {
		width: 100%;
		height: 100%;
		object-fit: contain;
	}
	video {
		width: 100%;
		height: 100%;
		object-fit: contain;
	}
	.video-state {
		position: absolute;
		inset: 0;
		display: grid;
		place-content: center;
		justify-items: center;
		gap: 8px;
		background: rgb(8 10 12 / 66%);
		color: white;
		font-size: 0.75rem;
		backdrop-filter: blur(4px);
	}
	/* Loader ringkas di tengah (tidak menggelapkan seluruh video). */
	.video-loading {
		position: absolute;
		top: 50%;
		left: 50%;
		display: grid;
		place-items: center;
		width: 56px;
		height: 56px;
		transform: translate(-50%, -50%);
		background: rgb(8 10 12 / 52%);
		border-radius: 50%;
		color: white;
		backdrop-filter: blur(4px);
		pointer-events: none;
	}
	.video-state.error button {
		padding: 7px 11px;
		background: white;
		border: 0;
		border-radius: 9px;
		font-weight: 720;
	}
	:global(.spin) {
		animation: spin 0.8s linear infinite;
	}
	.center-play {
		position: absolute;
		top: 50%;
		left: 50%;
		display: grid;
		padding: 0;
		place-items: center;
		background: none;
		border: 0;
		border-radius: 0;
		color: rgb(255 255 255 / 94%);
		transform: translate(-50%, -50%);
		filter: drop-shadow(0 2px 10px rgb(0 0 0 / 55%));
	}
	.video-controls {
		position: absolute;
		right: 10px;
		bottom: 10px;
		left: 10px;
		display: flex;
		min-height: 44px;
		align-items: center;
		gap: 8px;
		padding: 7px 9px;
		background: linear-gradient(135deg, rgb(12 15 18 / 82%), rgb(36 42 49 / 72%));
		border: 1px solid rgb(255 255 255 / 14%);
		border-radius: 13px;
		color: white;
		opacity: 0;
		transform: translateY(8px);
		transition: 180ms ease;
		backdrop-filter: blur(12px);
	}
	/* Kontrol tampil bila: JS menandai terlihat (jeda / baru disentuh), sedang difokus,
	   atau kursor presisi sedang hover. Hover dibatasi ke perangkat ber-mouse supaya
	   di layar sentuh tidak "nyangkut" terlihat karena hover yang diemulasi. */
	.smart-video.controls-visible .video-controls,
	.smart-video:focus-within .video-controls {
		opacity: 1;
		transform: none;
	}
	@media (hover: hover) and (pointer: fine) {
		.smart-video:hover .video-controls {
			opacity: 1;
			transform: none;
		}
	}
	.video-controls button {
		display: grid;
		width: 30px;
		height: 30px;
		flex: none;
		padding: 0;
		place-items: center;
		background: transparent;
		border: 0;
		border-radius: 8px;
		color: inherit;
	}
	.video-controls button:hover {
		background: rgb(255 255 255 / 14%);
	}
	.video-controls span {
		flex: none;
		font-size: 0.6rem;
		font-variant-numeric: tabular-nums;
	}
	.video-controls input {
		min-width: 40px;
		flex: 1;
		accent-color: #f28a22;
	}
	.minimal-controls {
		position: absolute;
		top: 12px;
		right: 12px;
		z-index: 4;
		display: flex;
		align-items: flex-start;
		gap: 8px;
	}
	.minimal-controls > button,
	.minimal-controls .quality-btn {
		display: grid !important;
		width: 36px !important;
		height: 36px;
		place-items: center;
		padding: 0 !important;
		background: rgb(0 0 0 / 42%);
		border: 0;
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
		backdrop-filter: blur(6px);
	}
	.minimal-controls .quality-menu {
		top: calc(100% + 6px);
		bottom: auto;
	}
	.seek {
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 6;
		display: flex;
		height: 22px;
		align-items: flex-end;
		cursor: pointer;
		touch-action: none;
	}
	.seek-track {
		position: relative;
		width: 100%;
		height: 3px;
		background: rgb(255 255 255 / 22%);
		transition: height 0.12s ease;
	}
	.seek.scrubbing .seek-track {
		height: 6px;
	}
	.seek-fill {
		position: absolute;
		inset: 0 auto 0 0;
		background: #fff;
	}
	.seek-knob {
		position: absolute;
		top: 50%;
		width: 12px;
		height: 12px;
		margin-left: -6px;
		border-radius: 50%;
		background: #fff;
		box-shadow: 0 1px 4px rgb(0 0 0 / 45%);
		transform: translateY(-50%) scale(0);
		transition: transform 0.12s ease;
	}
	.seek.scrubbing .seek-knob {
		transform: translateY(-50%) scale(1);
	}
	.seek-preview {
		position: absolute;
		bottom: 18px;
		display: grid;
		justify-items: center;
		gap: 5px;
		transform: translateX(-50%);
		pointer-events: none;
	}
	.seek-preview canvas {
		width: auto;
		height: 92px;
		border-radius: 9px;
		border: 2px solid rgb(255 255 255 / 85%);
		box-shadow: 0 6px 20px rgb(0 0 0 / 45%);
		background: #000;
	}
	.seek-preview span {
		padding: 2px 9px;
		background: rgb(0 0 0 / 72%);
		border-radius: 999px;
		color: #fff;
		font-size: 0.67rem;
		font-weight: 700;
		font-variant-numeric: tabular-nums;
	}
	.quality {
		position: relative;
		flex: none;
	}
	.quality-btn {
		display: inline-flex !important;
		width: auto !important;
		align-items: center;
		gap: 3px;
		padding: 0 6px !important;
	}
	.quality-btn small {
		font-size: 0.58rem;
		font-weight: 700;
	}
	.quality-menu {
		position: absolute;
		right: 0;
		bottom: calc(100% + 6px);
		z-index: 3;
		min-width: 96px;
		margin: 0;
		padding: 4px;
		list-style: none;
		background: rgb(18 20 24 / 96%);
		border: 1px solid rgb(255 255 255 / 14%);
		border-radius: 10px;
		box-shadow: 0 8px 24px rgb(0 0 0 / 40%);
	}
	.quality-menu li {
		display: block;
	}
	.quality-menu button {
		display: flex !important;
		width: 100% !important;
		height: auto !important;
		align-items: center;
		justify-content: flex-start;
		padding: 8px 11px !important;
		background: transparent;
		border: 0;
		border-radius: 7px;
		color: #fff;
		font-size: 0.74rem;
		font-weight: 600;
		text-align: left;
		cursor: pointer;
	}
	.quality-menu button:hover {
		background: rgb(255 255 255 / 12%);
	}
	.quality-menu button.active {
		background: rgb(242 138 34 / 26%);
		color: #ffcf9a;
		font-weight: 800;
	}
	@keyframes spin {
		to {
			transform: rotate(360deg);
		}
	}
	@media (hover: none) {
		.video-controls {
			opacity: 1;
			transform: none;
		}
	}
</style>
