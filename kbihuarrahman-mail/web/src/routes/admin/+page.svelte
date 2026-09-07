<script lang="ts">
	import { enhance } from '$app/forms';
	import {
		ArrowLeft,
		UserPlus,
		ShieldCheck,
		Shield,
		KeyRound,
		Trash2,
		Mail,
		Users,
		X,
		Check
	} from '@lucide/svelte';
	let { data, form }: { data: any; form: any } = $props();

	let showCreate = $state(false);
	let resetFor = $state<number | null>(null);
	let newPw = $state('');
	let cLocal = $state('');
	let cName = $state('');
	let cPw = $state('');
	let cAdmin = $state(false);

	const adminCount = $derived(data.users.filter((u: any) => u.is_admin).length);
	const totalMsg = $derived(data.users.reduce((n: number, u: any) => n + (u.msg_count || 0), 0));

	function initial(s: string) {
		return (s || 'U').trim().charAt(0).toUpperCase();
	}
	function fmtDate(s: string) {
		try {
			return new Date(s.replace(' ', 'T') + 'Z').toLocaleDateString('id-ID', {
				day: 'numeric',
				month: 'short',
				year: 'numeric'
			});
		} catch {
			return s;
		}
	}
	$effect(() => {
		if (form?.created) {
			showCreate = false;
			cLocal = cName = cPw = '';
			cAdmin = false;
		}
		if (form?.reset) {
			resetFor = null;
			newPw = '';
		}
	});
</script>

<svelte:head><title>Panel Admin — KBIHU Ar-Rahman Mail</title></svelte:head>

