<script lang="ts">
	import { enhance } from '$app/forms';
	let { form } = $props();
	let submitting = $state(false);
</script>

<div class="card">
	<h1>Masuk</h1>
	<p class="sub">Gunakan akun Portal SI-mu untuk mengakses email @portalsi.com.</p>

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
			Email atau username
			<input name="login" value={form?.login ?? ''} autocomplete="username" required />
		</label>
		<label class="field">
			Password
			<input name="password" type="password" autocomplete="current-password" required />
		</label>
		<button class="btn" disabled={submitting}>
			{#if submitting}<span class="spin"></span>{:else}Masuk{/if}
		</button>
	</form>

	<p class="altlink">Belum punya akun? <a href="/register">Daftar</a></p>
</div>
