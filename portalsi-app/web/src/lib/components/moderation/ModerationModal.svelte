<script module lang="ts">
	// Template alasan moderasi. `text` = alasan jadi yang dikirim ke server & ditampilkan
	// ke pemilik. Bisa dipilih beberapa; ditambah catatan bebas kalau perlu.
	export const MODERATION_REASONS = [
		{
			id: 'not_eligible',
			label: 'Tidak memenuhi kriteria platform',
			text: 'Konten tidak memenuhi kriteria yang diterima platform, misalnya mengandung watermark atau hak cipta dari platform lain (TikTok, CapCut, dsb.), atau kualitasnya terlalu rendah dan tidak memiliki nilai.'
		},
		{
			id: 'watermark',
			label: 'Watermark / hak cipta platform lain',
			text: 'Konten mengandung watermark atau tanda hak cipta dari platform lain (TikTok, CapCut, Instagram, dll.).'
		},
		{
			id: 'low_quality',
			label: 'Kualitas rendah / tanpa nilai',
			text: 'Kualitas konten terlalu rendah dan dianggap tidak memiliki nilai bagi komunitas.'
		},
		{
			id: 'reupload',
			label: 'Repost / bukan karya sendiri',
			text: 'Konten merupakan unggahan ulang / bukan karya orisinal pengunggah.'
		},
		{
			id: 'inappropriate',
			label: 'Konten tidak pantas',
			text: 'Konten tidak pantas atau melanggar pedoman komunitas Portal SI.'
		},
		{
			id: 'spam',
			label: 'Spam / menyesatkan',
			text: 'Konten bersifat spam, promosi berlebihan, atau menyesatkan.'
		}
	];
</script>

<script lang="ts">
	import { X, ShieldAlert, LoaderCircle } from '@lucide/svelte';
	import { clientRequest } from '$lib/api/client';
	import { portal } from '$lib/actions/portal';

	let {
		postId,
		onClose,
		onDone
	}: { postId: number; onClose: () => void; onDone?: () => void } = $props();

	let selected = $state<Set<string>>(new Set());
	let note = $state('');
	let submitting = $state(false);
	let error = $state('');

	function toggle(id: string) {
		const next = new Set(selected);
		if (next.has(id)) next.delete(id);
		else next.add(id);
		selected = next;
	}

	// Alasan template (tanpa catatan) — dipisah dengan baris kosong bila lebih dari satu.
	// Ini yang dikirim ke notifikasi; catatan bebas dikirim terpisah (hanya tampil di detail).
	const reasonText = $derived(
		MODERATION_REASONS.filter((r) => selected.has(r.id))
			.map((r) => `• ${r.text}`)
			.join('\n\n')
	);
	const canSubmit = $derived(reasonText.trim().length > 0 && !submitting);

	async function submit() {
		if (!canSubmit) return;
		submitting = true;
		error = '';
		try {
			const body = new FormData();
			body.set('reason', reasonText);
			if (note.trim()) body.set('note', note.trim());
			await clientRequest(`posts/${postId}/moderate`, { method: 'POST', body });
			onDone?.();
			onClose();
		} catch (e) {
			error =
				e instanceof Error && e.message ? e.message : 'Moderasi gagal dikirim. Coba lagi.';
		} finally {
			submitting = false;
		}
	}
</script>