<div class="wrap">
	<header class="hd">
		<a class="back" href="/"><ArrowLeft size={18} /> Kembali ke email</a>
		<h1>Panel Admin</h1>
		<p class="sub">Kelola mailbox dan akses untuk domain <b>@{data.domain}</b>.</p>
	</header>

	<div class="stats">
		<div class="stat"><span class="ico"><Users size={18} /></span><div><b>{data.users.length}</b><small>Mailbox</small></div></div>
		<div class="stat"><span class="ico green"><ShieldCheck size={18} /></span><div><b>{adminCount}</b><small>Admin</small></div></div>
		<div class="stat"><span class="ico blue"><Mail size={18} /></span><div><b>{totalMsg}</b><small>Total pesan</small></div></div>
	</div>

	{#if form?.error}<div class="alert err">{form.error}</div>{/if}
	{#if form?.created}<div class="alert ok"><Check size={16} /> Mailbox {form.created} berhasil dibuat.</div>{/if}
	{#if form?.removed}<div class="alert ok"><Check size={16} /> Mailbox dihapus.</div>{/if}
	{#if form?.reset}<div class="alert ok"><Check size={16} /> Kata sandi diperbarui.</div>{/if}

	<div class="bar">
		<h2>Daftar Mailbox</h2>
		<button class="btn primary" onclick={() => (showCreate = !showCreate)}>
			<UserPlus size={16} /> Tambah mailbox
		</button>
	</div>

	{#if showCreate}
		<form
			class="create"
			method="POST"
			action="?/create"
			use:enhance={() => async ({ update }) => await update()}
		>
			<div class="grid">
				<label>Nama lengkap<input name="full_name" bind:value={cName} placeholder="Panitia Pendaftaran" required /></label>
				<label>Alamat email
					<span class="addr"><input name="username" bind:value={cLocal} placeholder="pendaftaran" autocomplete="off" spellcheck="false" required /><span class="suf">@{data.domain}</span></span>
				</label>
				<label class="full">Kata sandi (min. 8 karakter)<input name="password" type="text" bind:value={cPw} minlength="8" placeholder="Ketik kata sandi untuk mailbox ini" required /></label>
			</div>
			<label class="chk"><input type="checkbox" name="make_admin" value="1" bind:checked={cAdmin} /> Jadikan admin (bisa akses panel ini)</label>
			<div class="actions">
				<button type="button" class="btn ghost" onclick={() => (showCreate = false)}>Batal</button>
				<button class="btn primary" type="submit"><Check size={16} /> Buat mailbox</button>
			</div>
		</form>
	{/if}

	<div class="list">
		{#each data.users as u (u.id)}
			<div class="row">
				<span class="av" class:admin={u.is_admin}>{initial(u.full_name || u.local_part)}</span>
				<div class="info">
					<div class="name">
						{u.full_name || u.local_part}
						{#if u.is_admin}<span class="badge"><ShieldCheck size={12} /> Admin</span>{/if}
						{#if u.id === data.meId}<span class="badge you">Kamu</span>{/if}
					</div>
					<div class="mail">{u.email}</div>
					<div class="meta">{u.msg_count} pesan · dibuat {fmtDate(u.created_at)}</div>
				</div>

				<div class="ops">
					<button class="op" title="Reset kata sandi" onclick={() => { resetFor = resetFor === u.id ? null : u.id; newPw = ''; }}>
						<KeyRound size={16} />
					</button>
					{#if u.id !== data.meId}
						<form method="POST" action="?/toggleAdmin" use:enhance={() => async ({ update }) => await update()}>
							<input type="hidden" name="id" value={u.id} />
							<input type="hidden" name="to" value={u.is_admin ? '0' : '1'} />
							<button class="op" title={u.is_admin ? 'Cabut admin' : 'Jadikan admin'}>
								{#if u.is_admin}<Shield size={16} />{:else}<ShieldCheck size={16} />{/if}
							</button>
						</form>
						<form method="POST" action="?/remove" use:enhance={() => async ({ update }) => await update()} onsubmit={(e) => { if (!confirm(`Hapus mailbox ${u.email}? Semua emailnya ikut terhapus permanen.`)) e.preventDefault(); }}>
							<input type="hidden" name="id" value={u.id} />
							<button class="op danger" title="Hapus mailbox"><Trash2 size={16} /></button>
						</form>
					{/if}
				</div>

				{#if resetFor === u.id}
					<form class="reset" method="POST" action="?/resetpw" use:enhance={() => async ({ update }) => await update()}>
						<input type="hidden" name="id" value={u.id} />
						<input name="password" type="text" bind:value={newPw} minlength="8" placeholder="Kata sandi baru (min. 8)" required />
						<button class="btn primary sm" type="submit">Simpan</button>
						<button class="btn ghost sm" type="button" onclick={() => (resetFor = null)}><X size={14} /></button>
					</form>
				{/if}
			</div>
		{/each}
	</div>
</div>

<style>
	.wrap {
		max-width: 860px;
		margin: 0 auto;
		padding: 22px 16px 60px;
	}
	.back {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		color: #5f6368;
		text-decoration: none;
		font-size: 0.86rem;
		font-weight: 600;
	}
	.hd h1 {
		margin: 12px 0 4px;
		font-size: 1.5rem;
	}
	.sub {
		margin: 0;
		color: #5f6368;
		font-size: 0.9rem;
	}
	.stats {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 12px;
		margin: 20px 0;
	}
	.stat {
		display: flex;
		align-items: center;
		gap: 12px;
		background: #fff;
		border: 1px solid #eae4da;
		border-radius: 14px;
		padding: 14px 16px;
	}
	.stat .ico {
		display: grid;
		place-items: center;
		width: 38px;
		height: 38px;
		border-radius: 10px;
		background: #f3ede3;
		color: #6b6459;
	}
	.stat .ico.green { background: #e6f6ec; color: #159c3a; }
	.stat .ico.blue { background: #fdece0; color: #e86a17; }
	.stat b { font-size: 1.2rem; display: block; }
	.stat small { color: #8a847b; font-size: 0.78rem; }
	.alert {
		display: flex;
		align-items: center;
		gap: 8px;
		border-radius: 11px;
		padding: 11px 14px;
		font-size: 0.88rem;
		margin-bottom: 14px;
	}
	.alert.err { background: #fdeceb; color: #c0322b; }
	.alert.ok { background: #e7f6ec; color: #14833a; }
	.bar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin: 6px 0 14px;
	}
	.bar h2 { font-size: 1.05rem; margin: 0; }
	.btn {
		display: inline-flex;
		align-items: center;
		gap: 7px;
		border: 1px solid transparent;
		border-radius: 10px;
		padding: 9px 14px;
		font: inherit;
		font-weight: 650;
		font-size: 0.88rem;
		cursor: pointer;
	}
	.btn.primary { background: #159c3a; color: #fff; }
	.btn.primary:hover { background: #128231; }
	.btn.ghost { background: #fff; border-color: #ddd4c5; color: #3c4043; }
	.btn.sm { padding: 7px 11px; font-size: 0.82rem; }
	.create {
		background: #fff;
		border: 1px solid #eae4da;
		border-radius: 18px;
		padding: 24px;
		margin-bottom: 18px;
	}
	.create .grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 18px;
	}
	.create label {
		display: grid;
		gap: 8px;
		font-size: 0.9rem;
		font-weight: 650;
		color: #3c4043;
	}
	.create label.full { grid-column: 1 / -1; }
	.create input[type='text'],
	.create input:not([type]) {
		height: 52px;
		padding: 0 15px;
		border: 1px solid #ddd4c5;
		border-radius: 12px;
		background: #faf7f2;
		font: inherit;
		font-size: 1rem;
	}
	.create input:focus { outline: 2px solid #159c3a; background: #fff; }
	.addr { display: flex; border: 1px solid #ddd4c5; border-radius: 12px; overflow: hidden; background: #faf7f2; }
	.addr input { flex: 1; border: 0; background: transparent; height: 52px; padding: 0 15px; min-width: 0; font-size: 1rem; }
	.addr input:focus { outline: none; }
	.addr:focus-within { outline: 2px solid #159c3a; background: #fff; }
	.suf { display: flex; align-items: center; padding: 0 14px; background: #eef1f5; color: #5f6368; font-size: 0.95rem; white-space: nowrap; }
	.chk { display: flex; align-items: center; gap: 10px; margin-top: 16px; font-size: 0.9rem; color: #3c4043; font-weight: 600; }
	.chk input { width: 18px; height: 18px; accent-color: #159c3a; }
	.actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
	.list { display: grid; gap: 10px; }
	.row {
		display: grid;
		grid-template-columns: auto 1fr auto;
		align-items: center;
		gap: 14px;
		background: #fff;
		border: 1px solid #eae4da;
		border-radius: 14px;
		padding: 14px 16px;
	}
	.av {
		display: grid;
		place-items: center;
		width: 42px;
		height: 42px;
		border-radius: 50%;
		background: #e6f6ec;
		color: #159c3a;
		font-weight: 800;
	}
	.av.admin { background: #e6f6ec; color: #159c3a; }
	.info { min-width: 0; }
	.name { font-weight: 700; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
	.badge {
		display: inline-flex;
		align-items: center;
		gap: 3px;
		background: #e6f6ec;
		color: #159c3a;
		font-size: 0.68rem;
		font-weight: 700;
		padding: 2px 7px;
		border-radius: 999px;
	}
	.badge.you { background: #eef1f5; color: #5f6368; }
	.mail { color: #3c4043; font-size: 0.88rem; overflow: hidden; text-overflow: ellipsis; }
	.meta { color: #9a948b; font-size: 0.76rem; margin-top: 2px; }
	.ops { display: flex; align-items: center; gap: 6px; }
	.ops form { margin: 0; }
	.op {
		display: grid;
		place-items: center;
		width: 36px;
		height: 36px;
		border: 1px solid #e4ded4;
		border-radius: 9px;
		background: #fff;
		color: #5f6368;
		cursor: pointer;
	}
	.op:hover { background: #f6f2eb; color: #202124; }
	.op.danger:hover { background: #fdeceb; color: #c0322b; border-color: #f3c9c6; }
	.reset {
		grid-column: 1 / -1;
		display: flex;
		gap: 8px;
		margin-top: 4px;
		padding-top: 12px;
		border-top: 1px dashed #e4ded4;
	}
	.reset input {
		flex: 1;
		height: 40px;
		padding: 0 12px;
		border: 1px solid #ddd4c5;
		border-radius: 9px;
		background: #faf7f2;
		font: inherit;
	}
	.reset input:focus { outline: 2px solid #159c3a; background: #fff; }
	@media (max-width: 560px) {
		.stats { grid-template-columns: 1fr; }
		.row { grid-template-columns: auto 1fr; }
		.ops { grid-column: 1 / -1; justify-content: flex-end; }
		.create .grid { grid-template-columns: 1fr; }
	}

	/* ── dark mode ── */
	:global(html.psdark) .back { color: #9aa4b2; }
	:global(html.psdark) .hd h1,
	:global(html.psdark) .bar h2 { color: #e8ecf2; }
	:global(html.psdark) .sub,
	:global(html.psdark) .meta,
	:global(html.psdark) .stat small { color: #9aa4b2; }
	:global(html.psdark) .stat,
	:global(html.psdark) .create,
	:global(html.psdark) .row { background: #12161c; border-color: #252b34; }
	:global(html.psdark) .stat .ico { background: #1b2029; color: #c3ccd8; }
	:global(html.psdark) .stat .ico.green { background: #163a26; color: #6fe39a; }
	:global(html.psdark) .stat .ico.blue { background: #3a2616; color: #f6a765; }
	:global(html.psdark) .stat b,
	:global(html.psdark) .name,
	:global(html.psdark) .mail { color: #e8ecf2; }
	:global(html.psdark) .create label { color: #c3ccd8; }
	:global(html.psdark) .create input,
	:global(html.psdark) .reset input { background: #0f1319; border-color: #2c333d; color: #e8ecf2; }
	:global(html.psdark) .addr { background: #0f1319; border-color: #2c333d; }
	:global(html.psdark) .addr input { color: #e8ecf2; }
	:global(html.psdark) .suf { background: #1b2029; color: #9aa4b2; }
	:global(html.psdark) .chk { color: #c3ccd8; }
	:global(html.psdark) .op { background: #12161c; border-color: #2c333d; color: #9aa4b2; }
	:global(html.psdark) .op:hover { background: #1b2029; color: #e8ecf2; }
	:global(html.psdark) .btn.ghost { background: #12161c; border-color: #2c333d; color: #c3ccd8; }
	:global(html.psdark) .badge.you { background: #1b2029; color: #9aa4b2; }
	:global(html.psdark) .reset { border-top-color: #2c333d; }
</style>
