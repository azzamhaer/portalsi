<script lang="ts">
	import { enhance } from '$app/forms';
	import { AtSign } from '@lucide/svelte';
	let { data, form } = $props();
	let submitting = $state(false);
	let local = $state(form?.local ?? '');
</script>

<div class="card">
	<div class="ico"><AtSign size={22} /></div>
	<h1>Buat email kamu</h1>
	<p class="sub">
		Pilih nama untuk alamat email @{data.domain}. Minimal 3 karakter, huruf kecil/angka, diawali
		huruf. Satu akun per pengguna dan tidak bisa diganti.
	</p>

	{#if form?.message}<div class="err">{form.message}</div>{/if}

	<form
		method="POST"
		use:enhance={() => {
			submitting = true;
			return async ({ update }) => {
				submitting = false;
				await update();
			};
		}}
	>
		<label class="field">
			Nama email
			<span class="addr">
				<input
					name="local_part"
					bind:value={local}
					placeholder="namamu"
					autocomplete="off"
					spellcheck="false"
					required
					autofocus
				/>
				<span class="suffix">@{data.domain}</span>
			</span>
		</label>
		<p class="preview">Alamat: <b>{(local || 'namamu').toLowerCase()}@{data.domain}</b></p>
		<button class="btn orange" disabled={submitting}>{submitting ? 'Membuat…' : 'Buat email'}</button>
	</form>
</div>

<style>
	.ico {
		display: grid;
		place-items: center;
		width: 48px;
		height: 48px;
		border-radius: 14px;
		background: rgba(232, 106, 23, 0.12);
		color: #e86a17;
		margin-bottom: 14px;
	}
	.addr {
		display: flex;
		align-items: stretch;
		margin-top: 5px;
		border: 1px solid rgba(26, 23, 20, 0.14);
		border-radius: 11px;
		overflow: hidden;
		background: #faf7f2;
	}
	.addr input {
		flex: 1;
		border: 0;
		background: transparent;
		padding: 11px 12px;
		font: inherit;
		font-weight: 400;
		min-width: 0;
	}
	.addr input:focus {
		outline: none;
	}
	.addr:focus-within {
		outline: 2px solid #e86a17;
		background: #fff;
	}
	.suffix {
		display: flex;
		align-items: center;
		padding: 0 12px;
		background: #efe9df;
		color: #6a6155;
		font-size: 0.9rem;
		white-space: nowrap;
	}
	.preview {
		margin: 4px 0 16px;
		font-size: 0.85rem;
		color: #6a6155;
	}
	.preview b {
		color: #1a1714;
	}
</style>