<div class="mod-scrim" use:portal role="presentation" onclick={onClose}>
	<div
		class="mod-card"
		role="dialog"
		aria-modal="true"
		aria-label="Moderasi postingan"
		onclick={(e) => e.stopPropagation()}
	>
		<header>
			<span class="mod-ico"><ShieldAlert size={20} /></span>
			<div>
				<strong>Moderasi postingan</strong>
				<small>Pilih alasan. Postingan akan diturunkan (soft take-down, retensi 30 hari).</small>
			</div>
			<button class="mod-close" onclick={onClose} aria-label="Tutup"><X size={18} /></button>
		</header>

		<div class="mod-reasons">
			{#each MODERATION_REASONS as reason (reason.id)}
				<button
					type="button"
					class="reason"
					class:on={selected.has(reason.id)}
					onclick={() => toggle(reason.id)}
				>
					<span class="check" aria-hidden="true"></span>
					<span>{reason.label}</span>
				</button>
			{/each}
		</div>

		<label class="mod-note">
			<span>Catatan tambahan (opsional)</span>
			<textarea
				bind:value={note}
				rows="2"
				maxlength="1000"
				placeholder="Tulis alasan spesifik bila perlu…"
			></textarea>
		</label>

		{#if error}<p class="mod-error">{error}</p>{/if}

		<div class="mod-actions">
			<button class="ghost" type="button" onclick={onClose}>Batal</button>
			<button class="danger" type="button" disabled={!canSubmit} onclick={submit}>
				{#if submitting}<LoaderCircle size={16} class="mod-spin" /> Memproses…{:else}Moderasi &
					turunkan{/if}
			</button>
		</div>
	</div>
</div>

<style>
	.mod-scrim {
		position: fixed;
		inset: 0;
		z-index: 3000;
		display: grid;
		place-items: center;
		padding: 16px;
		background: rgb(15 12 9 / 55%);
		backdrop-filter: blur(3px);
	}
	.mod-card {
		width: min(100%, 460px);
		max-height: 90vh;
		overflow-y: auto;
		padding: 18px;
		background: var(--color-surface, #fff);
		border-radius: 18px;
		box-shadow: 0 24px 60px rgb(0 0 0 / 35%);
	}
	header {
		display: flex;
		align-items: flex-start;
		gap: 11px;
		margin-bottom: 14px;
	}
	.mod-ico {
		display: grid;
		width: 38px;
		height: 38px;
		flex: none;
		place-items: center;
		background: rgb(192 57 43 / 12%);
		border-radius: 11px;
		color: #c0392b;
	}
	header > div {
		flex: 1;
		min-width: 0;
	}
	header strong {
		display: block;
		font-size: 1rem;
	}
	header small {
		color: var(--color-muted);
		font-size: 0.76rem;
		line-height: 1.4;
	}
	.mod-close {
		display: grid;
		width: 30px;
		height: 30px;
		flex: none;
		place-items: center;
		padding: 0;
		background: transparent;
		border: 0;
		border-radius: 50%;
		color: var(--color-muted);
		cursor: pointer;
	}
	.mod-close:hover {
		background: var(--color-surface-soft, #f1f2f4);
	}
	.mod-reasons {
		display: grid;
		gap: 7px;
	}
	.reason {
		display: flex;
		align-items: center;
		gap: 10px;
		width: 100%;
		padding: 11px 12px;
		background: var(--color-canvas-deep, #f6f1e8);
		border: 1px solid transparent;
		border-radius: 11px;
		color: var(--color-text);
		font-size: 0.85rem;
		text-align: left;
		cursor: pointer;
	}
	.reason .check {
		display: grid;
		width: 18px;
		height: 18px;
		flex: none;
		place-items: center;
		border: 2px solid var(--color-border, #cbd0d6);
		border-radius: 6px;
	}
	.reason.on {
		border-color: var(--color-primary);
		background: var(--color-primary-soft);
	}
	.reason.on .check {
		background: var(--color-primary);
		border-color: var(--color-primary);
	}
	.reason.on .check::after {
		content: '✓';
		color: #fff;
		font-size: 0.7rem;
		font-weight: 800;
	}
	.mod-note {
		display: grid;
		gap: 5px;
		margin-top: 12px;
	}
	.mod-note > span {
		font-size: 0.76rem;
		font-weight: 700;
	}
	.mod-note textarea {
		width: 100%;
		padding: 9px 11px;
		background: var(--color-surface);
		border: 1px solid var(--color-border);
		border-radius: 10px;
		font-size: 0.84rem;
		resize: vertical;
	}
	.mod-error {
		margin: 10px 0 0;
		color: #c0392b;
		font-size: 0.78rem;
	}
	.mod-actions {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
		margin-top: 16px;
	}
	.mod-actions button {
		min-height: 40px;
		padding: 0 16px;
		border-radius: 11px;
		font-size: 0.84rem;
		font-weight: 720;
		cursor: pointer;
	}
	.mod-actions .ghost {
		background: transparent;
		border: 1px solid var(--color-border);
		color: var(--color-text);
	}
	.mod-actions .danger {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		background: #c0392b;
		border: 0;
		color: #fff;
	}
	.mod-actions .danger:disabled {
		opacity: 0.55;
		cursor: default;
	}
	:global(.mod-spin) {
		animation: mod-spin 0.8s linear infinite;
	}
	@keyframes mod-spin {
		to {
			transform: rotate(360deg);
		}
	}
</style>
