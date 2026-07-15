'use client';

import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { Loader2, ArrowRight, Eye, EyeOff, Video, Shield, Zap, Lock, LogOut, UserCircle } from 'lucide-react';
import { normalizeRoomId, isValidRoomId } from '@/lib/room-id';

interface PortalUser {
  user_id: number;
  username: string;
  full_name?: string | null;
  email?: string | null;
  profile_picture_url?: string | null;
}

function userDisplayName(user: PortalUser | null): string {
  return (user?.full_name || user?.username || user?.email || '').slice(0, 40);
}

function responseError(data: any, fallback: string): string {
  if (typeof data?.error === 'string') return data.error;
  if (typeof data?.message === 'string') return data.message;
  if (data?.errors && typeof data.errors === 'object') {
    const first = Object.values(data.errors).flat().find((value) => typeof value === 'string');
    if (typeof first === 'string') return first;
  }
  return fallback;
}

export function HomeHero() {
  const router = useRouter();
  const [mode, setMode] = useState<'create' | 'join'>('create');
  const [authUser, setAuthUser] = useState<PortalUser | null>(null);
  const [authLoading, setAuthLoading] = useState(true);
  const [authMode, setAuthMode] = useState<'login' | 'register'>('login');
  const [authBusy, setAuthBusy] = useState(false);
  const [authError, setAuthError] = useState<string | null>(null);
  const [loginValue, setLoginValue] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [registerUsername, setRegisterUsername] = useState('');
  const [registerFullName, setRegisterFullName] = useState('');
  const [registerEmail, setRegisterEmail] = useState('');
  const [registerPassword, setRegisterPassword] = useState('');
  const [verificationSentTo, setVerificationSentTo] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [roomId, setRoomId] = useState('');
  const [password, setPassword] = useState('');
  const [usePassword, setUsePassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showPw, setShowPw] = useState(false);
  const [showLoginPw, setShowLoginPw] = useState(false);
  const [showRegisterPw, setShowRegisterPw] = useState(false);

  const [createMode, setCreateMode] = useState<'instant' | 'later' | 'schedule'>('instant');
  const [scheduledDate, setScheduledDate] = useState('');
  const [scheduledTime, setScheduledTime] = useState('');
  const [createdRoomInfo, setCreatedRoomInfo] = useState<{ id: string; password?: string; url: string; scheduledFor?: number } | null>(null);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    let alive = true;
    fetch('/api/auth/me')
      .then(async (res) => {
        if (!res.ok) return null;
        const data = await res.json();
        return data.user as PortalUser;
      })
      .then((user) => {
        if (!alive || !user) return;
        setAuthUser(user);
        setName((current) => current || userDisplayName(user));
      })
      .catch(() => {})
      .finally(() => {
        if (alive) setAuthLoading(false);
      });

    return () => {
      alive = false;
    };
  }, []);

  async function handleAuthLogin(e: React.FormEvent) {
    e.preventDefault();
    setAuthError(null);

    const login = loginValue.trim();
    if (!login || !loginPassword) {
      setAuthError('Email/username dan password Portal SI wajib diisi.');
      return;
    }

    setAuthBusy(true);
    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ login, password: loginPassword }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(responseError(data, 'Login Portal SI gagal.'));

      const user = data.user as PortalUser;
      setAuthUser(user);
      setName((current) => current || userDisplayName(user));
      setLoginPassword('');
    } catch (err: any) {
      setAuthError(err?.message || 'Login Portal SI gagal.');
    } finally {
      setAuthBusy(false);
    }
  }

  async function handleAuthRegister(e: React.FormEvent) {
    e.preventDefault();
    setAuthError(null);

    const username = registerUsername.trim().toLowerCase();
    const fullName = registerFullName.trim();
    const email = registerEmail.trim().toLowerCase();

    if (!/^[a-z0-9._]+$/i.test(username)) {
      setAuthError('Username hanya boleh berisi huruf, angka, titik, dan underscore.');
      return;
    }
    if (!fullName || !email || !registerPassword) {
      setAuthError('Username, nama lengkap, email, dan password wajib diisi.');
      return;
    }
    if (registerPassword.length < 6) {
      setAuthError('Password minimal 6 karakter.');
      return;
    }

    setAuthBusy(true);
    try {
      const res = await fetch('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, fullName, email, password: registerPassword }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(responseError(data, 'Pendaftaran akun Portal SI gagal.'));

      // Tidak auto-login. Tampilkan layar "email verifikasi terkirim";
      // user harus verifikasi email dulu sebelum bisa login.
      setVerificationSentTo(email);
      setRegisterUsername('');
      setRegisterFullName('');
      setRegisterEmail('');
      setRegisterPassword('');
    } catch (err: any) {
      setAuthError(err?.message || 'Pendaftaran akun Portal SI gagal.');
    } finally {
      setAuthBusy(false);
    }
  }

  async function handleLogout() {
    setAuthBusy(true);
    try {
      await fetch('/api/auth/logout', { method: 'POST' });
    } finally {
      setAuthUser(null);
      setName('');
      setCreatedRoomInfo(null);
      setAuthBusy(false);
    }
  }

  const handleCopy = () => {
    if (createdRoomInfo) {
      navigator.clipboard.writeText(createdRoomInfo.url);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    setError(null);

    if (!authUser) {
      setError('Login akun Portal SI dulu untuk membuat meeting.');
      return;
    }

    const hostName = name.trim() || userDisplayName(authUser);
    if (!hostName) {
      setError('Nama host tidak boleh kosong.');
      return;
    }

    let scheduledFor: number | undefined;
    if (createMode === 'schedule') {
      if (!scheduledDate || !scheduledTime) {
        setError('Pilih tanggal dan waktu rapat.');
        return;
      }
      scheduledFor = new Date(`${scheduledDate}T${scheduledTime}`).getTime();
      if (scheduledFor < Date.now()) {
        setError('Waktu rapat tidak boleh di masa lalu.');
        return;
      }
    }

    setLoading(true);
    try {
      const roomPassword = usePassword && password ? password : undefined;
      const res = await fetch('/api/rooms', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ hostName, password: roomPassword, lobby: false, scheduledFor, mode: createMode }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Gagal membuat ruang.');

      sessionStorage.setItem(`lk-${data.roomId}`, JSON.stringify({
        token: data.token,
        wsUrl: data.wsUrl,
        name: hostName,
        isHost: true,
        password: roomPassword,
      }));

      if (createMode === 'instant') {
        router.push(`/room/${data.roomId}`);
      } else {
        setLoading(false);
        setCreatedRoomInfo({
          id: data.roomId,
          password: roomPassword,
          url: window.location.origin + `/room/${data.roomId}`,
          scheduledFor,
        });
      }
    } catch (err: any) {
      setError(err.message);
      setLoading(false);
    }
  }

  async function handleJoin(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    const id = normalizeRoomId(roomId);
    if (!isValidRoomId(id)) {
      setError('Room ID tidak valid.');
      return;
    }
    if (!name.trim()) {
      setError('Nama tidak boleh kosong.');
      return;
    }
    sessionStorage.setItem(`lk-join-${id}`, JSON.stringify({ name: name.trim() }));
    router.push(`/room/${id}`);
  }

  return (
    <div className="w-full max-w-6xl mx-auto px-6 py-12 sm:py-20">
      <div className="grid lg:grid-cols-2 gap-16 lg:gap-20 items-center">
        <div>
          <h1 className="text-4xl sm:text-5xl font-bold text-gray-900 leading-[1.15] tracking-tight">
            Buat video meeting<br />
            <span className="hp-gradient-text">tercepat dan tersimpel yang pernah ada.</span>
          </h1>
          <p className="mt-5 text-lg text-gray-500 leading-relaxed max-w-md">
            Host membuat meeting dengan akun Portal SI. Peserta tetap bisa gabung cepat cukup pakai nama dan kode meeting.
          </p>

          <div className="mt-8 flex flex-wrap gap-3">
            <Pill icon={<Zap className="h-3.5 w-3.5" />} text="Instan" />
            <Pill icon={<Shield className="h-3.5 w-3.5" />} text="Terenkripsi" />
            <Pill icon={<UserCircle className="h-3.5 w-3.5" />} text="Akun Portal SI" />
            <Pill icon={<Video className="h-3.5 w-3.5" />} text="HD Video" />
          </div>
        </div>

        <div>
          <div className="hp-card">
            <div className="flex border-b border-gray-100 mb-6">
              <button type="button" onClick={() => { setMode('create'); setError(null); }}
                className={`flex-1 pb-3 text-sm font-semibold border-b-2 transition-colors ${mode === 'create' ? 'border-dove-green text-dove-green' : 'border-transparent text-gray-400 hover:text-gray-600'}`}>
                Buat Meeting
              </button>
              <button type="button" onClick={() => { setMode('join'); setError(null); }}
                className={`flex-1 pb-3 text-sm font-semibold border-b-2 transition-colors ${mode === 'join' ? 'border-dove-orange text-dove-orange' : 'border-transparent text-gray-400 hover:text-gray-600'}`}>
                Gabung Meeting
              </button>
            </div>

            {mode === 'create' ? (
              createdRoomInfo ? (
                <div className="space-y-4 animate-scale-in">
                  <div className="text-center mb-4">
                    <div className="mx-auto w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-3">
                      <Shield className="h-6 w-6 text-green-600" />
                    </div>
                    <h3 className="text-lg font-bold text-gray-900">Rapat Berhasil Dibuat</h3>
                    {createdRoomInfo.scheduledFor && (
                      <p className="text-sm text-gray-500 mt-1">
                        Dijadwalkan untuk: {new Date(createdRoomInfo.scheduledFor).toLocaleDateString('id-ID', {
                          weekday: 'long',
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </p>
                    )}
                  </div>

                  <div>
                    <label className="hp-label">Link Rapat (Bagikan ini)</label>
                    <div className="flex items-center mt-1">
                      <input type="text" readOnly value={createdRoomInfo.url} className="hp-input w-full flex-1 rounded-r-none border-r-0 text-sm font-mono text-gray-600" />
                      <button type="button" onClick={handleCopy} className="hp-btn hp-btn-green !w-auto !mt-0 rounded-l-none px-4 shrink-0 shadow-none h-[42px] sm:h-[46px] border border-dove-green min-w-[80px]">
                        {copied ? 'Disalin!' : 'Copy'}
                      </button>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-3 mt-4">
                    <div className="bg-gray-50 p-3 rounded-xl border border-gray-100">
                      <p className="text-[10px] text-gray-400 font-semibold uppercase">Room ID</p>
                      <p className="text-sm font-bold text-gray-800 font-mono mt-0.5">{createdRoomInfo.id}</p>
                    </div>
                    {createdRoomInfo.password && (
                      <div className="bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <p className="text-[10px] text-gray-400 font-semibold uppercase">Password</p>
                        <p className="text-sm font-bold text-gray-800 font-mono mt-0.5">{createdRoomInfo.password}</p>
                      </div>
                    )}
                  </div>

                  <div className="pt-4 flex gap-3">
                    <button type="button" onClick={() => setCreatedRoomInfo(null)} className="flex-1 py-3 text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">Buat Lainnya</button>
                    {!createdRoomInfo.scheduledFor && (
                      <button type="button" onClick={() => router.push(`/room/${createdRoomInfo.id}`)} className="flex-1 py-3 text-sm font-semibold text-white bg-dove-green hover:bg-dove-green/90 rounded-xl shadow-[0_4px_14px_rgba(16,185,129,0.3)] transition-all">Mulai Sekarang</button>
                    )}
                  </div>
                </div>
              ) : authLoading ? (
                <div className="py-12 flex flex-col items-center justify-center gap-3 text-sm text-gray-500">
                  <Loader2 className="h-5 w-5 animate-spin text-dove-green" />
                  Mengecek sesi Portal SI...
                </div>
              ) : !authUser ? (
                <AuthPanel
                  mode={authMode}
                  setMode={(next) => { setAuthMode(next); setAuthError(null); }}
                  verificationSentTo={verificationSentTo}
                  onBackToLogin={() => { setVerificationSentTo(null); setAuthMode('login'); setAuthError(null); }}
                  busy={authBusy}
                  error={authError}
                  loginValue={loginValue}
                  setLoginValue={setLoginValue}
                  loginPassword={loginPassword}
                  setLoginPassword={setLoginPassword}
                  showLoginPw={showLoginPw}
                  setShowLoginPw={setShowLoginPw}
                  registerUsername={registerUsername}
                  setRegisterUsername={setRegisterUsername}
                  registerFullName={registerFullName}
                  setRegisterFullName={setRegisterFullName}
                  registerEmail={registerEmail}
                  setRegisterEmail={setRegisterEmail}
                  registerPassword={registerPassword}
                  setRegisterPassword={setRegisterPassword}
                  showRegisterPw={showRegisterPw}
                  setShowRegisterPw={setShowRegisterPw}
                  onLogin={handleAuthLogin}
                  onRegister={handleAuthRegister}
                />
              ) : (
                <form onSubmit={handleCreate} className="space-y-4" autoComplete="off">
                  <div className="flex items-start justify-between gap-3 rounded-xl border border-green-100 bg-green-50 px-4 py-3">
                    <div className="min-w-0 flex items-center gap-3">
                      {authUser.profile_picture_url ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={authUser.profile_picture_url} alt="" className="h-9 w-9 shrink-0 rounded-full object-cover border border-green-200" />
                      ) : (
                        <UserCircle className="h-5 w-5 shrink-0 text-dove-green" />
                      )}
                      <div className="min-w-0">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-green-700">Akun Portal SI</p>
                        <p className="truncate text-sm font-semibold text-gray-800">{userDisplayName(authUser)}</p>
                      </div>
                    </div>
                    <button
                      type="button"
                      onClick={handleLogout}
                      disabled={authBusy}
                      aria-label="Keluar dari akun Portal SI"
                      className="rounded-lg p-2 text-green-700 hover:bg-white disabled:opacity-60"
                    >
                      {authBusy ? <Loader2 className="h-4 w-4 animate-spin" /> : <LogOut className="h-4 w-4" />}
                    </button>
                  </div>

                  <div className="flex bg-gray-50 p-1 rounded-xl mb-4 border border-gray-100">
                    <button type="button" onClick={() => setCreateMode('instant')} className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${createMode === 'instant' ? 'bg-white shadow border border-gray-200 text-gray-800' : 'text-gray-500 hover:text-gray-700'}`}>Instan</button>
                    <button type="button" onClick={() => setCreateMode('later')} className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${createMode === 'later' ? 'bg-white shadow border border-gray-200 text-gray-800' : 'text-gray-500 hover:text-gray-700'}`}>Buat Nanti</button>
                    <button type="button" onClick={() => setCreateMode('schedule')} className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${createMode === 'schedule' ? 'bg-white shadow border border-gray-200 text-gray-800' : 'text-gray-500 hover:text-gray-700'}`}>Jadwalkan</button>
                  </div>

                  <div>
                    <label className="hp-label">Nama Host</label>
                    <div className="hp-input flex items-center gap-2 bg-gray-50 text-sm text-gray-700 cursor-not-allowed" title="Nama mengikuti akun Portal SI yang login">
                      <UserCircle className="h-4 w-4 shrink-0 text-gray-400" />
                      <span className="truncate">{userDisplayName(authUser)}</span>
                    </div>
                    <p className="mt-1 text-[11px] text-gray-400">Nama mengikuti akun Portal SI yang login.</p>
                  </div>

                  {createMode === 'schedule' && (
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="hp-label">Tanggal</label>
                        <input type="date" value={scheduledDate} onChange={e => setScheduledDate(e.target.value)} className="hp-input text-sm" required={createMode === 'schedule'} />
                      </div>
                      <div>
                        <label className="hp-label">Waktu</label>
                        <input type="time" value={scheduledTime} onChange={e => setScheduledTime(e.target.value)} className="hp-input text-sm" required={createMode === 'schedule'} />
                      </div>
                    </div>
                  )}

                  <label className="flex items-center gap-3 cursor-pointer select-none py-2">
                    <input type="checkbox" checked={usePassword} onChange={e => setUsePassword(e.target.checked)}
                      className="h-4 w-4 rounded border-gray-300 text-dove-green focus:ring-dove-green/30 cursor-pointer" />
                    <span className="text-sm text-gray-600">Buat password room</span>
                  </label>

                  {usePassword && (
                    <PwField value={password} onChange={setPassword} showPw={showPw} toggle={() => setShowPw(v => !v)} placeholder="Buat password..." ignorePasswordManager />
                  )}

                  {error && <ErrBox text={error} />}

                  <button type="submit" disabled={loading} className="hp-btn hp-btn-green mt-2">
                    {loading ? <><Loader2 className="h-4 w-4 animate-spin" /> {createMode === 'instant' ? 'Memulai...' : 'Membuat...'}</> : <>
                      {createMode === 'instant' ? 'Mulai Rapat Instan' : (createMode === 'later' ? 'Dapatkan Info Rapat' : 'Jadwalkan Rapat')}
                      <ArrowRight className="h-4 w-4" />
                    </>}
                  </button>
                </form>
              )
            ) : (
              <form onSubmit={handleJoin} className="space-y-4" autoComplete="off">
                <Field label="Nama Anda" value={name} onChange={setName} placeholder="Masukkan nama" maxLength={40} autoFocus />

                <div>
                  <label className="hp-label">Kode Meeting</label>
                  <input type="text" value={roomId} onChange={e => setRoomId(e.target.value.replace(/[^a-zA-Z]/g, '').toUpperCase())}
                    placeholder="Contoh: ABCDEF" maxLength={6} autoComplete="off"
                    className="hp-input text-center uppercase tracking-[0.3em] font-semibold text-lg" />
                </div>

                {error && <ErrBox text={error} />}

                <button type="submit" className="hp-btn hp-btn-orange">
                  <ArrowRight className="h-4 w-4" /> Gabung Sekarang
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function AuthPanel({
  mode,
  setMode,
  verificationSentTo,
  onBackToLogin,
  busy,
  error,
  loginValue,
  setLoginValue,
  loginPassword,
  setLoginPassword,
  showLoginPw,
  setShowLoginPw,
  registerUsername,
  setRegisterUsername,
  registerFullName,
  setRegisterFullName,
  registerEmail,
  setRegisterEmail,
  registerPassword,
  setRegisterPassword,
  showRegisterPw,
  setShowRegisterPw,
  onLogin,
  onRegister,
}: {
  mode: 'login' | 'register';
  setMode: (mode: 'login' | 'register') => void;
  verificationSentTo: string | null;
  onBackToLogin: () => void;
  busy: boolean;
  error: string | null;
  loginValue: string;
  setLoginValue: (value: string) => void;
  loginPassword: string;
  setLoginPassword: (value: string) => void;
  showLoginPw: boolean;
  setShowLoginPw: (value: boolean | ((current: boolean) => boolean)) => void;
  registerUsername: string;
  setRegisterUsername: (value: string) => void;
  registerFullName: string;
  setRegisterFullName: (value: string) => void;
  registerEmail: string;
  setRegisterEmail: (value: string) => void;
  registerPassword: string;
  setRegisterPassword: (value: string) => void;
  showRegisterPw: boolean;
  setShowRegisterPw: (value: boolean | ((current: boolean) => boolean)) => void;
  onLogin: (e: React.FormEvent) => void;
  onRegister: (e: React.FormEvent) => void;
}) {
  if (verificationSentTo) {
    return (
      <div className="space-y-4 animate-scale-in text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
          <UserCircle className="h-7 w-7 text-green-700" />
        </div>
        <div>
          <p className="text-base font-semibold text-gray-800">Cek email kamu</p>
          <p className="mt-1 text-sm leading-relaxed text-gray-600">
            Link verifikasi sudah dikirim ke <span className="font-semibold text-gray-800">{verificationSentTo}</span>.
            Verifikasi email dulu, lalu masuk untuk mulai membuat meeting.
          </p>
        </div>
        <button type="button" onClick={onBackToLogin} className="hp-btn hp-btn-green">
          <UserCircle className="h-4 w-4" /> Kembali ke Masuk
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-4 animate-scale-in">
      <div className="rounded-xl border border-green-100 bg-green-50 px-4 py-3">
        <p className="text-sm font-semibold text-green-900">Login pakai akun Portal SI</p>
        <p className="mt-1 text-xs leading-relaxed text-green-700">
          Akun Portal SI wajib untuk membuat meeting. Peserta tetap bisa gabung dari tab sebelah cukup dengan nama dan kode meeting.
        </p>
      </div>

      <div className="flex bg-gray-50 p-1 rounded-xl border border-gray-100">
        <button type="button" onClick={() => setMode('login')} className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${mode === 'login' ? 'bg-white shadow border border-gray-200 text-gray-800' : 'text-gray-500 hover:text-gray-700'}`}>Masuk</button>
        <button type="button" onClick={() => setMode('register')} className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${mode === 'register' ? 'bg-white shadow border border-gray-200 text-gray-800' : 'text-gray-500 hover:text-gray-700'}`}>Daftar</button>
      </div>

      {mode === 'login' ? (
        <form onSubmit={onLogin} className="space-y-4" autoComplete="on">
          <Field label="Email atau Username Portal SI" value={loginValue} onChange={setLoginValue} placeholder="username atau email" maxLength={120} autoFocus autoComplete="username" />
          <PwField label="Password Portal SI" value={loginPassword} onChange={setLoginPassword} showPw={showLoginPw} toggle={() => setShowLoginPw(v => !v)} placeholder="Masukkan password..." autoComplete="current-password" />
          {error && <ErrBox text={error} />}
          <button type="submit" disabled={busy} className="hp-btn hp-btn-green mt-2">
            {busy ? <><Loader2 className="h-4 w-4 animate-spin" /> Masuk...</> : <><UserCircle className="h-4 w-4" /> Masuk dan Buat Meeting</>}
          </button>
        </form>
      ) : (
        <form onSubmit={onRegister} className="space-y-4" autoComplete="on">
          <Field label="Username Portal SI" value={registerUsername} onChange={setRegisterUsername} placeholder="contoh: ahmad.santri" maxLength={40} autoFocus autoComplete="username" />
          <Field label="Nama Lengkap" value={registerFullName} onChange={setRegisterFullName} placeholder="Masukkan nama lengkap" maxLength={120} autoComplete="name" />
          <Field label="Email" value={registerEmail} onChange={setRegisterEmail} placeholder="nama@email.com" maxLength={160} type="email" autoComplete="email" />
          <PwField label="Password" value={registerPassword} onChange={setRegisterPassword} showPw={showRegisterPw} toggle={() => setShowRegisterPw(v => !v)} placeholder="Minimal 6 karakter" autoComplete="new-password" />
          {error && <ErrBox text={error} />}
          <button type="submit" disabled={busy} className="hp-btn hp-btn-green mt-2">
            {busy ? <><Loader2 className="h-4 w-4 animate-spin" /> Mendaftar...</> : <><UserCircle className="h-4 w-4" /> Buat Akun Portal SI</>}
          </button>
        </form>
      )}
    </div>
  );
}

function Field({ label, value, onChange, placeholder, maxLength, autoFocus, type = 'text', autoComplete = 'off' }: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  placeholder: string;
  maxLength?: number;
  autoFocus?: boolean;
  type?: string;
  autoComplete?: string;
}) {
  return (
    <div>
      <label className="hp-label">{label}</label>
      <input type={type} value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
        maxLength={maxLength} autoFocus={autoFocus} autoComplete={autoComplete} className="hp-input" />
    </div>
  );
}

function PwField({ label, value, onChange, showPw, toggle, placeholder, autoComplete = 'off', ignorePasswordManager = false }: {
  label?: string;
  value: string;
  onChange: (v: string) => void;
  showPw: boolean;
  toggle: () => void;
  placeholder: string;
  autoComplete?: string;
  ignorePasswordManager?: boolean;
}) {
  return (
    <div>
      {label && <label className="hp-label">{label}</label>}
      <div className="relative">
        <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-300" />
        <input
          type={showPw ? 'text' : 'password'}
          value={value}
          onChange={e => onChange(e.target.value)}
          placeholder={placeholder}
          maxLength={64}
          autoComplete={autoComplete}
          data-1p-ignore={ignorePasswordManager ? '' : undefined}
          className="hp-input !pl-10 !pr-10"
        />
        <button type="button" onClick={toggle}
          className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
          {showPw ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </div>
    </div>
  );
}

function ErrBox({ text }: { text: string }) {
  return <p className="text-sm text-red-500 bg-red-50 border border-red-100 rounded-xl px-4 py-2.5">{text}</p>;
}

function Pill({ icon, text }: { icon: React.ReactNode; text: string }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-gray-50 border border-gray-100 px-3.5 py-1.5 text-xs font-medium text-gray-500">
      {icon}{text}
    </span>
  );
}
