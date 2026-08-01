<script lang="ts">
	import { enhance } from '$app/forms';
	import { KeyRound } from '@lucide/svelte';
	let { form } = $props();
	let submitting = $state(false);
</script>

<div class="card">
	<div class="lock"><KeyRound size={22} /></div>
	<h1>Akses beta</h1>
	<p class="sub">
		Portal SI Mail masih tahap beta. Masukkan <b>master password</b> dari admin untuk melanjutkan
		ke pembuatan email &amp; webmail.
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
			Master password
			<input name="master_password" type="password" autocomplete="off" required autofocus />
		</label>
		<button class="btn" disabled={submitting}>{submitting ? 'Memeriksa…' : 'Buka akses'}</button>
	</form>
</div>

<style>
	.lock {
		display: grid;
		place-items: center;
		width: 48px;
		height: 48px;
		border-radius: 14px;
		background: rgba(31, 111, 235, 0.12);
		color: #1f6feb;
		margin-bottom: 14px;
	}
</style>
