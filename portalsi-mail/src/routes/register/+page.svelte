<script lang="ts">
	import { enhance } from '$app/forms';
	let { form } = $props();
	let submitting = $state(false);
	const v = $derived(form?.values ?? {});
</script>

<div class="card">
	<h1>Daftar</h1>
	<p class="sub">Buat akun Portal SI. Setelah verifikasi email, kamu bisa masuk & membuat email @portalsi.com.</p>

	{#if form?.success}
		<div class="ok">{form.message}</div>
		<p class="altlink"><a href="/login">Kembali ke halaman masuk</a></p>
	{:else}
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
				Nama lengkap
				<input name="full_name" value={v.full_name ?? ''} required />
			</label>
			<label class="field">
				Username
				<input name="username" value={v.username ?? ''} autocomplete="username" required />
			</label>
			<label class="field">
				Email
				<input name="email" type="email" value={v.email ?? ''} autocomplete="email" required />
			</label>
			<label class="field">
				Password
				<input name="password" type="password" autocomplete="new-password" required />
			</label>
			<button class="btn orange" disabled={submitting}>{submitting ? 'Memproses…' : 'Daftar'}</button>
		</form>
		<p class="altlink">Sudah punya akun? <a href="/login">Masuk</a></p>
	{/if}
</div>
