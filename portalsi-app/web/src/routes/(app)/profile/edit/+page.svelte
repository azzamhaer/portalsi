<script lang="ts">
	import { enhance } from '$app/forms';
	import { ArrowLeft, Check, Save } from '@lucide/svelte';
	import { untrack } from 'svelte';
	import ImageCropper from '$lib/components/media/ImageCropper.svelte';
	import { cropImageToRegion, type CropRegion } from '$lib/utils/image-crop';
	import type { PageProps } from './$types';
	import MentionTextarea from '$lib/components/ui/MentionTextarea.svelte';
	let { data, form }: PageProps = $props();
	let profilePreview = $state(untrack(() => data.user?.avatarUrl ?? ''));
	let bannerPreview = $state(untrack(() => data.user?.bannerUrl ?? ''));
	let profileFile = $state<File | null>(null);
	let bannerFile = $state<File | null>(null);
	let profileRegion = $state<CropRegion | null>(null);
	let bannerRegion = $state<CropRegion | null>(null);
	let bio = $state(untrack(() => data.user?.bio ?? ''));
	let username = $state(untrack(() => data.user?.username ?? ''));
	let fullName = $state(untrack(() => data.user?.fullName ?? ''));

	// Nilai awal sebagai pembanding "sudah berubah atau belum". Disegarkan setelah
	// penyimpanan berhasil agar tombol kembali nonaktif sampai ada perubahan berikutnya.
	let baseline = $state({
		username: untrack(() => data.user?.username ?? ''),
		fullName: untrack(() => data.user?.fullName ?? ''),
		bio: untrack(() => data.user?.bio ?? '')
	});

	let submitting = $state(false);
	let savedModal = $state(false);

	const dirty = $derived(
		username !== baseline.username ||
			fullName !== baseline.fullName ||
			bio !== baseline.bio ||
			profileFile !== null ||
			bannerFile !== null
	);
	function previewFile(event: Event, target: 'profile' | 'banner') {
		const file = (event.currentTarget as HTMLInputElement).files?.[0];
		if (!file) return;
		const url = URL.createObjectURL(file);
		if (target === 'profile') {
			profileFile = file;
			profilePreview = url;
			profileRegion = null;
		} else {
			bannerFile = file;
			bannerPreview = url;
			bannerRegion = null;
		}
	}
</script>

<svelte:head
	><title>Edit profil — Portal SI</title><meta name="robots" content="noindex" /></svelte:head
