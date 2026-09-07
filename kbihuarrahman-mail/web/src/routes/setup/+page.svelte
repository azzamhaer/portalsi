<script lang="ts">
	import { enhance } from '$app/forms';
	import { AtSign, User, Lock } from '@lucide/svelte';
	let { data, form } = $props();
	let submitting = $state(false);
	let local = $state(form?.local ?? '');
	let fullName = $state(form?.fullName ?? '');
	let password = $state('');

	const clean = $derived((local || '').toLowerCase().trim());
	const valid = $derived(/^[a-z][a-z0-9._-]{1,}$/.test(clean) && password.length >= 8 && !!fullName.trim());
</script>

<div class="card">
	<div class="ico"><AtSign size={22} /></div>
	<h1>Buat Admin Pertama</h1>
	<p class="sub">
		Ini akun admin pertama untuk <b>KBIHU Ar-Rahman Mail</b>. Dari sini kamu bisa menambah
		mailbox lain. Simpan kata sandinya baik-baik.
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
			<span class="lbl"><User size={14} /> Nama lengkap</span>
			<input name="full_name" bind:value={fullName} placeholder="Admin KBIHU Ar-Rahman" required />
		</label>

		<label class="field">
			<span class="lbl"><AtSign size={14} /> Alamat email</span>
			<span class="addr">
				<input
					name="local_part"
					bind:value={local}
					placeholder="admin"
					autocomplete="off"
					spellcheck="false"
					required
				/>
				<span class="suffix">@{data.domain}</span>
			</span>
		</label>

		<label class="field">
			<span class="lbl"><Lock size={14} /> Kata sandi (min. 8 karakter)</span>
			<input name="password" type="password" bind:value={password} minlength="8" required />
		</label>

		<div class="preview">Alamat kamu: <b>{clean || 'admin'}@{data.domain}</b></div>

		<button type="submit" class="btn" disabled={submitting || !valid}>
			{#if submitting}<span class="spin"></span>{:else}Buat admin & masuk{/if}
		</button>
	</form>
</div>

<style>
	.ico {
		display: grid;
		place-items: center;
		width: 48px;
		height: 48px;
		border-radius: 14px;
		background: rgba(21, 156, 58, 0.12);
		color: #159c3a;
		margin-bottom: 14px;
	}
	.field {
		display: block;
		margin-bottom: 14px;
	}
	.lbl {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 0.83rem;
		font-weight: 680;
		color: #3c4043;
		margin-bottom: 6px;
	}
	.field > input {
		width: 100%;
		height: 47px;
		padding: 0 13px;
		border: 1px solid rgba(26, 23, 20, 0.14);
		border-radius: 11px;
		background: #faf7f2;
		font: inherit;
	}
	.field > input:focus {
		outline: 2px solid #159c3a;
		background: #fff;
	}
	.addr {
		display: flex;
		align-items: stretch;
		border: 1px solid rgba(26, 23, 20, 0.14);
		border-radius: 11px;
		overflow: hidden;
		background: #faf7f2;
	}
	.addr input {
		flex: 1;
		border: 0;
		background: transparent;
		padding: 12px;
		font: inherit;
		min-width: 0;
	}
	.addr input:focus {
		outline: none;
	}
	.addr:focus-within {
		outline: 2px solid #159c3a;
		background: #fff;
	}
	.suffix {
		display: flex;
		align-items: center;
		padding: 0 12px;
		background: #eef1f5;
		color: #5f6368;
		font-size: 0.9rem;
		white-space: nowrap;
	}
	.preview {
		margin: 2px 0 16px;
		font-size: 0.85rem;
		color: #5f6368;
	}
	.preview b {
		color: #159c3a;
	}
	:global(.btn:disabled) {
		opacity: 0.6;
		cursor: default;
	}
</style>
