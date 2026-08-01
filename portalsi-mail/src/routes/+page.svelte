<script lang="ts">
	import { Inbox } from '@lucide/svelte';
	let { data } = $props();
</script>

<div class="card">
	<h1>Kotak Masuk</h1>
	<p class="sub">Email aktif kamu:</p>
	<div class="emailbox"><Inbox size={18} /> <b>{data.account?.email}</b></div>
	<p class="muted" style="margin-top:16px">
		Antarmuka baca &amp; tulis email (webmail) sedang disiapkan dan akan muncul di sini.
	</p>
</div>

<style>
	.emailbox {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 10px 14px;
		border-radius: 12px;
		background: rgba(31, 111, 235, 0.08);
		color: #1554c0;
		font-size: 1rem;
	}
</style>