>
<main class="form-page surface">
	<header>
		<a href="/profile" aria-label="Kembali"><ArrowLeft size={19} /></a>
		<div>
			<p class="eyebrow">Profil Anda</p>
			<h1>Edit profil</h1>
		</div>
	</header>
	<form
		method="POST"
		enctype="multipart/form-data"
		use:enhance={async ({ formData }) => {
			submitting = true;
			if (profileFile && profileRegion)
				formData.set('profile_picture', await cropImageToRegion(profileFile, profileRegion, 1024));
			if (bannerFile && bannerRegion && bannerFile.type !== 'image/gif')
				formData.set('banner', await cropImageToRegion(bannerFile, bannerRegion, 2000));
			return async ({ update, result }) => {
				await update({ reset: false });
				submitting = false;
				if (result.type === 'success') {
					// Titik nol baru: tombol nonaktif lagi sampai ada perubahan berikutnya.
					baseline = { username, fullName, bio };
					profileFile = null;
					bannerFile = null;
					savedModal = true;
				}
			};
		}}
	>
		<div class="profile-preview">
			<div
				class="banner"
				style:background-image={bannerPreview ? `url(${bannerPreview})` : undefined}
			></div>
			<div class="avatar">
				{#if profilePreview}<img src={profilePreview} alt="Pratinjau foto profil" />{:else}<span
						>{fullName.slice(0, 1) || '?'}</span
					>{/if}
			</div>
			<!-- Pratinjau ikut berubah saat mengetik, jadi hasilnya terlihat sebelum disimpan. -->
			<p><strong>{fullName}</strong><small>@{username}</small></p>
		</div>
		<label
			><span>Username</span><input
				name="username"
				required
				minlength="3"
				maxlength="50"
				bind:value={username}
			/></label
		>
		{#if profileFile}<ImageCropper
				src={profilePreview}
				aspect={1}
				label="Crop foto profil"
				round
				onregion={(region) => (profileRegion = region)}
			/>{/if}
		<label
			><span>Nama lengkap</span><input
				name="full_name"
				required
				maxlength="255"
				bind:value={fullName}
			/></label
		>
		{#if bannerFile && bannerFile.type !== 'image/gif'}<ImageCropper
				src={bannerPreview}
				aspect={5}
				label="Crop banner profil"
				onregion={(region) => (bannerRegion = region)}
			/>{/if}
		<label
			><span>Bio</span><MentionTextarea
				bind:value={bio}
				name="bio"
				maxlength={1000}
				rows={5}
				placeholder="Ceritakan tentang diri Anda…"
			/></label
		>
		<label
			><span>Foto profil <small>maks. 10 MB</small></span><input
				name="profile_picture"
				type="file"
				accept="image/*"
				onchange={(event) => previewFile(event, 'profile')}
			/></label
		>
		<label
			><span>Banner <small>maks. 20 MB</small></span><input
				name="banner"
				type="file"
				accept="image/jpeg,image/png,image/webp,image/gif"
				onchange={(event) => previewFile(event, 'banner')}
			/></label
		>
		{#if form?.message && !savedModal}<p class="message" role="alert">{form.message}</p>{/if}
		<button class="save" type="submit" disabled={submitting || !dirty}>
			{#if submitting}<span class="spinner" aria-hidden="true"></span> Menyimpan…{:else}<Save
					size={17}
				/> Simpan perubahan{/if}
		</button>
		{#if !dirty && !submitting}
			<small class="save-hint">Belum ada perubahan untuk disimpan.</small>
		{/if}
	</form>
</main>

{#if savedModal}
	<div class="modal-scrim" role="presentation" onclick={() => (savedModal = false)}>
		<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
		<div class="modal" role="dialog" aria-modal="true" onclick={(e) => e.stopPropagation()}>
			<span class="modal-ico"><Check size={26} /></span>
			<strong>Profil diperbarui</strong>
			<p>Perubahan Anda sudah tersimpan dan langsung terlihat di profil.</p>
			<button type="button" onclick={() => (savedModal = false)}>Selesai</button>
		</div>
	</div>
{/if}

<style>
	.form-page {
		width: min(100% - 32px, 640px);
		margin: 28px auto;
		overflow: hidden;
	}
	header {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 16px 18px;
		border-bottom: 1px solid var(--color-border);
	}
	header a {
		display: grid;
		width: 40px;
		height: 40px;
		place-items: center;
		border-radius: 50%;
	}
	header p {
		margin: 0;
	}
	h1 {
		margin: 0;
		font-size: 1.15rem;
	}
	form {
		display: grid;
		gap: 16px;
		padding: 22px;
	}
	.profile-preview {
		position: relative;
		min-height: 180px;
		overflow: hidden;
		background: var(--color-surface-soft);
		border: 1px solid var(--color-border);
		border-radius: 16px;
	}
	.profile-preview .banner {
		aspect-ratio: 5 / 1;
		background: linear-gradient(135deg, #f7cf91, #b7d9c9);
		background-position: center;
		background-size: cover;
	}
	.profile-preview .avatar {
		position: absolute;
		top: 72px;
		left: 18px;
		display: grid;
		width: 76px;
		height: 76px;
		overflow: hidden;
		place-items: center;
		background: var(--color-primary);
		border: 4px solid white;
		border-radius: 50%;
		color: white;
		font-size: 1.35rem;
		font-weight: 800;
	}
	.profile-preview .avatar img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.profile-preview p {
		display: grid;
		margin: 11px 16px 12px 108px;
	}
	.profile-preview p small {
		color: var(--color-muted);
	}
	label {
		display: grid;
		gap: 7px;
	}
	label span {
		font-size: 0.78rem;
		font-weight: 700;
	}
	label small {
		color: var(--color-muted);
		font-weight: 500;
	}
	input {
		width: 100%;
		padding: 11px 12px;
		background: var(--color-surface-soft);
		border: 1px solid var(--color-border);
		border-radius: 11px;
	}
	:global(.mention-field textarea) {
		padding: 11px 12px;
		background: var(--color-surface-soft);
		border: 1px solid var(--color-border);
		border-radius: 11px;
		resize: vertical;
	}
	/* Disamakan dengan tombol simpan di halaman Privasi akun. */
	.save {
		display: inline-flex;
		min-height: 46px;
		align-items: center;
		justify-content: center;
		gap: 8px;
		background: var(--color-primary);
		border: 0;
		border-radius: 12px;
		color: white;
		font-weight: 720;
		cursor: pointer;
		transition: opacity 0.15s ease;
	}
	.save:disabled {
		opacity: 0.55;
		cursor: not-allowed;
	}
	.save-hint {
		margin-top: -4px;
		color: var(--color-muted);
		font-size: 0.72rem;
		text-align: center;
	}
	.spinner {
		width: 15px;
		height: 15px;
		border: 2px solid rgb(255 255 255 / 45%);
		border-top-color: #fff;
		border-radius: 50%;
		animation: psi-spin 0.7s linear infinite;
	}
	@keyframes psi-spin {
		to {
			transform: rotate(360deg);
		}
	}
	.message {
		margin: 0;
		color: var(--color-danger);
		font-size: 0.76rem;
	}
	/* ---- Popup berhasil (mengikuti gaya halaman Privasi akun) ---- */
	.modal-scrim {
		position: fixed;
		inset: 0;
		z-index: 60;
		display: grid;
		place-items: center;
		padding: 20px;
		background: rgb(17 12 6 / 45%);
		animation: psi-fade 0.16s ease;
	}
	.modal {
		display: grid;
		width: min(100%, 340px);
		justify-items: center;
		gap: 8px;
		padding: 26px 22px 20px;
		background: var(--color-surface, #fff);
		border-radius: 18px;
		text-align: center;
		animation: psi-pop 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
	}
	.modal-ico {
		display: grid;
		place-items: center;
		width: 52px;
		height: 52px;
		margin-bottom: 4px;
		border-radius: 50%;
		background: var(--color-secondary-soft, #e7f6ee);
		color: var(--color-secondary, #1a9d5a);
	}
	.modal strong {
		font-size: 1.05rem;
	}
	.modal p {
		margin: 0;
		color: var(--color-muted);
		font-size: 0.84rem;
		line-height: 1.45;
	}
	.modal button {
		width: 100%;
		min-height: 44px;
		margin-top: 10px;
		background: var(--color-primary);
		border: 0;
		border-radius: 12px;
		color: #fff;
		font-weight: 700;
		cursor: pointer;
	}
	@keyframes psi-fade {
		from {
			opacity: 0;
		}
	}
	@keyframes psi-pop {
		from {
			opacity: 0;
			transform: scale(0.94);
		}
	}
	@media (max-width: 767px) {
		.form-page {
			width: 100%;
			margin: 0;
			border: 0;
			border-radius: 0;
		}
	}
</style>
