<script lang="ts">
	import type { Snippet } from 'svelte';
	import { ArrowLeft, Quote } from '@lucide/svelte';
	let { children, mode = 'login' }: { children: Snippet; mode?: 'login' | 'register' } = $props();
</script>

<main class="auth-shell">
	<section class="auth-visual" aria-label="Tentang KBIHU Ar-Rahman Mail">
		<a class="brand" href="https://kbihuarrahman.com"><img src="/logo.png" alt="" /><span>KBIHU <b>Mail</b></span></a>
		<div class="visual-copy">
			<span class="quote"><Quote size={22} fill="currentColor" /></span>
			<p>
				{mode === 'register'
					? 'Buat email resmi untuk seluruh keluarga besar KBIHU Ar-Rahman.'
					: 'Satu kotak masuk untuk semua kabar penting. Rapi, cepat, dan aman.'}
			</p>
			<small>Layanan email keluarga besar KBIHU Ar-Rahman</small>
		</div>
	</section>

	<section class="form-side">
		<a class="back" href="https://kbihuarrahman.com"><ArrowLeft size={17} /> kbihuarrahman.com</a>
		<div class="mobile-brand"><img src="/logo.png" alt="" /><b>KBIHU Ar-Rahman Mail</b></div>
		<div class="form-wrap">{@render children()}</div>
	</section>
</main>

<style>
	:global(:root) {
		--color-surface: #ffffff;
		--color-surface-soft: #fffdf8;
		--color-text: #23201d;
		--color-muted: #706b65;
		--color-subtle: #9b958e;
		--color-border-strong: #ddd4c5;
		--color-primary: #159c3a;
		--color-primary-strong: #0f7d2e;
		--color-danger: #c7322b;
		--color-danger-soft: #fff0ef;
		--focus-ring: 0 0 0 3px rgb(21 156 58 / 22%);
	}
	@font-face {
		font-family: 'Alan Sans';
		src: url('/assets/fonts/AlanSans-VariableFont_wght.ttf') format('truetype');
		font-style: normal;
		font-weight: 100 900;
		font-display: swap;
	}
	.auth-shell {
		display: grid;
		min-height: 100vh;
		background: var(--color-surface);
		font-family: 'Alan Sans', Inter, ui-sans-serif, system-ui, sans-serif;
	}
	.auth-visual {
		position: relative;
		display: none;
		overflow: hidden;
		padding: 36px;
		background: linear-gradient(155deg, #f08a2a 0%, #d1650f 45%, #a94a0b 100%);
		color: white;
	}
	.auth-visual::after {
		position: absolute;
		inset: 0;
		background: radial-gradient(120% 90% at 80% 0%, rgb(255 255 255 / 12%), transparent 60%);
		content: '';
	}
	.brand,
	.mobile-brand {
		position: relative;
		z-index: 2;
		display: flex;
		align-items: center;
		gap: 10px;
		font-size: 1.3rem;
		font-weight: 720;
		letter-spacing: -0.03em;
		text-decoration: none;
		color: inherit;
	}
	.brand img,
	.mobile-brand img {
		width: 40px;
		height: 40px;
		border-radius: 11px;
	}
	.brand b,
	.mobile-brand b {
		color: #ffaf36;
	}
	.mobile-brand b {
		color: #159c3a;
	}
	.visual-copy {
		position: relative;
		z-index: 2;
		max-width: 33rem;
		align-self: end;
		margin-bottom: 30px;
	}
	.quote {
		display: grid;
		width: 44px;
		height: 44px;
		place-items: center;
		background: rgb(255 255 255 / 15%);
		border: 1px solid rgb(255 255 255 / 20%);
		border-radius: 14px;
		color: #fff;
	}
	.visual-copy p {
		margin: 18px 0 14px;
		font-size: clamp(1.6rem, 3vw, 2.7rem);
		font-weight: 680;
		letter-spacing: -0.04em;
		line-height: 1.12;
	}
	.visual-copy small {
		color: rgb(255 255 255 / 70%);
	}
	.visual-image {
		position: absolute;
		inset: 0;
		width: 100%;
		height: 100%;
		object-fit: cover;
		opacity: 0.5;
	}
	.form-side {
		display: flex;
		min-width: 0;
		flex-direction: column;
		padding: 24px 20px;
		background:
			radial-gradient(circle at 100% 0%, rgb(21 156 58 / 8%), transparent 22rem),
			var(--color-surface-soft);
	}
	.back {
		display: flex;
		width: fit-content;
		min-height: 40px;
		margin-bottom: 28px;
		align-items: center;
		gap: 6px;
		color: var(--color-muted);
		font-size: 0.83rem;
		font-weight: 650;
		text-decoration: none;
	}
	.mobile-brand {
		justify-content: center;
		margin: 30px 0 26px;
		font-size: 1.2rem;
	}
	.form-wrap {
		width: 100%;
		max-width: 430px;
		margin: auto;
	}
	@media (min-width: 900px) {
		.auth-shell {
			grid-template-columns: minmax(430px, 1.08fr) minmax(460px, 0.92fr);
		}
		.auth-visual {
			display: grid;
		}
		.form-side {
			padding: 32px clamp(32px, 5vw, 72px);
		}
		.mobile-brand {
			display: none;
		}
	}
</style>
