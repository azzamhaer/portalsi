<script lang="ts">
	import {
		BadgeCheck,
		ExternalLink,
		Heart,
		MessageSquareText,
		RotateCcw,
		Search,
		ShieldCheck,
		Store,
		Wallet
	} from '@lucide/svelte';
	import SectionPage from '$lib/components/layout/SectionPage.svelte';

	const MARKETPLACE_URL = 'https://marketplace.portalsi.com/';

	const highlights = [
		{
			icon: BadgeCheck,
			title: 'Penjualnya jelas orangnya',
			body: 'Setiap toko wajib lolos verifikasi KTP sebelum boleh memajang produk. Tidak ada toko anonim.'
		},
		{
			icon: MessageSquareText,
			title: 'Tanya dulu, beli belakangan',
			body: 'Ada tombol tanya di tiap halaman produk. Chatnya langsung nyambung ke pemilik toko, bukan bot.'
		},
		{
			icon: Wallet,
			title: 'Bayar sesuai kebiasaan Anda',
			body: 'QRIS, transfer virtual account, OVO, DANA, ShopeePay, sampai bayar tunai di Alfamart atau Indomaret.'
		},
		{
			icon: RotateCcw,
			title: 'Barang bermasalah? Ajukan retur',
			body: 'Pengajuan pengembalian dibuka dari halaman pesanan dan ditinjau admin, jadi tidak perlu adu argumen sendiri.'
		}
	];
</script>

<svelte:head><title>Marketplace — Portal SI</title><meta name="robots" content="noindex" /></svelte:head
>

