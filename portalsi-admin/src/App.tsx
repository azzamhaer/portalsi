import { FormEvent, useEffect, useMemo, useState } from 'react';
import type { ElementType, ReactNode } from 'react';
import {
  Activity,
  Ban,
  ChevronsLeft,
  ChevronsRight,
  ClipboardList,
  Eye,
  FileText,
  Lock,
  LogOut,
  Menu,
  MessageSquare,
  Pencil,
  Radio,
  RefreshCw,
  Save,
  Search,
  ShieldAlert,
  ShieldCheck,
  Trash2,
  Unlock,
  UserCheck,
  Users,
  UserX,
  Video,
  X,
} from 'lucide-react';

const PORTAL_API = (import.meta.env.VITE_PORTALSI_API_URL || 'https://api-new.portalsi.com/api').replace(/\/+$/, '');
const MEET_API = (import.meta.env.VITE_MEET_API_URL || 'https://meet.portalsi.com/api').replace(/\/+$/, '');
const STORAGE_KEY = 'portalsi-admin-session';

type Tab = 'dashboard' | 'users' | 'admins' | 'chats' | 'content' | 'meet' | 'audit';
type ChatMode = 'direct' | 'group';
type ContentMode = 'posts' | 'comments' | 'stories' | 'groups';

interface PortalUser {
  user_id: number;
  username: string;
  full_name?: string | null;
  email?: string | null;
  role?: string | null;
  bio?: string | null;
  is_verified: boolean;
  is_private?: boolean;
  is_online?: boolean;
  is_banned?: boolean;
  ban_reason?: string | null;
  admin_notes?: string | null;
  last_activity?: string | null;
  created_at?: string | null;
}

interface Session {
  token: string;
  user: PortalUser;
}

interface PageResult<T> {
  data: T[];
  current_page?: number;
  last_page?: number;
  total?: number;
}

type NavItem = { id: Tab; label: string; icon: ElementType };

const navGroups: Array<{ heading: string; items: NavItem[] }> = [
  { heading: 'Ringkasan', items: [{ id: 'dashboard', label: 'Dashboard', icon: Activity }] },
  {
    heading: 'Manajemen',
    items: [
      { id: 'users', label: 'Users', icon: Users },
      { id: 'chats', label: 'Chats', icon: MessageSquare },
      { id: 'content', label: 'Content', icon: FileText },
    ],
  },
  { heading: 'Meeting', items: [{ id: 'meet', label: 'Meet Rooms', icon: Video }] },
  {
    heading: 'Sistem',
    items: [
      { id: 'admins', label: 'Admin', icon: ShieldCheck },
      { id: 'audit', label: 'Audit', icon: ClipboardList },
    ],
  },
];

const navItems: NavItem[] = navGroups.flatMap(g => g.items);

function readStoredSession(): Session | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

