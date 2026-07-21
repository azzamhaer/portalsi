<script module lang="ts">
	// Tipe diekspor dari <script module> — export di script instance dianggap prop
	// komponen, bukan export modul, sehingga tidak bisa diimpor komponen lain.
	export type MusicTrack = {
		id: string | number;
		title: string;
		artist: string;
		durationSeconds: number;
		previewUrl: string | null;
		artworkUrl: string | null;
	};
</script>

<script lang="ts">
	import { LoaderCircle, Music2, X } from '@lucide/svelte';

	let {
		selected = $bindable<MusicTrack | null>(null),
		disabled = false
	}: { selected?: MusicTrack | null; disabled?: boolean } = $props();

	let query = $state('');
	let results = $state<MusicTrack[]>([]);
	let recommended = $state<MusicTrack[]>([]);
	let searching = $state(false);
	let recoLoading = $state(false);

	function formatTime(total: number) {
		const seconds = Math.max(0, Math.round(total));
		return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
	}

	// Endpoint yang sama persis dengan composer, jadi hasil & peringkatnya identik.
	$effect(() => {
		const q = query.trim();
		if (q.length < 2) {
			results = [];
			searching = false;
			return;
		}
		searching = true;
		const controller = new AbortController();
		const timer = window.setTimeout(async () => {
			try {
				const response = await fetch(`/api/external/music?q=${encodeURIComponent(q)}`, {
					signal: controller.signal
				});
				if (!response.ok) throw new Error();
				const payload = (await response.json()) as { tracks?: MusicTrack[] };
				results = payload.tracks ?? [];
			} catch (error) {
				if (!(error instanceof DOMException && error.name === 'AbortError')) results = [];
			} finally {
				if (!controller.signal.aborted) searching = false;
			}
		}, 280);
		return () => {
			window.clearTimeout(timer);
			controller.abort();
		};
	});

	$effect(() => {
		recoLoading = true;
		fetch('/api/external/music/recommended')
			.then((response) => (response.ok ? response.json() : { tracks: [] }))
			.then((payload: { tracks?: MusicTrack[] }) => {
				recommended = payload.tracks ?? [];
			})
			.catch(() => {
				recommended = [];
			})
			.finally(() => {
				recoLoading = false;
			});
	});
</script>

<div class="music-picker">
	<label class="mp-field">
		<span><Music2 size={15} /> Musik</span>
		<input
			bind:value={query}
			maxlength="80"
			{disabled}
			placeholder="Cari judul lagu atau artis"
			onkeydown={(event) => {
				if (event.key === 'Enter') event.preventDefault();
			}}
		/>
		{#if searching}<LoaderCircle class="mp-spinner" size={15} />{/if}
	</label>

	{#if selected}
		<div class="mp-selected">
			{#if selected.artworkUrl}<img src={selected.artworkUrl} alt="" />{:else}<Music2
					size={18}
				/>{/if}
			<span>
				<strong>{selected.title}</strong>
				<small>{selected.artist} · {formatTime(selected.durationSeconds)}</small>
			</span>
			<button type="button" {disabled} onclick={() => (selected = null)} aria-label="Hapus musik"
				><X size={15} /></button
			>
		</div>
	{/if}

	{#if results.length}
		<div class="mp-list" aria-label="Hasil musik">
			{#each results as track (track.id)}
				<button
					type="button"
					{disabled}
					class:active={selected?.id === track.id}
					onclick={() => (selected = track)}
				>
					{#if track.artworkUrl}<img src={track.artworkUrl} alt="" />{:else}<Music2 size={22} />{/if}
					<span>
						<strong>{track.title}</strong>
						<small>{track.artist} · {formatTime(track.durationSeconds)}</small>
					</span>
				</button>
			{/each}
		</div>
	{:else if query.trim().length < 2 && (recommended.length || recoLoading)}
		<div class="mp-reco-head">
			<span>Rekomendasi</span>{#if recoLoading}<LoaderCircle class="mp-spinner" size={13} />{/if}
		</div>
		{#if recommended.length}
			<div class="mp-list" aria-label="Rekomendasi musik">
				{#each recommended as track (track.id)}
					<button
						type="button"
						{disabled}
						class:active={selected?.id === track.id}
						onclick={() => (selected = track)}
					>
						{#if track.artworkUrl}<img src={track.artworkUrl} alt="" />{:else}<Music2
								size={22}
							/>{/if}
						<span>
							<strong>{track.title}</strong>
							<small>{track.artist} · {formatTime(track.durationSeconds)}</small>
						</span>
					</button>
				{/each}
			</div>
		{/if}
	{/if}
</div>

<style>
	.music-picker {
		display: grid;
		gap: 8px;
	}
	.mp-field {
		position: relative;
		display: grid;
		gap: 5px;
	}
	.mp-field > span {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 0.78rem;
		font-weight: 700;
	}
	.mp-field input {
		width: 100%;
		min-height: 40px;
		padding: 0 34px 0 12px;
		background: var(--color-surface);
		border: 1px solid var(--color-border);
		border-radius: 10px;
		font-size: 0.84rem;
	}
	.music-picker :global(.mp-spinner) {
		position: absolute;
		right: 11px;
		bottom: 12px;
		animation: mp-spin 1s linear infinite;
		color: var(--color-muted);
	}
	@keyframes mp-spin {
		to {
			transform: rotate(360deg);
		}
	}
	.mp-list {
		display: grid;
		max-height: 220px;
		gap: 2px;
		overflow-y: auto;
		padding: 4px;
		background: var(--color-canvas-deep, #f6f1e8);
		border-radius: 11px;
	}
	.mp-list button,
	.mp-selected {
		display: flex;
		width: 100%;
		align-items: center;
		gap: 9px;
		padding: 7px 9px;
		background: transparent;
		border: 0;
		border-radius: 9px;
		text-align: left;
	}
	.mp-list button {
		cursor: pointer;
	}
	.mp-list button:hover:not(:disabled),
	.mp-list button.active {
		background: var(--color-primary-soft);
	}
	.mp-list img,
	.mp-selected img {
		width: 34px;
		height: 34px;
		flex: none;
		border-radius: 7px;
		object-fit: cover;
	}
	.mp-list span,
	.mp-selected span {
		display: grid;
		flex: 1;
		min-width: 0;
	}
	.mp-list strong,
	.mp-selected strong {
		overflow: hidden;
		font-size: 0.8rem;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.mp-list small,
	.mp-selected small {
		overflow: hidden;
		color: var(--color-muted);
		font-size: 0.7rem;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.mp-selected {
		background: var(--color-primary-soft);
	}
	.mp-selected button {
		display: grid;
		width: 28px;
		height: 28px;
		flex: none;
		place-items: center;
		padding: 0;
		background: transparent;
		border: 0;
		border-radius: 50%;
		color: var(--color-muted);
		cursor: pointer;
	}
	.mp-selected button:hover:not(:disabled) {
		background: rgb(120 74 37 / 12%);
		color: var(--color-primary-strong);
	}
</style>