<SectionPage title="Marketplace">
	<section class="mp-hero surface">
		<div class="mp-copy">
			<span class="mp-badge"><Store size={15} /> Toko milik warga Portal SI</span>
			<h2>Belanja di tempat yang penjualnya Anda kenal</h2>
			<p>
				Produk dari toko-toko yang dikelola sendiri oleh anggota komunitas. Harga langsung dari
				pemiliknya, tanpa perantara, dan bisa ditanyakan dulu sebelum Anda memutuskan beli.
			</p>
			<div class="mp-cta">
				<a class="primary" href={MARKETPLACE_URL} target="_blank" rel="noopener noreferrer"
					>Mulai belanja <ExternalLink size={16} /></a
				>
				<a
					class="ghost"
					href={`${MARKETPLACE_URL}seller/register`}
					target="_blank"
					rel="noopener noreferrer">Buka toko sendiri</a
				>
			</div>
			<small class="mp-note"
				><ShieldCheck size={14} /> Tautan menuju marketplace.portalsi.com — alamat resmi yang sudah
				diizinkan.</small
			>
		</div>

		<div class="mp-preview" aria-label="Pratinjau tampilan Portal SI Marketplace">
			<div class="mp-phone">
				<div class="mp-status"><span>9:41</span><b>● ● ●</b></div>
				<div class="mp-search"><Search size={13} /><span>Cari produk atau toko</span></div>
				<div class="mp-tags">
					<span class="on">Semua</span><span>elektronik</span><span>fashion</span><span>harian</span>
				</div>
				<div class="mp-grid">
					<article>
						<i class="thumb a"><Heart size={12} class="mp-heart" /></i>
						<b>Headset Bluetooth</b>
						<small>Rp185.000</small>
						<em><BadgeCheck size={10} /> Fashion Hub</em>
					</article>
					<article>
						<i class="thumb b"><Heart size={12} class="mp-heart on" /></i>
						<b>Tumbler 900ml</b>
						<small>Rp62.000</small>
						<em><BadgeCheck size={10} /> Rumah Botol</em>
					</article>
					<article>
						<i class="thumb c"><Heart size={12} class="mp-heart" /></i>
						<b>Kemeja Katun</b>
						<small>Rp139.000</small>
						<em><BadgeCheck size={10} /> Lemari Kita</em>
					</article>
					<article>
						<i class="thumb d"><Heart size={12} class="mp-heart" /></i>
						<b>Lampu Belajar</b>
						<small>Rp98.000</small>
						<em><BadgeCheck size={10} /> Terang Jaya</em>
					</article>
				</div>
			</div>
		</div>
	</section>

	<section class="mp-points">
		{#each highlights as item (item.title)}
			<article class="surface">
				<span><item.icon size={19} /></span>
				<strong>{item.title}</strong>
				<p>{item.body}</p>
			</article>
		{/each}
	</section>

	<section class="mp-seller surface">
		<div>
			<h3>Punya barang yang bisa dijual?</h3>
			<p>
				Daftar sebagai penjual, unggah KTP untuk verifikasi, lalu atur produk, ongkir, dan voucher
				toko Anda sendiri. Pencairan saldo diajukan kapan pun dari dasbor penjual.
			</p>
		</div>
		<a href={`${MARKETPLACE_URL}seller/register`} target="_blank" rel="noopener noreferrer"
			>Daftar jadi penjual <ExternalLink size={15} /></a
		>
	</section>
</SectionPage>

<style>
	/* ---------- Hero ---------- */
	.mp-hero {
		display: grid;
		grid-template-columns: 1.05fr 0.95fr;
		align-items: center;
		gap: clamp(24px, 5vw, 56px);
		padding: clamp(26px, 5vw, 56px);
		overflow: hidden;
		border-radius: 22px;
	}
	.mp-badge {
		display: inline-flex;
		align-items: center;
		gap: 7px;
		padding: 6px 12px;
		background: var(--color-primary-soft);
		border-radius: 999px;
		color: var(--color-primary-strong);
		font-size: 0.72rem;
		font-weight: 720;
	}
	.mp-copy h2 {
		margin: 16px 0 10px;
		font-size: clamp(1.55rem, 3.6vw, 2.35rem);
		line-height: 1.12;
		letter-spacing: -0.035em;
	}
	.mp-copy > p {
		max-width: 32rem;
		margin: 0;
		color: var(--color-muted);
		line-height: 1.6;
	}
	.mp-cta {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		margin-top: 22px;
	}
	.mp-cta a {
		display: inline-flex;
		height: 46px;
		align-items: center;
		gap: 8px;
		padding: 0 18px;
		border-radius: 12px;
		font-size: 0.86rem;
		font-weight: 720;
	}
	.mp-cta .primary {
		background: var(--color-primary);
		color: white;
	}
	.mp-cta .ghost {
		background: transparent;
		border: 1px solid var(--color-border, rgb(0 0 0 / 14%));
		color: var(--color-text);
	}
	.mp-cta .ghost:hover {
		background: var(--color-primary-soft);
	}
	.mp-note {
		display: flex;
		align-items: center;
		gap: 6px;
		margin-top: 14px;
		color: var(--color-muted);
		font-size: 0.74rem;
	}

	/* ---------- Pratinjau ponsel ---------- */
	.mp-preview {
		display: grid;
		place-items: center;
	}
	.mp-phone {
		position: relative;
		width: min(100%, 290px);
		padding: 26px 13px 16px;
		background: var(--color-surface, #fffdf8);
		border: 8px solid #191714;
		border-radius: 38px;
		box-shadow: 0 26px 60px rgb(39 27 14 / 24%);
		transform: rotate(2deg);
	}
	.mp-phone::before {
		position: absolute;
		top: 9px;
		left: 50%;
		width: 74px;
		height: 17px;
		background: #191714;
		border-radius: 99px;
		content: '';
		transform: translateX(-50%);
	}
	.mp-status {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 4px 10px;
		color: var(--color-muted);
		font-size: 0.5rem;
	}
	.mp-status b {
		font-size: 0.36rem;
		letter-spacing: 0.12em;
	}
	.mp-search {
		display: flex;
		align-items: center;
		gap: 7px;
		padding: 8px 11px;
		background: var(--color-primary-soft);
		border-radius: 10px;
		color: var(--color-muted);
		font-size: 0.55rem;
	}
	.mp-tags {
		display: flex;
		gap: 5px;
		margin: 10px 0 11px;
		overflow: hidden;
	}
	.mp-tags span {
		flex: none;
		padding: 4px 9px;
		background: var(--color-primary-soft);
		border-radius: 999px;
		color: var(--color-muted);
		font-size: 0.47rem;
	}
	.mp-tags span.on {
		background: #191714;
		color: white;
	}
	.mp-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 9px;
	}
	.mp-grid article {
		display: grid;
		gap: 1px;
	}
	.thumb {
		position: relative;
		display: block;
		height: 74px;
		border-radius: 12px;
	}
	.thumb.a {
		background: linear-gradient(150deg, #d9e6f2, #a9c2dd);
	}
	.thumb.b {
		background: linear-gradient(150deg, #eee1cb, #c5d8cb);
	}
	.thumb.c {
		background: linear-gradient(150deg, #f7dba8, #dfb46f);
	}
	.thumb.d {
		background: linear-gradient(150deg, #e7dcf0, #c0aed6);
	}
	.thumb :global(.mp-heart) {
		position: absolute;
		top: 6px;
		right: 6px;
		color: rgb(255 255 255 / 88%);
	}
	.thumb :global(.mp-heart.on) {
		color: #e8536f;
		fill: #e8536f;
	}
	.mp-grid b {
		margin-top: 5px;
		font-size: 0.52rem;
	}
	.mp-grid small {
		color: var(--color-primary-strong);
		font-size: 0.5rem;
		font-weight: 750;
	}
	.mp-grid em {
		display: flex;
		align-items: center;
		gap: 3px;
		color: var(--color-muted);
		font-size: 0.44rem;
		font-style: normal;
	}

	/* ---------- Keunggulan ---------- */
	.mp-points {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
		gap: 14px;
		margin-top: 18px;
	}
	.mp-points article {
		padding: 20px;
		border-radius: 18px;
	}
	.mp-points span {
		display: grid;
		width: 40px;
		height: 40px;
		place-items: center;
		background: var(--color-primary-soft);
		border-radius: 12px;
		color: var(--color-primary-strong);
	}
	.mp-points strong {
		display: block;
		margin: 13px 0 6px;
		font-size: 0.95rem;
		letter-spacing: -0.01em;
	}
	.mp-points p {
		margin: 0;
		color: var(--color-muted);
		font-size: 0.82rem;
		line-height: 1.55;
	}

	/* ---------- Ajakan buka toko ---------- */
	.mp-seller {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 22px;
		margin-top: 14px;
		padding: 24px clamp(20px, 4vw, 34px);
		border-radius: 20px;
	}
	.mp-seller h3 {
		margin: 0 0 7px;
		font-size: 1.12rem;
		letter-spacing: -0.02em;
	}
	.mp-seller p {
		max-width: 44rem;
		margin: 0;
		color: var(--color-muted);
		font-size: 0.85rem;
		line-height: 1.55;
	}
	.mp-seller a {
		display: inline-flex;
		height: 44px;
		flex: none;
		align-items: center;
		gap: 7px;
		padding: 0 17px;
		background: var(--color-secondary, var(--color-primary));
		border-radius: 12px;
		color: white;
		font-size: 0.84rem;
		font-weight: 720;
	}

	@media (max-width: 900px) {
		.mp-hero {
			grid-template-columns: 1fr;
		}
		.mp-preview {
			order: -1;
		}
		.mp-phone {
			transform: none;
		}
	}
	@media (max-width: 767px) {
		.mp-hero,
		.mp-seller {
			margin-inline: 16px;
		}
		.mp-points {
			margin-inline: 16px;
		}
		.mp-seller {
			flex-direction: column;
			align-items: flex-start;
		}
		.mp-cta a {
			flex: 1;
			justify-content: center;
		}
	}
</style>