async function apiRequest<T>(base: string, path: string, token?: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (init.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
  if (token) headers.set('Authorization', `Bearer ${token}`);

  const res = await fetch(`${base}${path}`, { ...init, headers, cache: 'no-store' });
  const text = await res.text();
  let data: any = {};
  try {
    data = text ? JSON.parse(text) : {};
  } catch {
    data = { message: text };
  }

  if (!res.ok) {
    throw new Error(data.message || data.error || `Request gagal (${res.status})`);
  }

  return data as T;
}

function fmt(value?: string | number | null) {
  if (!value) return '-';
  try {
    return new Intl.DateTimeFormat('id-ID', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(typeof value === 'number' ? new Date(value) : new Date(value));
  } catch {
    return String(value);
  }
}

function short(text?: string | null, length = 90) {
  if (!text) return '-';
  return text.length > length ? `${text.slice(0, length)}...` : text;
}

export function App() {
  const [session, setSession] = useState<Session | null>(() => readStoredSession());
  const [tab, setTab] = useState<Tab>('dashboard');
  const [loading, setLoading] = useState(false);
  const [navOpen, setNavOpen] = useState(false); // drawer mobile
  const [collapsed, setCollapsed] = useState(() => {
    try { return localStorage.getItem('portalsi-admin-nav-collapsed') === '1'; } catch { return false; }
  });
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  // Toast auto-dismiss: notice (sukses) 3.5s, error 6s. Reset timer tiap pesan baru.
  useEffect(() => {
    if (!notice) return;
    const t = setTimeout(() => setNotice(''), 3500);
    return () => clearTimeout(t);
  }, [notice]);
  useEffect(() => {
    if (!error) return;
    const t = setTimeout(() => setError(''), 6000);
    return () => clearTimeout(t);
  }, [error]);

  useEffect(() => {
    try { localStorage.setItem('portalsi-admin-nav-collapsed', collapsed ? '1' : '0'); } catch { /* ignore */ }
  }, [collapsed]);

  // Pilih tab lalu tutup drawer (mobile).
  const selectTab = (id: Tab) => {
    setTab(id);
    setNavOpen(false);
  };

  const [appOverview, setAppOverview] = useState<any>(null);
  const [meetOverview, setMeetOverview] = useState<any>(null);
  const [users, setUsers] = useState<PortalUser[]>([]);
  const [userSearch, setUserSearch] = useState('');
  const [editingUser, setEditingUser] = useState<PortalUser | null>(null);
  const [chatMode, setChatMode] = useState<ChatMode>('direct');
  const [chatRows, setChatRows] = useState<any[]>([]);
  const [chatSearch, setChatSearch] = useState('');
  const [contentMode, setContentMode] = useState<ContentMode>('posts');
  const [contentRows, setContentRows] = useState<any[]>([]);
  const [rooms, setRooms] = useState<any[]>([]);
  const [roomSearch, setRoomSearch] = useState('');
  const [selectedRoom, setSelectedRoom] = useState<any | null>(null);
  const [roomAudit, setRoomAudit] = useState<any[]>([]);
  const [auditRows, setAuditRows] = useState<any[]>([]);
  const [auditPage, setAuditPage] = useState(1);
  const [auditHasMore, setAuditHasMore] = useState(false);
  const [auditAction, setAuditAction] = useState('');
  const [admins, setAdmins] = useState<PortalUser[]>([]);
  const [adminSearch, setAdminSearch] = useState('');

  const portal = useMemo(() => {
    return <T,>(path: string, init?: RequestInit) => apiRequest<T>(PORTAL_API, path, session?.token, init);
  }, [session?.token]);

  const meet = useMemo(() => {
    return <T,>(path: string, init?: RequestInit) => apiRequest<T>(MEET_API, path, session?.token, init);
  }, [session?.token]);

  async function run(label: string, fn: () => Promise<void>) {
    setLoading(true);
    setError('');
    try {
      await fn();
      if (label) setNotice(label);
    } catch (err: any) {
      setError(err.message || 'Terjadi kesalahan.');
    } finally {
      setLoading(false);
    }
  }

  async function loadDashboard() {
    const [app, meetStats] = await Promise.all([
      portal<any>('/admin-panel/overview'),
      meet<any>('/admin/overview'),
    ]);
    setAppOverview(app);
    setMeetOverview(meetStats);
  }

  async function loadUsers() {
    const query = new URLSearchParams({ per_page: '50' });
    if (userSearch.trim()) query.set('search', userSearch.trim());
    const page = await portal<PageResult<PortalUser>>(`/admin-panel/users?${query}`);
    setUsers(page.data || []);
  }

  async function loadChats() {
    const query = new URLSearchParams({ per_page: '50' });
    if (chatSearch.trim()) query.set('search', chatSearch.trim());
    const path = chatMode === 'direct' ? '/admin-panel/direct-messages' : '/admin-panel/group-messages';
    const page = await portal<PageResult<any>>(`${path}?${query}`);
    setChatRows(page.data || []);
  }

  async function loadContent() {
    const page = await portal<PageResult<any>>(`/admin-panel/${contentMode}?per_page=50`);
    setContentRows(page.data || []);
  }

  async function loadRooms() {
    const query = new URLSearchParams({ limit: '100' });
    if (roomSearch.trim()) query.set('search', roomSearch.trim());
    const data = await meet<{ rooms: any[] }>(`/admin/rooms?${query}`);
    setRooms(data.rooms || []);
  }

  async function loadRoomDetail(roomId: string) {
    const [detail, audit] = await Promise.all([
      meet<any>(`/admin/rooms/${roomId}`),
      meet<any>(`/admin/rooms/${roomId}/audit`),
    ]);
    setSelectedRoom(detail);
    setRoomAudit(audit.audit || []);
  }

  async function loadAudit(reset = true) {
    const nextPage = reset ? 1 : auditPage + 1;
    const query = new URLSearchParams({ per_page: '50', page: String(nextPage) });
    if (auditAction.trim()) query.set('action', auditAction.trim());
    const page = await portal<PageResult<any>>(`/admin-panel/audit-logs?${query}`);
    const rows = page.data || [];
    setAuditRows(reset ? rows : [...auditRows, ...rows]);
    setAuditPage(page.current_page || nextPage);
    setAuditHasMore((page.current_page || nextPage) < (page.last_page || nextPage));
  }

  async function loadAdmins() {
    const query = new URLSearchParams({ per_page: '100' });
    if (adminSearch.trim()) query.set('search', adminSearch.trim());
    else query.set('is_verified', '1');
    const page = await portal<PageResult<PortalUser>>(`/admin-panel/users?${query}`);
    setAdmins(page.data || []);
  }

  async function setAdminAccess(user: PortalUser, grant: boolean) {
    await run(grant ? `@${user.username} kini admin.` : `Akses admin @${user.username} dicabut.`, async () => {
      await portal(`/admin-panel/users/${user.user_id}`, {
        method: 'PATCH',
        body: JSON.stringify({ is_verified: grant }),
      });
      await loadAdmins();
    });
  }

  async function reloadCurrent() {
    await run('', async () => {
      if (tab === 'dashboard') await loadDashboard();
      if (tab === 'users') await loadUsers();
      if (tab === 'admins') await loadAdmins();
      if (tab === 'chats') await loadChats();
      if (tab === 'content') await loadContent();
      if (tab === 'meet') await loadRooms();
      if (tab === 'audit') await loadAudit(true);
    });
  }

  useEffect(() => {
    if (!session) return;
    reloadCurrent();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session?.token, tab, chatMode, contentMode]);

  async function handleLogin(login: string, password: string) {
    await run('', async () => {
      const payload = await apiRequest<{ token: string; user: PortalUser }>(PORTAL_API, '/login', undefined, {
        method: 'POST',
        body: JSON.stringify({ login, password }),
      });
      if (!payload.user?.is_verified) {
        throw new Error('Akun ini belum is_verified=1, jadi tidak bisa masuk admin panel.');
      }
      const nextSession = { token: payload.token, user: payload.user };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(nextSession));
      setSession(nextSession);
      setNotice('Login berhasil.');
    });
  }

  function logout() {
    localStorage.removeItem(STORAGE_KEY);
    setSession(null);
    setTab('dashboard');
  }

  async function banUser(user: PortalUser) {
    const reason = window.prompt(`Alasan blokir untuk @${user.username}:`, user.ban_reason || '');
    if (reason === null) return;
    await run('User berhasil diblokir.', async () => {
      await portal(`/admin-panel/users/${user.user_id}/ban`, {
        method: 'POST',
        body: JSON.stringify({ reason }),
      });
      await loadUsers();
    });
  }

  async function unbanUser(user: PortalUser) {
    await run('Blokir user dibuka.', async () => {
      await portal(`/admin-panel/users/${user.user_id}/unban`, { method: 'POST' });
      await loadUsers();
    });
  }

  async function deleteUser(user: PortalUser) {
    const ok = window.confirm(`Anonimkan dan blokir permanen @${user.username}?`);
    if (!ok) return;
    await run('User dianonimkan.', async () => {
      await portal(`/admin-panel/users/${user.user_id}`, {
        method: 'DELETE',
        body: JSON.stringify({ mode: 'anonymize', reason: 'Dihapus dari admin panel.' }),
      });
      await loadUsers();
    });
  }

  async function updateUser(user: PortalUser) {
    await run('User berhasil diperbarui.', async () => {
      await portal(`/admin-panel/users/${user.user_id}`, {
        method: 'PATCH',
        body: JSON.stringify(user),
      });
      setEditingUser(null);
      await loadUsers();
    });
  }

  async function editMessage(row: any) {
    const id = chatMode === 'direct' ? row.message_id : row.id;
    const next = window.prompt('Ubah isi pesan:', row.content || '');
    if (next === null) return;
    await run('Pesan diperbarui.', async () => {
      const path = chatMode === 'direct' ? `/admin-panel/direct-messages/${id}` : `/admin-panel/group-messages/${id}`;
      await portal(path, { method: 'PATCH', body: JSON.stringify({ content: next }) });
      await loadChats();
    });
  }

  async function deleteMessage(row: any) {
    const id = chatMode === 'direct' ? row.message_id : row.id;
    const ok = window.confirm(chatMode === 'direct' ? 'Hapus direct message ini?' : 'Sembunyikan group message ini?');
    if (!ok) return;
    await run('Pesan diproses.', async () => {
      const path = chatMode === 'direct' ? `/admin-panel/direct-messages/${id}` : `/admin-panel/group-messages/${id}`;
      await portal(path, { method: 'DELETE' });
      await loadChats();
    });
  }

  async function editContent(row: any) {
    const id = row.post_id || row.comment_id || row.story_id || row.id;
    const field = contentMode === 'groups' ? 'name' : contentMode === 'posts' ? 'caption' : contentMode === 'comments' ? 'content' : 'caption';
    const next = window.prompt(`Ubah ${field}:`, row[field] || '');
    if (next === null) return;
    await run('Konten diperbarui.', async () => {
      await portal(`/admin-panel/${contentMode}/${id}`, {
        method: 'PATCH',
        body: JSON.stringify({ [field]: next }),
      });
      await loadContent();
    });
  }

  async function deleteContent(row: any) {
    const id = row.post_id || row.comment_id || row.story_id || row.id;
    const ok = window.confirm(`Hapus item ${contentMode} #${id}?`);
    if (!ok) return;
    await run('Konten dihapus.', async () => {
      await portal(`/admin-panel/${contentMode}/${id}`, { method: 'DELETE' });
      await loadContent();
    });
  }

  async function patchRoom(roomId: string, body: Record<string, unknown>, label = 'Room diperbarui.') {
    await run(label, async () => {
      await meet(`/admin/rooms/${roomId}`, { method: 'PATCH', body: JSON.stringify(body) });
      await loadRooms();
      if (selectedRoom?.room?.id === roomId) await loadRoomDetail(roomId);
    });
  }

  async function deleteRoom(roomId: string) {
    const ok = window.confirm(`Hapus room ${roomId} dan tutup sesi LiveKit aktif?`);
    if (!ok) return;
    await run('Room dihapus.', async () => {
      await meet(`/admin/rooms/${roomId}`, { method: 'DELETE' });
      setSelectedRoom(null);
      await loadRooms();
    });
  }

  async function observerRoom(roomId: string) {
    await run('Observer moderator dibuat.', async () => {
      const data = await meet<{ joinUrl: string; notice: string }>(`/admin/rooms/${roomId}/observer-token`, {
        method: 'POST',
      });
      setNotice(data.notice);
      window.open(data.joinUrl, '_blank', 'noopener,noreferrer');
    });
  }

  async function kickParticipant(roomId: string, identity: string) {
    const ok = window.confirm(`Keluarkan peserta ${identity}?`);
    if (!ok) return;
    await run('Peserta dikeluarkan.', async () => {
      await meet(`/admin/rooms/${roomId}/kick`, {
        method: 'POST',
        body: JSON.stringify({ identity }),
      });
      await loadRoomDetail(roomId);
    });
  }

  if (!session) {
    return <LoginScreen loading={loading} error={error} onLogin={handleLogin} />;
  }

  return (
    <div className={`app-shell${collapsed ? ' nav-collapsed' : ''}${navOpen ? ' nav-open' : ''}`}>
      {navOpen && <div className="nav-backdrop" onClick={() => setNavOpen(false)} />}
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-mark"><ShieldCheck size={22} /></div>
          <div className="brand-text">
            <strong>Portal SI</strong>
            <span>Admin Panel</span>
          </div>
          <button
            className="icon-button collapse-toggle"
            onClick={() => setCollapsed(v => !v)}
            title={collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}
          >
            {collapsed ? <ChevronsRight size={16} /> : <ChevronsLeft size={16} />}
          </button>
        </div>
        <nav>
          {navGroups.map(group => (
            <div key={group.heading} className="nav-group">
              <p className="nav-heading">{group.heading}</p>
              {group.items.map(item => {
                const Icon = item.icon;
                return (
                  <button
                    key={item.id}
                    className={tab === item.id ? 'nav-item active' : 'nav-item'}
                    onClick={() => selectTab(item.id)}
                    title={item.label}
                  >
                    <Icon size={18} />
                    <span>{item.label}</span>
                  </button>
                );
              })}
            </div>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="admin-chip">
            <UserCheck size={16} />
            <span>{session.user.full_name || session.user.username}</span>
          </div>
          <button className="ghost-button" onClick={logout} title="Logout">
            <LogOut size={16} />
            <span>Logout</span>
          </button>
        </div>
      </aside>

      <main className="workspace">
        <header className="topbar">
          <div className="topbar-lead">
            <button className="icon-button nav-hamburger" onClick={() => setNavOpen(true)} title="Menu">
              <Menu size={18} />
            </button>
            <div>
              <p className="eyebrow">admin.portalsi.com</p>
              <h1>{navItems.find(item => item.id === tab)?.label}</h1>
            </div>
          </div>
          <button className="icon-button" onClick={reloadCurrent} disabled={loading} title="Refresh data">
            <RefreshCw size={18} className={loading ? 'spin' : ''} />
          </button>
        </header>

        {(notice || error) && (
          <div className="toast-stack">
            <div className={error ? 'toast error' : 'toast'} role="status">
              <span>{error || notice}</span>
              <button onClick={() => { setNotice(''); setError(''); }} title="Tutup"><X size={16} /></button>
            </div>
          </div>
        )}

        {tab === 'dashboard' && <Dashboard appOverview={appOverview} meetOverview={meetOverview} />}
        {tab === 'users' && (
          <UsersPanel
            users={users}
            userSearch={userSearch}
            setUserSearch={setUserSearch}
            loadUsers={() => run('', loadUsers)}
            onEdit={setEditingUser}
            onBan={banUser}
            onUnban={unbanUser}
            onDelete={deleteUser}
          />
        )}
        {tab === 'chats' && (
          <ChatsPanel
            mode={chatMode}
            setMode={setChatMode}
            rows={chatRows}
            search={chatSearch}
            setSearch={setChatSearch}
            loadChats={() => run('', loadChats)}
            onEdit={editMessage}
            onDelete={deleteMessage}
          />
        )}
        {tab === 'content' && (
          <ContentPanel
            mode={contentMode}
            setMode={setContentMode}
            rows={contentRows}
            onEdit={editContent}
            onDelete={deleteContent}
          />
        )}
        {tab === 'meet' && (
          <MeetPanel
            rooms={rooms}
            selectedRoom={selectedRoom}
            roomAudit={roomAudit}
            search={roomSearch}
            setSearch={setRoomSearch}
            loadRooms={() => run('', loadRooms)}
            loadRoomDetail={(roomId) => run('', () => loadRoomDetail(roomId))}
            patchRoom={patchRoom}
            deleteRoom={deleteRoom}
            observerRoom={observerRoom}
            kickParticipant={kickParticipant}
          />
        )}
        {tab === 'admins' && (
          <AdminsPanel
            admins={admins}
            currentUserId={session.user.user_id}
            search={adminSearch}
            setSearch={setAdminSearch}
            loadAdmins={() => run('', loadAdmins)}
            onEdit={setEditingUser}
            onGrant={(user) => setAdminAccess(user, true)}
            onRevoke={(user) => setAdminAccess(user, false)}
          />
        )}
        {tab === 'audit' && (
          <AuditPanel
            rows={auditRows}
            action={auditAction}
            setAction={setAuditAction}
            onSearch={() => run('', () => loadAudit(true))}
            onLoadMore={() => run('', () => loadAudit(false))}
            hasMore={auditHasMore}
            loading={loading}
          />
        )}
      </main>

      {editingUser && (
        <EditUserModal
          user={editingUser}
          onClose={() => setEditingUser(null)}
          onSave={updateUser}
        />
      )}
    </div>
  );
}

function LoginScreen({ loading, error, onLogin }: { loading: boolean; error: string; onLogin: (login: string, password: string) => Promise<void> }) {
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');

  async function submit(event: FormEvent) {
    event.preventDefault();
    await onLogin(login.trim(), password);
  }

  return (
    <main className="login-screen">
      <section className="login-panel">
        <div className="brand large">
          <div className="brand-mark"><ShieldAlert size={26} /></div>
          <div>
            <strong>Portal SI Admin</strong>
            <span>Login dengan akun Portal SI terverifikasi</span>
          </div>
        </div>
        <form onSubmit={submit} className="login-form">
          <label>
            Username atau email
            <input value={login} onChange={event => setLogin(event.target.value)} autoComplete="username" required />
          </label>
          <label>
            Password
            <input type="password" value={password} onChange={event => setPassword(event.target.value)} autoComplete="current-password" required />
          </label>
          {error && <p className="form-error">{error}</p>}
          <button className="primary-button" disabled={loading}>
            <ShieldCheck size={18} />
            <span>{loading ? 'Memeriksa...' : 'Masuk Admin Panel'}</span>
          </button>
        </form>
      </section>
    </main>
  );
}

function Dashboard({ appOverview, meetOverview }: { appOverview: any; meetOverview: any }) {
  const u = appOverview?.users || {};
  const c = appOverview?.content || {};
  const r = meetOverview?.rooms || {};
  return (
    <section className="stack">
      <div className="metric-grid">
        <Metric label="Total User" value={u.total} icon={Users} tone="blue" />
        <Metric label="Admin (verified)" value={u.verified} icon={ShieldCheck} tone="green" />
        <Metric label="User Diblokir" value={u.banned} icon={Ban} tone="red" />
        <Metric label="Online" value={u.online} icon={Activity} tone="green" />
        <Metric label="Aktif 24 Jam" value={u.active_24h} icon={Activity} tone="amber" />
        <Metric label="Room Meet" value={r.total} icon={Video} tone="blue" />
        <Metric label="Room Terkunci" value={r.locked} icon={Lock} tone="amber" />
        <Metric label="Direct Messages" value={c.direct_messages} icon={MessageSquare} tone="blue" />
        <Metric label="Posts" value={c.posts} icon={FileText} tone="green" />
        <Metric label="Groups" value={c.groups} icon={Users} tone="blue" />
      </div>
      <div className="dashboard-columns">
        <section className="panel">
          <Header title="User Terbaru" subtitle="Akun terbaru dan status moderasinya." />
          <Table>
            <thead><tr><th>User</th><th>Role</th><th>Verified</th><th>Banned</th><th>Dibuat</th></tr></thead>
            <tbody>
              {(appOverview?.recent_users || []).map((user: PortalUser) => (
                <tr key={user.user_id}>
                  <td><UserCell user={user} /></td>
                  <td>{user.role || '-'}</td>
                  <td><Badge ok={user.is_verified}>{user.is_verified ? 'verified' : 'no'}</Badge></td>
                  <td><Badge danger={user.is_banned}>{user.is_banned ? 'banned' : 'clear'}</Badge></td>
                  <td>{fmt(user.created_at)}</td>
                </tr>
              ))}
              {(appOverview?.recent_users || []).length === 0 && (
                <tr><td colSpan={5} className="empty-cell">Belum ada data.</td></tr>
              )}
            </tbody>
          </Table>
        </section>
        <section className="panel">
          <Header title="Aktivitas Admin Terbaru" subtitle="12 aksi admin terakhir." />
          <ul className="activity-feed">
            {(appOverview?.recent_audit_logs || []).map((log: any) => (
              <li key={log.id}>
                <span className="activity-action">{log.action}</span>
                <span className="activity-meta">
                  {(log.actor?.username || 'sistem')} · {fmt(log.created_at)}
                </span>
              </li>
            ))}
            {(appOverview?.recent_audit_logs || []).length === 0 && (
              <li className="empty-cell">Belum ada aktivitas.</li>
            )}
          </ul>
        </section>
      </div>
    </section>
  );
}

function UsersPanel(props: {
  users: PortalUser[];
  userSearch: string;
  setUserSearch: (value: string) => void;
  loadUsers: () => void;
  onEdit: (user: PortalUser) => void;
  onBan: (user: PortalUser) => void;
  onUnban: (user: PortalUser) => void;
  onDelete: (user: PortalUser) => void;
}) {
  return (
    <section className="panel">
      <Header title="User Control" subtitle="Ban, unban, edit profil, catatan admin, dan anonimisasi akun." />
      <Toolbar>
        <SearchBox value={props.userSearch} onChange={props.setUserSearch} onSubmit={props.loadUsers} placeholder="Cari username, nama, email" />
      </Toolbar>
      <Table>
        <thead><tr><th>ID</th><th>User</th><th>Role</th><th>Status</th><th>Aktivitas</th><th>Aksi</th></tr></thead>
        <tbody>
          {props.users.map(user => (
            <tr key={user.user_id}>
              <td>{user.user_id}</td>
              <td><UserCell user={user} /></td>
              <td>{user.role || '-'}</td>
              <td>
                <div className="badges">
                  <Badge ok={user.is_verified}>verified</Badge>
                  <Badge danger={user.is_banned}>{user.is_banned ? 'banned' : 'clear'}</Badge>
                </div>
              </td>
              <td>{fmt(user.last_activity)}</td>
              <td><ActionBar>
                <IconAction title="Edit user" onClick={() => props.onEdit(user)} icon={Pencil} />
                {user.is_banned
                  ? <IconAction title="Unban user" onClick={() => props.onUnban(user)} icon={Unlock} />
                  : <IconAction title="Ban user" onClick={() => props.onBan(user)} icon={Ban} danger />}
                <IconAction title="Anonimkan user" onClick={() => props.onDelete(user)} icon={UserX} danger />
              </ActionBar></td>
            </tr>
          ))}
        </tbody>
      </Table>
    </section>
  );
}

function ChatsPanel(props: {
  mode: ChatMode;
  setMode: (mode: ChatMode) => void;
  rows: any[];
  search: string;
  setSearch: (value: string) => void;
  loadChats: () => void;
  onEdit: (row: any) => void;
  onDelete: (row: any) => void;
}) {
  return (
    <section className="panel">
      <Header title="Chat Moderation" subtitle="Lihat, edit, dan hapus direct message maupun group message." />
      <Toolbar>
        <Segmented value={props.mode} onChange={value => props.setMode(value as ChatMode)} options={[['direct', 'Direct'], ['group', 'Group']]} />
        <SearchBox value={props.search} onChange={props.setSearch} onSubmit={props.loadChats} placeholder="Cari isi pesan" />
      </Toolbar>
      <Table>
        <thead><tr><th>ID</th><th>Pengirim</th><th>Tujuan</th><th>Pesan</th><th>Waktu</th><th>Aksi</th></tr></thead>
        <tbody>
          {props.rows.map(row => (
            <tr key={props.mode === 'direct' ? row.message_id : row.id}>
              <td>{props.mode === 'direct' ? row.message_id : row.id}</td>
              <td>{row.sender?.username || row.sender_id || '-'}</td>
              <td>{props.mode === 'direct' ? (row.receiver?.username || row.receiver_id) : (row.group?.name || row.group_id)}</td>
              <td className="wide-cell">{short(row.content, 140)}</td>
              <td>{fmt(row.sent_at)}</td>
              <td><ActionBar>
                <IconAction title="Edit pesan" onClick={() => props.onEdit(row)} icon={Pencil} />
                <IconAction title={props.mode === 'direct' ? 'Hapus pesan' : 'Sembunyikan pesan'} onClick={() => props.onDelete(row)} icon={Trash2} danger />
              </ActionBar></td>
            </tr>
          ))}
        </tbody>
      </Table>
    </section>
  );
}

function ContentPanel(props: {
  mode: ContentMode;
  setMode: (mode: ContentMode) => void;
  rows: any[];
  onEdit: (row: any) => void;
  onDelete: (row: any) => void;
}) {
  return (
    <section className="panel">
      <Header title="Content Control" subtitle="Moderasi post, comment, story, dan group dari API Portal SI." />
      <Toolbar>
        <Segmented value={props.mode} onChange={value => props.setMode(value as ContentMode)} options={[
          ['posts', 'Posts'],
          ['comments', 'Comments'],
          ['stories', 'Stories'],
          ['groups', 'Groups'],
        ]} />
      </Toolbar>
      <Table>
        <thead><tr><th>ID</th><th>Pemilik</th><th>Konten</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr></thead>
        <tbody>
          {props.rows.map(row => {
            const id = row.post_id || row.comment_id || row.story_id || row.id;
            return (
              <tr key={id}>
                <td>{id}</td>
                <td>{row.user?.username || row.owner?.username || row.user_id || row.owner_id || '-'}</td>
                <td className="wide-cell">{short(row.caption || row.content || row.name || row.description, 140)}</td>
                <td>
                  {props.mode === 'posts' && <Badge>{row.is_archived ? 'archived' : 'public'}</Badge>}
                  {props.mode === 'stories' && <Badge>{row.type || 'story'}</Badge>}
                  {props.mode === 'groups' && <Badge>{row.members_count ?? 0} member</Badge>}
                  {props.mode === 'comments' && <Badge>comment</Badge>}
                </td>
                <td>{fmt(row.created_at || row.expires_at)}</td>
                <td><ActionBar>
                  <IconAction title="Edit item" onClick={() => props.onEdit(row)} icon={Pencil} />
                  <IconAction title="Hapus item" onClick={() => props.onDelete(row)} icon={Trash2} danger />
                </ActionBar></td>
              </tr>
            );
          })}
        </tbody>
      </Table>
    </section>
  );
}

function MeetPanel(props: {
  rooms: any[];
  selectedRoom: any | null;
  roomAudit: any[];
  search: string;
  setSearch: (value: string) => void;
  loadRooms: () => void;
  loadRoomDetail: (roomId: string) => void;
  patchRoom: (roomId: string, body: Record<string, unknown>, label?: string) => void;
  deleteRoom: (roomId: string) => void;
  observerRoom: (roomId: string) => void;
  kickParticipant: (roomId: string, identity: string) => void;
}) {
  const detail = props.selectedRoom?.room;
  return (
    <section className="split-layout">
      <div className="panel">
        <Header title="Meet Room Control" subtitle="Lihat room aktif, lock, ubah permission, hapus room, dan masuk sebagai moderator tercatat." />
        <Toolbar>
          <SearchBox value={props.search} onChange={props.setSearch} onSubmit={props.loadRooms} placeholder="Cari room, host, username" />
        </Toolbar>
        <Table>
          <thead><tr><th>Room</th><th>Host</th><th>Status</th><th>Jadwal</th><th>Aksi</th></tr></thead>
          <tbody>
            {props.rooms.map(room => (
              <tr key={room.id}>
                <td><strong className="mono">{room.id}</strong></td>
                <td>{room.hostName}<small>@{room.hostUsername || 'guest'}</small></td>
                <td><div className="badges">
                  <Badge danger={room.adminLocked}>{room.adminLocked ? 'locked' : 'open'}</Badge>
                  <Badge>{room.permissions?.allowJoin === false ? 'join off' : 'join on'}</Badge>
                </div></td>
                <td>{fmt(room.scheduledFor)}</td>
                <td><ActionBar>
                  <IconAction title="Detail room" onClick={() => props.loadRoomDetail(room.id)} icon={Eye} />
                  <IconAction title={room.adminLocked ? 'Buka kunci — izinkan peserta baru masuk' : 'Kunci room — cegah peserta baru masuk'} onClick={() => props.patchRoom(room.id, { adminLocked: !room.adminLocked })} icon={room.adminLocked ? Unlock : Lock} />
                  <IconAction title="Tonton room sebagai admin (observer, tanpa mengganggu)" onClick={() => props.observerRoom(room.id)} icon={Radio} />
                  <IconAction title="Hapus room" onClick={() => props.deleteRoom(room.id)} icon={Trash2} danger />
                </ActionBar></td>
              </tr>
            ))}
          </tbody>
        </Table>
      </div>

      <aside className="detail-pane">
        {detail ? (
          <>
            <Header title={`Room ${detail.id}`} subtitle={`Host ${detail.hostName || '-'} | dibuat ${fmt(detail.createdAt)}`} />
            <div className="toggle-grid">
              <Toggle label="Locked" checked={Boolean(detail.adminLocked)} onChange={checked => props.patchRoom(detail.id, { adminLocked: checked })} />
              <Toggle label="Allow Join" checked={detail.permissions?.allowJoin !== false} onChange={checked => props.patchRoom(detail.id, { permissions: { ...detail.permissions, allowJoin: checked } })} />
              <Toggle label="Lobby" checked={Boolean(detail.permissions?.lobbyMode)} onChange={checked => props.patchRoom(detail.id, { permissions: { ...detail.permissions, lobbyMode: checked } })} />
              <Toggle label="Chat" checked={detail.permissions?.allowChat !== false} onChange={checked => props.patchRoom(detail.id, { permissions: { ...detail.permissions, allowChat: checked } })} />
              <Toggle label="Screen" checked={detail.permissions?.allowScreenShare !== false} onChange={checked => props.patchRoom(detail.id, { permissions: { ...detail.permissions, allowScreenShare: checked } })} />
              <Toggle label="Rename" checked={detail.permissions?.allowRename !== false} onChange={checked => props.patchRoom(detail.id, { permissions: { ...detail.permissions, allowRename: checked } })} />
            </div>
            <div className="detail-actions">
              <button className="secondary-button" onClick={() => props.observerRoom(detail.id)}><Radio size={16} /> Observer</button>
              <button className="danger-button" onClick={() => props.deleteRoom(detail.id)}><Trash2 size={16} /> Hapus Room</button>
            </div>
            <h3>Participants</h3>
            <div className="list">
              {(props.selectedRoom?.participants || []).map((participant: any) => (
                <div className="list-row" key={participant.identity}>
                  <div><strong>{participant.name || participant.identity}</strong><span>{participant.identity}</span></div>
                  <IconAction title="Kick peserta" icon={UserX} danger onClick={() => props.kickParticipant(detail.id, participant.identity)} />
                </div>
              ))}
              {(!props.selectedRoom?.participants || props.selectedRoom.participants.length === 0) && <p className="empty">Tidak ada participant aktif atau LiveKit API belum tersedia.</p>}
            </div>
            <h3>Audit Room</h3>
            <div className="audit-list">
              {props.roomAudit.map((row, index) => (
                <div key={`${row.ts}-${index}`}><strong>{row.action}</strong><span>{fmt(row.ts)} oleh {row.actorUsername || row.actorUserId || 'system'}</span></div>
              ))}
            </div>
          </>
        ) : (
          <p className="empty">Pilih room untuk melihat participant, permission, dan audit.</p>
        )}
      </aside>
    </section>
  );
}

function AuditPanel(props: {
  rows: any[];
  action: string;
  setAction: (value: string) => void;
  onSearch: () => void;
  onLoadMore: () => void;
  hasMore: boolean;
  loading: boolean;
}) {
  return (
    <section className="panel">
      <Header title="Audit Trail" subtitle="Jejak aksi admin dari Portal SI App API." />
      <Toolbar>
        <SearchBox value={props.action} onChange={props.setAction} onSubmit={props.onSearch} placeholder="Filter aksi (mis. user.viewed)" />
      </Toolbar>
      <Table>
        <thead><tr><th>Waktu</th><th>Admin</th><th>Aksi</th><th>Target</th><th>IP</th></tr></thead>
        <tbody>
          {props.rows.map(row => (
            <tr key={row.id}>
              <td>{fmt(row.created_at)}</td>
              <td>{row.actor?.username || row.actor_user_id || 'sistem'}</td>
              <td><Badge>{row.action}</Badge></td>
              <td>{row.target_type ? `${row.target_type} #${row.target_id ?? '-'}` : '-'}</td>
              <td>{row.ip_address || '-'}</td>
            </tr>
          ))}
          {props.rows.length === 0 && (
            <tr><td colSpan={5} className="empty-cell">Belum ada log audit.</td></tr>
          )}
        </tbody>
      </Table>
      {props.hasMore && (
        <div className="load-more">
          <button className="secondary-button" onClick={props.onLoadMore} disabled={props.loading}>
            {props.loading ? 'Memuat...' : 'Muat lebih banyak'}
          </button>
        </div>
      )}
    </section>
  );
}

function AdminsPanel(props: {
  admins: PortalUser[];
  currentUserId: number;
  search: string;
  setSearch: (value: string) => void;
  loadAdmins: () => void;
  onEdit: (user: PortalUser) => void;
  onGrant: (user: PortalUser) => void;
  onRevoke: (user: PortalUser) => void;
}) {
  const searching = props.search.trim().length > 0;
  return (
    <section className="panel">
      <Header
        title="Manajemen Admin"
        subtitle="Akses admin panel = akun terverifikasi. Beri atau cabut akses, dan atur peran. Kamu tidak bisa mencabut akses akunmu sendiri."
      />
      <Toolbar>
        <SearchBox
          value={props.search}
          onChange={props.setSearch}
          onSubmit={props.loadAdmins}
          placeholder="Cari user untuk dijadikan admin (username, nama, email)"
        />
      </Toolbar>
      <p className="panel-hint">
        {searching
          ? 'Menampilkan hasil pencarian — beri akses admin ke akun mana pun.'
          : 'Menampilkan admin aktif (akun terverifikasi). Cari untuk menambah admin baru.'}
      </p>
      <Table>
        <thead><tr><th>ID</th><th>User</th><th>Peran</th><th>Status</th><th>Aktivitas</th><th>Aksi</th></tr></thead>
        <tbody>
          {props.admins.map(user => {
            const isAdmin = user.is_verified;
            const isSelf = user.user_id === props.currentUserId;
            return (
              <tr key={user.user_id}>
                <td>{user.user_id}</td>
                <td><UserCell user={user} /></td>
                <td>{user.role || '-'}</td>
                <td>
                  <div className="badges">
                    <Badge ok={isAdmin} danger={!isAdmin}>{isAdmin ? 'admin' : 'bukan admin'}</Badge>
                    {isSelf && <Badge ok>kamu</Badge>}
                    {user.is_banned && <Badge danger>banned</Badge>}
                  </div>
                </td>
                <td>{fmt(user.last_activity)}</td>
                <td><ActionBar>
                  <IconAction title="Edit peran / profil" onClick={() => props.onEdit(user)} icon={Pencil} />
                  {isAdmin
                    ? <IconAction title={isSelf ? 'Tidak bisa mencabut akses sendiri' : 'Cabut akses admin'} onClick={() => !isSelf && props.onRevoke(user)} icon={ShieldAlert} danger />
                    : <IconAction title="Jadikan admin" onClick={() => props.onGrant(user)} icon={ShieldCheck} />}
                </ActionBar></td>
              </tr>
            );
          })}
          {props.admins.length === 0 && (
            <tr><td colSpan={6} className="empty-cell">Tidak ada data.</td></tr>
          )}
        </tbody>
      </Table>
    </section>
  );
}

function EditUserModal({ user, onClose, onSave }: { user: PortalUser; onClose: () => void; onSave: (user: PortalUser) => void }) {
  const [draft, setDraft] = useState<PortalUser>({ ...user });

  function submit(event: FormEvent) {
    event.preventDefault();
    onSave(draft);
  }

  return (
    <div className="modal-backdrop">
      <form className="modal" onSubmit={submit}>
        <Header title={`Edit @${user.username}`} subtitle="Perubahan ini langsung masuk ke database Portal SI." />
        <div className="form-grid">
          <label>Username<input value={draft.username || ''} onChange={event => setDraft({ ...draft, username: event.target.value })} /></label>
          <label>Nama<input value={draft.full_name || ''} onChange={event => setDraft({ ...draft, full_name: event.target.value })} /></label>
          <label>Email<input value={draft.email || ''} onChange={event => setDraft({ ...draft, email: event.target.value })} /></label>
          <label>Role<select value={draft.role || 'student'} onChange={event => setDraft({ ...draft, role: event.target.value })}>
            <option value="student">student</option>
            <option value="teacher">teacher</option>
            <option value="parent">parent</option>
            <option value="other">other</option>
            <option value="dev">dev</option>
          </select></label>
          <label className="span-2">Bio<textarea value={draft.bio || ''} onChange={event => setDraft({ ...draft, bio: event.target.value })} /></label>
          <label className="span-2">Ban Reason<textarea value={draft.ban_reason || ''} onChange={event => setDraft({ ...draft, ban_reason: event.target.value })} /></label>
          <label className="span-2">Admin Notes<textarea value={draft.admin_notes || ''} onChange={event => setDraft({ ...draft, admin_notes: event.target.value })} /></label>
          <label className="check"><input type="checkbox" checked={Boolean(draft.is_verified)} onChange={event => setDraft({ ...draft, is_verified: event.target.checked })} /> Verified</label>
          <label className="check"><input type="checkbox" checked={Boolean(draft.is_banned)} onChange={event => setDraft({ ...draft, is_banned: event.target.checked })} /> Banned</label>
        </div>
        <div className="modal-actions">
          <button type="button" className="ghost-button" onClick={onClose}><X size={16} /> Batal</button>
          <button className="primary-button"><Save size={16} /> Simpan</button>
        </div>
      </form>
    </div>
  );
}

function Metric({ label, value, icon: Icon, tone }: { label: string; value: unknown; icon: ElementType; tone: string }) {
  return (
    <div className={`metric ${tone}`}>
      <Icon size={20} />
      <div><span>{label}</span><strong>{String(value ?? '-')}</strong></div>
    </div>
  );
}

function Header({ title, subtitle }: { title: string; subtitle?: string }) {
  return <div className="section-header"><h2>{title}</h2>{subtitle && <p>{subtitle}</p>}</div>;
}

function Toolbar({ children }: { children: ReactNode }) {
  return <div className="toolbar">{children}</div>;
}

function SearchBox({ value, onChange, onSubmit, placeholder }: { value: string; onChange: (value: string) => void; onSubmit: () => void; placeholder: string }) {
  return (
    <form className="search-box" onSubmit={event => { event.preventDefault(); onSubmit(); }}>
      <Search size={16} />
      <input value={value} onChange={event => onChange(event.target.value)} placeholder={placeholder} />
      <button type="submit">Cari</button>
    </form>
  );
}

function Segmented({ value, options, onChange }: { value: string; options: Array<[string, string]>; onChange: (value: string) => void }) {
  return <div className="segmented">{options.map(([id, label]) => <button key={id} className={value === id ? 'active' : ''} onClick={() => onChange(id)}>{label}</button>)}</div>;
}

function Table({ children }: { children: ReactNode }) {
  return <div className="table-wrap"><table>{children}</table></div>;
}

function UserCell({ user }: { user: PortalUser }) {
  return <div className="user-cell"><strong>{user.full_name || user.username}</strong><span>@{user.username} {user.email ? `| ${user.email}` : ''}</span></div>;
}

function Badge({ children, ok, danger }: { children: ReactNode; ok?: boolean; danger?: boolean }) {
  return <span className={`badge ${ok ? 'ok' : ''} ${danger ? 'danger' : ''}`}>{children}</span>;
}

function ActionBar({ children }: { children: ReactNode }) {
  return <div className="action-bar">{children}</div>;
}

function IconAction({ icon: Icon, title, onClick, danger }: { icon: ElementType; title: string; onClick: () => void; danger?: boolean }) {
  return <button className={danger ? 'icon-action danger' : 'icon-action'} title={title} onClick={onClick}><Icon size={16} /></button>;
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (value: boolean) => void }) {
  return <label className="toggle"><input type="checkbox" checked={checked} onChange={event => onChange(event.target.checked)} /><span>{label}</span></label>;
}
