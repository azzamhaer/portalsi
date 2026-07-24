import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import type { ElementType, ReactNode } from 'react';
import {
  Activity,
  Ban,
  ChevronDown,
  ChevronsLeft,
  ChevronsRight,
  ClipboardList,
  Eye,
  FileText,
  Gavel,
  Lock,
  LogOut,
  Menu,
  MessageSquare,
  Pencil,
  Plus,
  Power,
  ListChecks,
  ScrollText,
  Image as ImageIcon,
  Radio,
  RefreshCw,
  RotateCcw,
  Save,
  Search,
  Settings,
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

const PORTAL_API = (import.meta.env.VITE_PORTALSI_API_URL || 'https://api..portalsi.com/api').replace(/\/+$/, '');
const MEET_API = (import.meta.env.VITE_MEET_API_URL || 'https://meet.portalsi.com/api').replace(/\/+$/, '');
const STORAGE_KEY = 'portalsi-admin-session';

type Tab = 'dashboard' | 'users' | 'admins' | 'chats' | 'content' | 'moderation' | 'policies' | 'meet' | 'appeals' | 'security' | 'audit';

interface DialogOpts {
  title: string;
  message?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  danger?: boolean;
  input?: boolean;
  inputLabel?: string;
  inputValue?: string;
  inputPlaceholder?: string;
  inputRequired?: boolean;
}
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

type NavItem = { id: Tab; label: string; icon: ElementType; badge?: 'appeals' };

const navGroups: Array<{ heading: string; items: NavItem[] }> = [
  { heading: 'Ringkasan', items: [{ id: 'dashboard', label: 'Dashboard', icon: Activity }] },
  {
    heading: 'Pengguna & Moderasi',
    items: [
      { id: 'users', label: 'Pengguna', icon: Users },
      { id: 'appeals', label: 'Banding', icon: Gavel, badge: 'appeals' },
      { id: 'admins', label: 'Admin', icon: ShieldCheck },
    ],
  },
  {
    heading: 'Konten & Pesan',
    items: [
      { id: 'content', label: 'Konten', icon: FileText },
      { id: 'moderation', label: 'Moderasi', icon: ShieldAlert },
      { id: 'chats', label: 'Direct Message', icon: MessageSquare },
    ],
  },
  { heading: 'Meeting', items: [{ id: 'meet', label: 'Meet Rooms', icon: Video }] },
  {
    heading: 'Kebijakan',
    items: [{ id: 'policies', label: 'Popup Kebijakan', icon: ScrollText }],
  },
  {
    heading: 'Sistem',
    items: [
      { id: 'security', label: 'Keamanan', icon: Lock },
      { id: 'audit', label: 'Log', icon: ClipboardList },
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
  const [accountOpen, setAccountOpen] = useState(false);
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
  const [detailUser, setDetailUser] = useState<any | null>(null);
  const [drill, setDrill] = useState<{ title: string; type: string; rows: any[] } | null>(null);
  const [dialog, setDialog] = useState<DialogOpts | null>(null);
  const dialogResolver = useRef<((v: string | null) => void) | null>(null);
  const [chatMode, setChatMode] = useState<ChatMode>('direct');
  const [chatRows, setChatRows] = useState<any[]>([]);
  const [chatSearch, setChatSearch] = useState('');
  const [chatGroup, setChatGroup] = useState<{ id: number; name: string } | null>(null);
  const [contentMode, setContentMode] = useState<ContentMode>('posts');
  const [contentRows, setContentRows] = useState<any[]>([]);
  const [contentSearch, setContentSearch] = useState('');
  const [moderationRows, setModerationRows] = useState<any[]>([]);
  const [policiesRows, setPoliciesRows] = useState<any[]>([]);
  const [editingPolicy, setEditingPolicy] = useState<any | 'new' | null>(null);
  const [previewPolicy, setPreviewPolicy] = useState<any | null>(null);
  const [acceptancePolicy, setAcceptancePolicy] = useState<any | null>(null);
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
  const [appeals, setAppeals] = useState<any[]>([]);
  const [appealStatus, setAppealStatus] = useState('pending');
  const [securityBlocks, setSecurityBlocks] = useState<any[]>([]);

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

  // Dialog modern berbasis promise — pengganti window.confirm/prompt.
  // Resolusi: null = batal, string = konfirmasi (isi input, '' untuk konfirmasi biasa).
  function askDialog(opts: DialogOpts): Promise<string | null> {
    return new Promise((resolve) => {
      dialogResolver.current = resolve;
      setDialog(opts);
    });
  }
  function resolveDialog(value: string | null) {
    dialogResolver.current?.(value);
    dialogResolver.current = null;
    setDialog(null);
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
    if (chatMode === 'group' && chatGroup) query.set('group_id', String(chatGroup.id));
    const path = chatMode === 'direct' ? '/admin-panel/direct-messages' : '/admin-panel/group-messages';
    const page = await portal<PageResult<any>>(`${path}?${query}`);
    setChatRows(page.data || []);
  }

  async function loadContent() {
    const query = new URLSearchParams({ per_page: '50' });
    if (contentSearch.trim()) query.set('search', contentSearch.trim());
    const page = await portal<PageResult<any>>(`/admin-panel/${contentMode}?${query}`);
    setContentRows(page.data || []);
  }

  async function loadModeration() {
    const res = await portal<{ data: any[] }>(`/moderation/posts?per_page=50`);
    setModerationRows(res.data || []);
  }

  async function cancelModeration(postId: number) {
    const ok = await askDialog({
      title: 'Batalkan moderasi?',
      message: 'Postingan akan dipulihkan dan kembali tampil di aplikasi.',
      confirmLabel: 'Batalkan moderasi'
    });
    if (ok === null) return;
    await run('Moderasi dibatalkan', async () => {
      await portal(`/posts/${postId}/moderation/cancel`, { method: 'POST' });
      await loadModeration();
    });
  }

  async function loadPolicies() {
    const res = await portal<{ policies: any[] }>(`/admin-panel/policies`);
    setPoliciesRows(res.policies || []);
  }

  async function savePolicy(draft: any) {
    await run(draft.id ? 'Kebijakan diperbarui' : 'Kebijakan dibuat', async () => {
      if (draft.id) {
        await portal(`/admin-panel/policies/${draft.id}`, { method: 'PUT', body: JSON.stringify(draft) });
      } else {
        await portal(`/admin-panel/policies`, { method: 'POST', body: JSON.stringify(draft) });
      }
      setEditingPolicy(null);
      await loadPolicies();
    });
  }

  async function deletePolicy(id: number) {
    const ok = await askDialog({
      title: 'Hapus kebijakan?',
      message: 'Kebijakan dan seluruh riwayat persetujuannya akan dihapus permanen.',
      confirmLabel: 'Hapus',
      danger: true,
    });
    if (ok === null) return;
    await run('Kebijakan dihapus', async () => {
      await portal(`/admin-panel/policies/${id}`, { method: 'DELETE' });
      await loadPolicies();
    });
  }

  async function togglePolicy(id: number) {
    await run('', async () => {
      await portal(`/admin-panel/policies/${id}/toggle`, { method: 'POST' });
      await loadPolicies();
    });
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

  async function loadAppeals() {
    const query = new URLSearchParams({ per_page: '50' });
    if (appealStatus) query.set('status', appealStatus);
    const page = await portal<PageResult<any>>(`/admin-panel/appeals?${query}`);
    setAppeals(page.data || []);
  }

  async function loadSecurity() {
    const data = await portal<{ blocks: any[] }>('/admin-panel/security/ip-blocks');
    setSecurityBlocks(data.blocks || []);
  }

  async function clearIpBlock(ip: string) {
    const ok = await askDialog({
      title: `Buka blokir IP ${ip}`,
      message: 'IP ini akan bisa mencoba login kembali.',
      confirmLabel: 'Buka blokir',
    });
    if (ok === null) return;
    await run('Blokir IP dibuka.', async () => {
      await portal('/admin-panel/security/ip-blocks/clear', { method: 'POST', body: JSON.stringify({ ip }) });
      await loadSecurity();
    });
  }

  async function resolveAppeal(appeal: any, decision: 'approved' | 'rejected') {
    const response = await askDialog({
      title: decision === 'approved' ? 'Setujui banding' : 'Tolak banding',
      message: decision === 'approved'
        ? 'Menyetujui akan otomatis membuka blokir user.'
        : 'Banding akan ditolak dan user tetap diblokir.',
      confirmLabel: decision === 'approved' ? 'Setujui & buka blokir' : 'Tolak banding',
      danger: decision === 'rejected',
      input: true,
      inputLabel: 'Catatan untuk user (opsional)',
      inputPlaceholder: 'Tulis tanggapan admin…',
    });
    if (response === null) return;
    await run(decision === 'approved' ? 'Banding disetujui, blokir dibuka.' : 'Banding ditolak.', async () => {
      await portal(`/admin-panel/appeals/${appeal.appeal_id}/resolve`, {
        method: 'POST',
        body: JSON.stringify({ decision, admin_response: response.trim() || undefined }),
      });
      await loadAppeals();
    });
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
      if (tab === 'moderation') await loadModeration();
      if (tab === 'policies') await loadPolicies();
      if (tab === 'meet') await loadRooms();
      if (tab === 'appeals') await loadAppeals();
      if (tab === 'security') await loadSecurity();
      if (tab === 'audit') await loadAudit(true);
    });
  }

  useEffect(() => {
    if (!session) return;
    reloadCurrent();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session?.token, tab, chatMode, contentMode, appealStatus, chatGroup]);

  // Live search (debounce 300ms) untuk User.
  useEffect(() => {
    if (!session || tab !== 'users') return;
    const t = setTimeout(() => run('', loadUsers), 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userSearch]);

  // Live search (debounce 300ms) untuk Konten.
  useEffect(() => {
    if (!session || tab !== 'content') return;
    const t = setTimeout(() => run('', loadContent), 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [contentSearch]);

  // Live search (debounce 300ms) untuk Chat.
  useEffect(() => {
    if (!session || tab !== 'chats') return;
    const t = setTimeout(() => run('', loadChats), 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chatSearch]);

  // Auto-refresh ringan tab Banding & Keamanan (banding/IP baru muncul tanpa refresh manual).
  useEffect(() => {
    if (!session || (tab !== 'appeals' && tab !== 'security')) return;
    const t = setInterval(() => {
      if (tab === 'appeals') loadAppeals().catch(() => undefined);
      if (tab === 'security') loadSecurity().catch(() => undefined);
    }, 12000);
    return () => clearInterval(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab, session?.token, appealStatus]);

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
    const reason = await askDialog({
      title: `Blokir @${user.username}`,
      message: 'User tetap bisa login, tetapi fiturnya dibatasi dan bisa mengajukan banding.',
      confirmLabel: 'Blokir user',
      danger: true,
      input: true,
      inputLabel: 'Alasan blokir',
      inputValue: user.ban_reason || '',
      inputPlaceholder: 'Jelaskan alasan pemblokiran…',
    });
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
    const ok = await askDialog({
      title: `Buka blokir @${user.username}`,
      message: 'User dapat mengakses semua fitur kembali.',
      confirmLabel: 'Buka blokir',
    });
    if (ok === null) return;
    await run('Blokir user dibuka.', async () => {
      await portal(`/admin-panel/users/${user.user_id}/unban`, { method: 'POST' });
      await loadUsers();
    });
  }

  async function deleteUser(user: PortalUser) {
    const ok = await askDialog({
      title: `Hapus @${user.username}`,
      message: 'Akun dan seluruh datanya dihapus permanen. Tindakan ini tidak dapat dibatalkan.',
      confirmLabel: 'Hapus permanen',
      danger: true,
    });
    if (ok === null) return;
    await run('User dihapus.', async () => {
      await portal(`/admin-panel/users/${user.user_id}`, {
        method: 'DELETE',
        body: JSON.stringify({ mode: 'force', reason: 'Dihapus dari admin panel.' }),
      });
      await loadUsers();
    });
  }

  async function openUserDetail(user: PortalUser) {
    await run('', async () => {
      const data = await portal<any>(`/admin-panel/users/${user.user_id}`);
      setDetailUser(data);
    });
  }

  // Buka chat sebuah grup di tab Chat (mode group, terfilter group_id) — mendukung edit/hapus.
  function viewGroupChat(group: any) {
    setChatGroup({ id: group.id, name: group.name || `Grup #${group.id}` });
    setChatMode('group');
    setTab('chats');
  }

  // Drill-down: buka data di balik statistik user (post, komentar, story, DM, grup).
  async function openDrill(userId: number, key: string) {
    const map: Record<string, { path: string; title: string }> = {
      posts: { path: `/admin-panel/posts?user_id=${userId}&per_page=100`, title: 'Postingan' },
      comments: { path: `/admin-panel/comments?user_id=${userId}&per_page=100`, title: 'Komentar' },
      stories: { path: `/admin-panel/stories?user_id=${userId}&per_page=100`, title: 'Story' },
      sent_direct_messages: { path: `/admin-panel/direct-messages?sender_id=${userId}&per_page=100`, title: 'DM Terkirim' },
      received_direct_messages: { path: `/admin-panel/direct-messages?receiver_id=${userId}&per_page=100`, title: 'DM Diterima' },
      user_dms: { path: `/admin-panel/direct-messages?user_id=${userId}&per_page=200`, title: 'Semua Percakapan DM' },
      groups_owned: { path: `/admin-panel/groups?owner_id=${userId}&per_page=100`, title: 'Grup Dimiliki' },
    };
    const cfg = map[key];
    if (!cfg) return;
    await run('', async () => {
      const page = await portal<PageResult<any>>(cfg.path);
      setDrill({ title: cfg.title, type: key, rows: page.data || [] });
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
    const next = await askDialog({
      title: 'Ubah isi pesan',
      confirmLabel: 'Simpan',
      input: true,
      inputLabel: 'Isi pesan',
      inputValue: row.content || '',
    });
    if (next === null) return;
    await run('Pesan diperbarui.', async () => {
      const path = chatMode === 'direct' ? `/admin-panel/direct-messages/${id}` : `/admin-panel/group-messages/${id}`;
      await portal(path, { method: 'PATCH', body: JSON.stringify({ content: next }) });
      await loadChats();
    });
  }

  async function deleteMessage(row: any) {
    const id = chatMode === 'direct' ? row.message_id : row.id;
    const ok = await askDialog({
      title: chatMode === 'direct' ? 'Hapus direct message' : 'Sembunyikan group message',
      message: chatMode === 'direct' ? 'Pesan akan dihapus permanen.' : 'Pesan grup akan disembunyikan.',
      confirmLabel: chatMode === 'direct' ? 'Hapus' : 'Sembunyikan',
      danger: true,
    });
    if (ok === null) return;
    await run('Pesan diproses.', async () => {
      const path = chatMode === 'direct' ? `/admin-panel/direct-messages/${id}` : `/admin-panel/group-messages/${id}`;
      await portal(path, { method: 'DELETE' });
      await loadChats();
    });
  }

  async function editContent(row: any) {
    const id = row.post_id || row.comment_id || row.story_id || row.id;
    const field = contentMode === 'groups' ? 'name' : contentMode === 'posts' ? 'caption' : contentMode === 'comments' ? 'content' : 'caption';
    const next = await askDialog({
      title: `Ubah ${field}`,
      confirmLabel: 'Simpan',
      input: true,
      inputLabel: field,
      inputValue: row[field] || '',
    });
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
    const notifiable = contentMode === 'posts' || contentMode === 'comments' || contentMode === 'stories';
    let reason: string | null = null;
    if (notifiable) {
      reason = await askDialog({
        title: `Hapus ${contentMode} #${id}`,
        message: 'Alasan akan dikirim sebagai notifikasi ke pemilik konten.',
        confirmLabel: 'Hapus',
        danger: true,
        input: true,
        inputLabel: 'Alasan (opsional)',
        inputPlaceholder: 'Alasan penghapusan…',
      });
      if (reason === null) return; // dibatalkan
    } else {
      const ok = await askDialog({
        title: `Hapus ${contentMode} #${id}`,
        message: 'Item ini akan dihapus.',
        confirmLabel: 'Hapus',
        danger: true,
      });
      if (ok === null) return;
    }
    await run('Konten dihapus.', async () => {
      await portal(`/admin-panel/${contentMode}/${id}`, {
        method: 'DELETE',
        body: notifiable ? JSON.stringify({ reason: reason?.trim() || undefined }) : undefined,
      });
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
    const ok = await askDialog({
      title: `Hapus room ${roomId}`,
      message: 'Room dihapus dan sesi LiveKit aktif ditutup.',
      confirmLabel: 'Hapus room',
      danger: true,
    });
    if (ok === null) return;
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
    const ok = await askDialog({
      title: 'Keluarkan peserta',
      message: `Peserta ${identity} akan dikeluarkan dari room.`,
      confirmLabel: 'Keluarkan',
      danger: true,
    });
    if (ok === null) return;
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
                const count = item.badge === 'appeals' ? (appOverview?.moderation?.appeals_pending ?? 0) : 0;
                return (
                  <button
                    key={item.id}
                    className={tab === item.id ? 'nav-item active' : 'nav-item'}
                    onClick={() => selectTab(item.id)}
                    title={item.label}
                  >
                    <Icon size={18} />
                    <span>{item.label}</span>
                    {count > 0 && <span className="nav-badge">{count > 99 ? '99+' : count}</span>}
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
          <div className="topbar-actions">
            <button className="icon-button" onClick={reloadCurrent} disabled={loading} title="Refresh data">
              <RefreshCw size={18} className={loading ? 'spin' : ''} />
            </button>
            <div className="account">
              <button className="account-trigger" onClick={() => setAccountOpen(v => !v)} title="Akun">
                <span className="account-avatar">
                  {(session.user.full_name || session.user.username || '?').charAt(0).toUpperCase()}
                </span>
                <span className="account-name">{session.user.full_name || session.user.username}</span>
                <ChevronDown size={15} />
              </button>
              {accountOpen && (
                <>
                  <div className="account-overlay" onClick={() => setAccountOpen(false)} />
                  <div className="account-menu">
                    <button onClick={() => { setAccountOpen(false); openUserDetail(session.user); }}>
                      <Eye size={15} /> Profil
                    </button>
                    <button onClick={() => { setAccountOpen(false); setNotice('Pengaturan admin akan hadir.'); }}>
                      <Settings size={15} /> Pengaturan
                    </button>
                    <button className="danger" onClick={() => { setAccountOpen(false); logout(); }}>
                      <LogOut size={15} /> Logout
                    </button>
                  </div>
                </>
              )}
            </div>
          </div>
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
            onView={openUserDetail}
            onChat={(user) => openDrill(user.user_id, 'user_dms')}
            onEdit={setEditingUser}
            onBan={banUser}
            onUnban={unbanUser}
            onDelete={deleteUser}
          />
        )}
        {tab === 'chats' && (
          <ChatsPanel
            mode={chatMode}
            setMode={(m) => { setChatMode(m); if (m === 'direct') setChatGroup(null); }}
            rows={chatRows}
            search={chatSearch}
            setSearch={setChatSearch}
            group={chatMode === 'group' ? chatGroup : null}
            onClearGroup={() => setChatGroup(null)}
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
            search={contentSearch}
            setSearch={setContentSearch}
            onEdit={editContent}
            onDelete={deleteContent}
            onGroupChat={viewGroupChat}
          />
        )}
        {tab === 'moderation' && (
          <ModerationPanel
            rows={moderationRows}
            reload={() => run('', loadModeration)}
            onCancel={cancelModeration}
          />
        )}
        {tab === 'policies' && (
          <PoliciesPanel
            rows={policiesRows}
            reload={() => run('', loadPolicies)}
            onNew={() => setEditingPolicy('new')}
            onEdit={(p) => setEditingPolicy(p)}
            onDelete={deletePolicy}
            onToggle={togglePolicy}
            onPreview={(p) => setPreviewPolicy(p)}
            onAcceptances={(p) => setAcceptancePolicy(p)}
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
        {tab === 'appeals' && (
          <AppealsPanel
            appeals={appeals}
            status={appealStatus}
            setStatus={setAppealStatus}
            onResolve={resolveAppeal}
          />
        )}
        {tab === 'security' && <SecurityPanel blocks={securityBlocks} onClear={clearIpBlock} />}
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
      {editingPolicy && (
        <PolicyEditorModal
          policy={editingPolicy === 'new' ? null : editingPolicy}
          onClose={() => setEditingPolicy(null)}
          onSave={savePolicy}
          onPreview={(draft) => setPreviewPolicy(draft)}
        />
      )}
      {previewPolicy && (
        <PolicyPreviewModal policy={previewPolicy} onClose={() => setPreviewPolicy(null)} />
      )}
      {acceptancePolicy && (
        <PolicyAcceptancesModal
          policy={acceptancePolicy}
          fetcher={portal}
          onClose={() => setAcceptancePolicy(null)}
        />
      )}
      {detailUser && (
        <UserDetailModal
          data={detailUser}
          onClose={() => setDetailUser(null)}
          onEdit={(user) => { setDetailUser(null); setEditingUser(user); }}
          onDrill={(key) => openDrill(detailUser.user?.user_id, key)}
        />
      )}
      {drill && <DrillModal drill={drill} onClose={() => setDrill(null)} />}
      {dialog && (
        <AppDialog
          opts={dialog}
          onCancel={() => resolveDialog(null)}
          onConfirm={(value) => resolveDialog(value)}
        />
      )}
    </div>
  );
}

function AppDialog({ opts, onCancel, onConfirm }: { opts: DialogOpts; onCancel: () => void; onConfirm: (value: string) => void }) {
  const [value, setValue] = useState(opts.inputValue ?? '');

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') onCancel();
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onCancel]);

  function confirm() {
    if (opts.input && opts.inputRequired && !value.trim()) return;
    onConfirm(opts.input ? value : '');
  }

  return (
    <div className="dialog-backdrop" onClick={onCancel}>
      <div className="dialog" role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
        <h3 className="dialog-title">{opts.title}</h3>
        {opts.message && <p className="dialog-message">{opts.message}</p>}
        {opts.input && (
          <label className="dialog-field">
            {opts.inputLabel && <span>{opts.inputLabel}</span>}
            <textarea
              autoFocus
              rows={3}
              value={value}
              placeholder={opts.inputPlaceholder}
              onChange={(e) => setValue(e.target.value)}
            />
          </label>
        )}
        <div className="dialog-actions">
          <button className="secondary-button" onClick={onCancel}>{opts.cancelLabel ?? 'Batal'}</button>
          <button
            className={opts.danger ? 'danger-button' : 'primary-button'}
            onClick={confirm}
            disabled={opts.input && opts.inputRequired && !value.trim()}
          >
            {opts.confirmLabel ?? 'Konfirmasi'}
          </button>
        </div>
      </div>
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
  onView: (user: PortalUser) => void;
  onChat: (user: PortalUser) => void;
  onEdit: (user: PortalUser) => void;
  onBan: (user: PortalUser) => void;
  onUnban: (user: PortalUser) => void;
  onDelete: (user: PortalUser) => void;
}) {
  return (
    <section className="panel">
      <Header title="Manajemen Pengguna" subtitle="Lihat detail, edit, blokir, buka blokir, dan hapus akun. Pencarian langsung saat mengetik." />
      <Toolbar>
        <SearchBox value={props.userSearch} onChange={props.setUserSearch} onSubmit={props.loadUsers} placeholder="Cari username, nama, email (live)" />
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
                <IconAction title="Lihat detail" onClick={() => props.onView(user)} icon={Eye} />
                <IconAction title="Lihat percakapan DM" onClick={() => props.onChat(user)} icon={MessageSquare} />
                <IconAction title="Edit user" onClick={() => props.onEdit(user)} icon={Pencil} />
                {user.is_banned
                  ? <IconAction title="Unban user" onClick={() => props.onUnban(user)} icon={Unlock} />
                  : <IconAction title="Ban user" onClick={() => props.onBan(user)} icon={Ban} danger />}
                <IconAction title="Hapus user" onClick={() => props.onDelete(user)} icon={Trash2} danger />
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
  group: { id: number; name: string } | null;
  onClearGroup: () => void;
  loadChats: () => void;
  onEdit: (row: any) => void;
  onDelete: (row: any) => void;
}) {
  return (
    <section className="panel">
      <Header title="Manajemen Chat" subtitle="Lihat, cari, edit, dan hapus direct message maupun pesan grup." />
      <Toolbar>
        <Segmented value={props.mode} onChange={value => props.setMode(value as ChatMode)} options={[['direct', 'Direct'], ['group', 'Group']]} />
        <SearchBox value={props.search} onChange={props.setSearch} onSubmit={props.loadChats} placeholder="Cari isi pesan (live)" />
      </Toolbar>
      {props.group && (
        <div className="filter-chip">
          <MessageSquare size={14} />
          <span>Chat grup: <strong>{props.group.name}</strong></span>
          <button onClick={props.onClearGroup} title="Hapus filter grup"><X size={14} /></button>
        </div>
      )}
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
  search: string;
  setSearch: (value: string) => void;
  onEdit: (row: any) => void;
  onDelete: (row: any) => void;
  onGroupChat: (row: any) => void;
}) {
  const placeholder = props.mode === 'posts'
    ? 'Cari caption post (live)'
    : props.mode === 'comments'
      ? 'Cari isi komentar (live)'
      : props.mode === 'stories'
        ? 'Cari caption story (live)'
        : 'Cari nama/deskripsi grup (live)';
  return (
    <section className="panel">
      <Header title="Moderasi Konten" subtitle="Post, komentar, story, dan grup. Pencarian langsung berdasarkan isi konten." />
      <Toolbar>
        <Segmented value={props.mode} onChange={value => props.setMode(value as ContentMode)} options={[
          ['posts', 'Posts'],
          ['comments', 'Comments'],
          ['stories', 'Stories'],
          ['groups', 'Groups'],
        ]} />
        <SearchBox value={props.search} onChange={props.setSearch} onSubmit={() => { /* live */ }} placeholder={placeholder} />
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
                  {props.mode === 'groups' && (
                    <IconAction title="Lihat chat grup" onClick={() => props.onGroupChat(row)} icon={MessageSquare} />
                  )}
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

function AppealsPanel(props: {
  appeals: any[];
  status: string;
  setStatus: (value: string) => void;
  onResolve: (appeal: any, decision: 'approved' | 'rejected') => void;
}) {
  return (
    <section className="panel">
      <Header title="Banding Akun" subtitle="Peninjauan banding dari user yang diblokir. Menyetujui akan otomatis membuka blokir." />
      <Toolbar>
        <Segmented
          value={props.status}
          onChange={props.setStatus}
          options={[['pending', 'Menunggu'], ['approved', 'Disetujui'], ['rejected', 'Ditolak'], ['', 'Semua']]}
        />
      </Toolbar>
      <Table>
        <thead><tr><th>ID</th><th>User</th><th>Pesan Banding</th><th>Status</th><th>Diajukan</th><th>Aksi</th></tr></thead>
        <tbody>
          {props.appeals.map(a => (
            <tr key={a.appeal_id}>
              <td>{a.appeal_id}</td>
              <td>{a.user?.username || a.user_id}</td>
              <td className="wide-cell">
                {short(a.message, 160)}
                {a.admin_response ? <span className="detail-sub"> · admin: {a.admin_response}</span> : null}
              </td>
              <td><Badge ok={a.status === 'approved'} danger={a.status === 'rejected'}>{a.status}</Badge></td>
              <td>{fmt(a.created_at)}</td>
              <td><ActionBar>
                {a.status === 'pending' ? (
                  <>
                    <IconAction title="Setujui — buka blokir" icon={ShieldCheck} onClick={() => props.onResolve(a, 'approved')} />
                    <IconAction title="Tolak banding" icon={ShieldAlert} danger onClick={() => props.onResolve(a, 'rejected')} />
                  </>
                ) : (
                  <span className="detail-sub">ditinjau {a.reviewer?.username ? `oleh ${a.reviewer.username}` : ''}</span>
                )}
              </ActionBar></td>
            </tr>
          ))}
          {props.appeals.length === 0 && (
            <tr><td colSpan={6} className="empty-cell">Tidak ada banding.</td></tr>
          )}
        </tbody>
      </Table>
    </section>
  );
}

function ModerationPanel(props: {
  rows: any[];
  reload: () => void;
  onCancel: (postId: number) => void;
}) {
  return (
    <section className="panel">
      <Header
        title="Moderasi Postingan"
        subtitle="Postingan yang diturunkan (soft take-down). Tersimpan ~30 hari lalu dihapus permanen otomatis. Batalkan untuk memulihkan."
        action={
          <button className="secondary-button" onClick={props.reload}>
            <RefreshCw size={16} /> Muat ulang
          </button>
        }
      />
      <Table>
        <thead>
          <tr>
            <th>Postingan</th>
            <th>Pemilik</th>
            <th>Alasan</th>
            <th>Moderator</th>
            <th>Dimoderasi</th>
            <th>Sisa retensi</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          {props.rows.map((r) => (
            <tr key={r.post_id}>
              <td>
                <a href={`https://app.portalsi.com/posts/${r.post_id}`} target="_blank" rel="noreferrer" className="mod-thumb-link">
                  {r.thumbnail_url || r.media_url
                    ? <img src={r.thumbnail_url || r.media_url} alt="" className="mod-thumb" />
                    : <span className="mod-thumb placeholder" />}
                  <span className="detail-sub">#{r.post_id}{r.is_video ? ' · video' : ''}</span>
                </a>
              </td>
              <td>
                {r.owner
                  ? <div className="user-cell"><strong>{r.owner.full_name || r.owner.username}</strong><span>@{r.owner.username}</span></div>
                  : '-'}
              </td>
              <td className="mod-reason">{r.reason || '-'}</td>
              <td>{r.moderated_by ? `@${r.moderated_by.username}` : '-'}</td>
              <td>{fmt(r.moderated_at)}</td>
              <td>
                {typeof r.days_left === 'number'
                  ? <Badge danger={r.days_left <= 3}>{r.days_left} hari lagi</Badge>
                  : '-'}
              </td>
              <td>
                <ActionBar>
                  <IconAction title="Batalkan moderasi (pulihkan)" icon={RotateCcw} onClick={() => props.onCancel(r.post_id)} />
                </ActionBar>
              </td>
            </tr>
          ))}
          {props.rows.length === 0 && (
            <tr><td colSpan={7} className="empty-cell">Tidak ada postingan yang sedang dimoderasi.</td></tr>
          )}
        </tbody>
      </Table>
    </section>
  );
}

function SecurityPanel({ blocks, onClear }: { blocks: any[]; onClear: (ip: string) => void }) {
  return (
    <section className="panel">
      <Header
        title="Keamanan — Login per-IP"
        subtitle="IP dengan kegagalan login berlebih. Blokir bertingkat: 5 menit → 30 menit → 1 jam → 1 hari lalu reset. Daftar 3 akun/hari/IP."
      />
      <Table>
        <thead><tr><th>IP</th><th>Status</th><th>Akun yang Dicoba</th><th>Level</th><th>Total Gagal</th><th>Terakhir</th><th>Aksi</th></tr></thead>
        <tbody>
          {blocks.map(b => (
            <tr key={b.ip}>
              <td>{b.ip}</td>
              <td>
                {b.is_blocked
                  ? <Badge danger>terblokir s/d {fmt(b.blocked_until)}</Badge>
                  : <Badge>{b.fail_count}/10 gagal</Badge>}
              </td>
              <td>
                {b.target_user
                  ? <div className="user-cell"><strong>{b.target_user.full_name || b.target_user.username}</strong><span>@{b.target_user.username} · {b.target_user.email || '-'}{b.target_user.is_banned ? ' · banned' : ''}</span></div>
                  : (b.last_username || '-')}
                {b.last_app && <span className="detail-sub"> · via {b.last_app}</span>}
              </td>
              <td>{b.block_level}</td>
              <td>{b.total_failures}</td>
              <td>{fmt(b.updated_at)}</td>
              <td><ActionBar>
                {b.is_blocked && <IconAction title="Buka blokir IP" icon={Unlock} onClick={() => onClear(b.ip)} />}
              </ActionBar></td>
            </tr>
          ))}
          {blocks.length === 0 && (
            <tr><td colSpan={7} className="empty-cell">Belum ada IP bermasalah.</td></tr>
          )}
        </tbody>
      </Table>
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
      <Header title="Log Aktivitas" subtitle="Jejak seluruh aksi admin di Portal SI." />
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

function DrillModal({ drill, onClose }: { drill: { title: string; type: string; rows: any[] }; onClose: () => void }) {
  const { type, rows } = drill;
  const dmType = type === 'sent_direct_messages' || type === 'received_direct_messages';

  function headCells() {
    if (type === 'comments') return <tr><th>ID</th><th>Isi Komentar</th><th>Di Post</th><th>Waktu</th></tr>;
    if (type === 'user_dms') return <tr><th>ID</th><th>Dari</th><th>Ke</th><th>Isi</th><th>Waktu</th></tr>;
    if (type === 'sent_direct_messages') return <tr><th>ID</th><th>Ke</th><th>Isi</th><th>Waktu</th></tr>;
    if (type === 'received_direct_messages') return <tr><th>ID</th><th>Dari</th><th>Isi</th><th>Waktu</th></tr>;
    if (type === 'groups_owned') return <tr><th>ID</th><th>Nama Grup</th><th>Anggota</th></tr>;
    return <tr><th>ID</th><th>Konten</th><th>Waktu</th></tr>;
  }

  function rowCells(r: any) {
    if (type === 'comments') {
      return (
        <>
          <td>{r.comment_id || r.id}</td>
          <td className="wide-cell">{short(r.content, 120)}</td>
          <td>{r.post?.caption ? short(r.post.caption, 40) : (r.post_id ? `Post #${r.post_id}` : '-')}</td>
          <td>{fmt(r.created_at)}</td>
        </>
      );
    }
    if (type === 'user_dms') {
      return (
        <>
          <td>{r.message_id || r.id}</td>
          <td>{r.sender?.username || r.sender_id || '-'}</td>
          <td>{r.receiver?.username || r.receiver_id || '-'}</td>
          <td className="wide-cell">{short(r.content, 110)}</td>
          <td>{fmt(r.sent_at)}</td>
        </>
      );
    }
    if (dmType) {
      const other = type === 'sent_direct_messages' ? r.receiver : r.sender;
      return (
        <>
          <td>{r.message_id || r.id}</td>
          <td>{other?.username || (type === 'sent_direct_messages' ? r.receiver_id : r.sender_id) || '-'}</td>
          <td className="wide-cell">{short(r.content, 120)}</td>
          <td>{fmt(r.sent_at)}</td>
        </>
      );
    }
    if (type === 'groups_owned') {
      return (
        <>
          <td>{r.id}</td>
          <td>{r.name || '-'}</td>
          <td>{r.members_count ?? r.members ?? '-'}</td>
        </>
      );
    }
    // posts / stories
    return (
      <>
        <td>{r.post_id || r.story_id || r.id}</td>
        <td className="wide-cell">{short(r.caption, 140)}</td>
        <td>{fmt(r.created_at)}</td>
      </>
    );
  }

  return (
    <div className="modal-backdrop drill-backdrop" onClick={onClose}>
      <div className="modal detail-modal" onClick={e => e.stopPropagation()}>
        <div className="detail-modal-head">
          <h2>{drill.title} <span className="detail-sub">({rows.length})</span></h2>
          <button className="ghost-button" onClick={onClose}><X size={16} /></button>
        </div>
        <Table>
          <thead>{headCells()}</thead>
          <tbody>
            {rows.map((r, i) => <tr key={r.id ?? r.post_id ?? r.comment_id ?? r.story_id ?? r.message_id ?? i}>{rowCells(r)}</tr>)}
            {rows.length === 0 && <tr><td colSpan={4} className="empty-cell">Tidak ada data.</td></tr>}
          </tbody>
        </Table>
        <div className="modal-actions">
          <button className="ghost-button" onClick={onClose}><X size={16} /> Tutup</button>
        </div>
      </div>
    </div>
  );
}

function UserDetailModal({ data, onClose, onEdit, onDrill }: { data: any; onClose: () => void; onEdit: (user: PortalUser) => void; onDrill: (key: string) => void }) {
  const user: PortalUser = data.user || {};
  const stats: Record<string, number> = data.stats || {};
  const statLabels: Record<string, string> = {
    posts: 'Posts',
    comments: 'Comments',
    stories: 'Stories',
    sent_direct_messages: 'DM Terkirim',
    received_direct_messages: 'DM Diterima',
    group_messages: 'Pesan Grup',
    groups_owned: 'Grup Dimiliki',
    groups_joined: 'Grup Diikuti',
    followers: 'Followers',
    following: 'Following',
  };
  const drillable = new Set(['posts', 'comments', 'stories', 'sent_direct_messages', 'received_direct_messages', 'groups_owned']);
  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className="modal detail-modal" onClick={e => e.stopPropagation()}>
        <div className="detail-modal-head">
          <div>
            <h2>{user.full_name || user.username}</h2>
            <p className="detail-sub">@{user.username} · ID {user.user_id} {user.email ? `· ${user.email}` : ''}</p>
            <div className="badges">
              <Badge ok={user.is_verified} danger={!user.is_verified}>{user.is_verified ? 'admin/verified' : 'belum verified'}</Badge>
              {user.is_banned && <Badge danger>banned</Badge>}
              {user.is_private && <Badge>private</Badge>}
              <Badge>{user.role || 'no role'}</Badge>
            </div>
          </div>
          <div className="detail-modal-actions">
            <button className="secondary-button" onClick={() => onEdit(user)}><Pencil size={16} /> Edit</button>
            <button className="ghost-button" onClick={onClose}><X size={16} /></button>
          </div>
        </div>

        {user.bio && <p className="detail-bio">{user.bio}</p>}
        {user.ban_reason && <p className="detail-warn">Alasan blokir: {user.ban_reason}</p>}
        {user.admin_notes && <p className="detail-note">Catatan admin: {user.admin_notes}</p>}
        <p className="detail-sub">
          Dibuat {fmt(user.created_at)} · Aktivitas terakhir {fmt(user.last_activity)}
        </p>

        <h3>Statistik <span className="stat-hint">(klik untuk telusuri)</span></h3>
        <div className="stat-grid">
          {Object.entries(statLabels).map(([key, label]) => {
            const canDrill = drillable.has(key) && (stats[key] ?? 0) > 0;
            return (
              <button
                type="button"
                className={canDrill ? 'stat-cell drillable' : 'stat-cell'}
                key={key}
                disabled={!canDrill}
                onClick={() => canDrill && onDrill(key)}
              >
                <span>{label}</span>
                <strong>{stats[key] ?? 0}</strong>
              </button>
            );
          })}
        </div>

        <h3>Riwayat Login Terakhir</h3>
        <Table>
          <thead><tr><th>Waktu</th><th>IP</th><th>Perangkat</th></tr></thead>
          <tbody>
            {(data.recent_login_histories || []).map((row: any) => (
              <tr key={row.id}>
                <td>{fmt(row.login_at)}</td>
                <td>{row.ip_address || '-'}</td>
                <td className="wide-cell">{short(row.user_agent, 60)}</td>
              </tr>
            ))}
            {(data.recent_login_histories || []).length === 0 && (
              <tr><td colSpan={3} className="empty-cell">Tidak ada riwayat.</td></tr>
            )}
          </tbody>
        </Table>

        <div className="modal-actions">
          <button type="button" className="ghost-button" onClick={onClose}><X size={16} /> Tutup</button>
        </div>
      </div>
    </div>
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

function Header({ title, subtitle, action }: { title: string; subtitle?: string; action?: ReactNode }) {
  return (
    <div className="section-header">
      <div><h2>{title}</h2>{subtitle && <p>{subtitle}</p>}</div>
      {action}
    </div>
  );
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

// ══════════════════════════════════════════════════════════
// POPUP KEBIJAKAN
// ══════════════════════════════════════════════════════════

type PolicySlide = { title: string; body: string; image_url: string };

function emptyPolicyDraft() {
  return {
    id: null as number | null,
    title: '',
    description: '',
    slides: [{ title: '', body: '', image_url: '' }] as PolicySlide[],
    read_seconds: 5,
    require_agreement: true,
    agreement_text: '',
    is_active: false,
  };
}

function PoliciesPanel(props: {
  rows: any[];
  reload: () => void;
  onNew: () => void;
  onEdit: (p: any) => void;
  onDelete: (id: number) => void;
  onToggle: (id: number) => void;
  onPreview: (p: any) => void;
  onAcceptances: (p: any) => void;
}) {
  return (
    <section className="panel">
      <Header
        title="Manajemen Popup Kebijakan"
        subtitle="Buat kebijakan dinamis yang muncul saat pengguna login. Atur slide, gambar, durasi wajib-baca, dan pantau siapa yang sudah menyetujui."
        action={
          <div className="header-actions">
            <button className="secondary-button" onClick={props.reload}><RefreshCw size={16} /> Muat ulang</button>
            <button className="primary-button" onClick={props.onNew}><Plus size={16} /> Buat kebijakan</button>
          </div>
        }
      />
      <Table>
        <thead>
          <tr>
            <th>Kebijakan</th>
            <th>Status</th>
            <th>Halaman</th>
            <th>Wajib baca</th>
            <th>Persetujuan</th>
            <th>Versi</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          {props.rows.map((p) => (
            <tr key={p.id}>
              <td><div className="user-cell"><strong>{p.title}</strong><span>{short(p.description, 60)}</span></div></td>
              <td>{p.is_active ? <Badge ok>Aktif</Badge> : <Badge>Nonaktif</Badge>}</td>
              <td>{(p.slides?.length ?? 0)} halaman</td>
              <td>{p.read_seconds}s</td>
              <td>
                <button className="link-button" onClick={() => props.onAcceptances(p)}>
                  {p.accepted_count ?? 0}/{p.total_users ?? 0} setuju
                </button>
              </td>
              <td>v{p.version}</td>
              <td>
                <ActionBar>
                  <IconAction title="Preview" icon={Eye} onClick={() => props.onPreview(p)} />
                  <IconAction title="Riwayat persetujuan" icon={ListChecks} onClick={() => props.onAcceptances(p)} />
                  <IconAction title="Edit" icon={Pencil} onClick={() => props.onEdit(p)} />
                  <IconAction title={p.is_active ? 'Nonaktifkan' : 'Aktifkan'} icon={Power} onClick={() => props.onToggle(p.id)} />
                  <IconAction title="Hapus" icon={Trash2} danger onClick={() => props.onDelete(p.id)} />
                </ActionBar>
              </td>
            </tr>
          ))}
          {props.rows.length === 0 && (
            <tr><td colSpan={7} className="empty-cell">Belum ada kebijakan. Klik "Buat kebijakan" untuk memulai.</td></tr>
          )}
        </tbody>
      </Table>
    </section>
  );
}

function PolicyEditorModal(props: {
  policy: any | null;
  onClose: () => void;
  onSave: (draft: any) => void;
  onPreview: (draft: any) => void;
}) {
  const [draft, setDraft] = useState<any>(() => {
    if (!props.policy) return emptyPolicyDraft();
    return {
      id: props.policy.id,
      title: props.policy.title || '',
      description: props.policy.description || '',
      slides: (props.policy.slides?.length ? props.policy.slides : [{ title: '', body: '', image_url: '' }]).map((s: any) => ({
        title: s.title || '', body: s.body || '', image_url: s.image_url || '',
      })),
      read_seconds: props.policy.read_seconds ?? 5,
      require_agreement: props.policy.require_agreement ?? true,
      agreement_text: props.policy.agreement_text || '',
      is_active: props.policy.is_active ?? false,
    };
  });

  function updateSlide(i: number, patch: Partial<PolicySlide>) {
    setDraft({ ...draft, slides: draft.slides.map((s: PolicySlide, idx: number) => idx === i ? { ...s, ...patch } : s) });
  }
  function addSlide() {
    setDraft({ ...draft, slides: [...draft.slides, { title: '', body: '', image_url: '' }] });
  }
  function removeSlide(i: number) {
    if (draft.slides.length <= 1) return;
    setDraft({ ...draft, slides: draft.slides.filter((_: PolicySlide, idx: number) => idx !== i) });
  }
  function moveSlide(i: number, dir: -1 | 1) {
    const j = i + dir;
    if (j < 0 || j >= draft.slides.length) return;
    const next = [...draft.slides];
    [next[i], next[j]] = [next[j], next[i]];
    setDraft({ ...draft, slides: next });
  }

  function submit(e: FormEvent) {
    e.preventDefault();
    props.onSave(draft);
  }

  return (
    <div className="modal-backdrop">
      <form className="modal policy-editor" onSubmit={submit}>
        <Header title={draft.id ? 'Edit kebijakan' : 'Buat kebijakan'} subtitle="Konten disimpan sebagai slide. Ubah, tambah, atau hapus tanpa mengubah kode." />
        <div className="form-grid">
          <label className="span-2">Judul kebijakan<input value={draft.title} onChange={e => setDraft({ ...draft, title: e.target.value })} placeholder="mis. Kebijakan Komunitas 2026" required /></label>
          <label className="span-2">Deskripsi singkat<textarea value={draft.description} onChange={e => setDraft({ ...draft, description: e.target.value })} placeholder="Ringkasan opsional" /></label>
          <label>Wajib baca (detik)<input type="number" min={0} max={120} value={draft.read_seconds} onChange={e => setDraft({ ...draft, read_seconds: Number(e.target.value) })} /></label>
          <label className="check"><input type="checkbox" checked={Boolean(draft.require_agreement)} onChange={e => setDraft({ ...draft, require_agreement: e.target.checked })} /> Wajib centang persetujuan</label>
          <label className="check"><input type="checkbox" checked={Boolean(draft.is_active)} onChange={e => setDraft({ ...draft, is_active: e.target.checked })} /> Aktifkan sekarang</label>
          <label className="span-2">Teks pernyataan persetujuan<textarea value={draft.agreement_text} onChange={e => setDraft({ ...draft, agreement_text: e.target.value })} placeholder="Saya telah membaca dan memahami kebijakan platform…" /></label>
        </div>

        <div className="policy-slides">
          <div className="policy-slides-head"><h3>Halaman / Slide</h3><button type="button" className="secondary-button" onClick={addSlide}><Plus size={15} /> Tambah halaman</button></div>
          {draft.slides.map((s: PolicySlide, i: number) => (
            <div className="policy-slide-card" key={i}>
              <div className="policy-slide-top">
                <strong>Halaman {i + 1}</strong>
                <div className="policy-slide-tools">
                  <button type="button" className="icon-action" title="Naik" onClick={() => moveSlide(i, -1)}><ChevronsLeft size={15} style={{ transform: 'rotate(90deg)' }} /></button>
                  <button type="button" className="icon-action" title="Turun" onClick={() => moveSlide(i, 1)}><ChevronsRight size={15} style={{ transform: 'rotate(90deg)' }} /></button>
                  <button type="button" className="icon-action danger" title="Hapus halaman" onClick={() => removeSlide(i)}><Trash2 size={15} /></button>
                </div>
              </div>
              <label>Judul halaman<input value={s.title} onChange={e => updateSlide(i, { title: e.target.value })} placeholder="Judul slide" /></label>
              <label>Isi<textarea value={s.body} onChange={e => updateSlide(i, { body: e.target.value })} placeholder="Isi teks (baris baru didukung)" /></label>
              <label><ImageIcon size={13} /> URL gambar / banner<input value={s.image_url} onChange={e => updateSlide(i, { image_url: e.target.value })} placeholder="https://…" /></label>
              {s.image_url ? <img className="policy-slide-thumb" src={s.image_url} alt="" /> : null}
            </div>
          ))}
        </div>

        <div className="modal-actions">
          <button type="button" className="ghost-button" onClick={props.onClose}><X size={16} /> Batal</button>
          <button type="button" className="secondary-button" onClick={() => props.onPreview(draft)}><Eye size={16} /> Preview</button>
          <button className="primary-button"><Save size={16} /> Simpan</button>
        </div>
      </form>
    </div>
  );
}

// Tampilan modal SAMA PERSIS dengan yang diterima pengguna (untuk preview admin).
function PolicyModalView({ policy }: { policy: any }) {
  const slides: PolicySlide[] = (policy.slides?.length ? policy.slides : [{ title: policy.title, body: policy.description, image_url: '' }]);
  const [slide, setSlide] = useState(0);
  const [secondsLeft, setSecondsLeft] = useState(policy.read_seconds ?? 0);
  const [agreed, setAgreed] = useState(false);
  const isLast = slide >= slides.length - 1;

  useEffect(() => {
    setSecondsLeft(policy.read_seconds ?? 0);
    const id = setInterval(() => setSecondsLeft((s: number) => Math.max(0, s - 1)), 1000);
    return () => clearInterval(id);
  }, [policy.read_seconds]);

  const s = slides[slide] || { title: '', body: '', image_url: '' };
  const canContinue = isLast && secondsLeft <= 0 && (!policy.require_agreement || agreed);

  return (
    <div className="pgv-card" onClick={e => e.stopPropagation()}>
      {s.image_url
        ? <div className="pgv-hero"><img src={s.image_url} alt="" /></div>
        : <div className="pgv-hero pgv-hero-fallback"><ShieldCheck size={44} /></div>}
      <div className="pgv-body">
        {slides.length > 1 && (
          <div className="pgv-dots">{slides.map((_, i) => <span key={i} className={i === slide ? 'on' : ''} />)}</div>
        )}
        {s.title && <h2>{s.title}</h2>}
        {s.body && <p className="pgv-text">{s.body}</p>}
      </div>
      <div className="pgv-foot">
        {!isLast ? (
          <div className="pgv-nav">
            <button className="ghost-button" disabled={slide === 0} onClick={() => setSlide(slide - 1)}>Kembali</button>
            <button className="primary-button" onClick={() => setSlide(slide + 1)}>Lanjut</button>
          </div>
        ) : (
          <>
            {policy.require_agreement && (
              <label className="pgv-agree">
                <input type="checkbox" checked={agreed} onChange={e => setAgreed(e.target.checked)} />
                <span>{policy.agreement_text || 'Saya telah membaca dan memahami kebijakan platform, serta siap menerima konsekuensi berupa moderasi konten maupun pembatasan/pemblokiran akun apabila melanggar syarat dan ketentuan yang berlaku.'}</span>
              </label>
            )}
            <div className="pgv-nav">
              {slides.length > 1 && <button className="ghost-button" onClick={() => setSlide(slide - 1)}>Kembali</button>}
              <button className="primary-button" disabled={!canContinue}>
                {secondsLeft > 0 ? `Baca dulu (${secondsLeft}s)` : 'Setuju & lanjutkan'}
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function PolicyPreviewModal({ policy, onClose }: { policy: any; onClose: () => void }) {
  return (
    <div className="modal-backdrop pgv-backdrop" onClick={onClose}>
      <div className="pgv-preview-wrap" onClick={e => e.stopPropagation()}>
        <div className="pgv-preview-label"><Eye size={14} /> Preview — persis seperti yang dilihat pengguna</div>
        <PolicyModalView policy={policy} />
        <button className="secondary-button pgv-close" onClick={onClose}><X size={15} /> Tutup preview</button>
      </div>
    </div>
  );
}

function PolicyAcceptancesModal(props: {
  policy: any;
  fetcher: <T,>(path: string, init?: RequestInit) => Promise<T>;
  onClose: () => void;
}) {
  const [status, setStatus] = useState<'accepted' | 'pending'>('accepted');
  const [rows, setRows] = useState<any[]>([]);
  const [meta, setMeta] = useState<{ total: number }>({ total: 0 });
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let alive = true;
    setBusy(true);
    props.fetcher<any>(`/admin-panel/policies/${props.policy.id}/acceptances?status=${status}&per_page=50`)
      .then((res) => { if (alive) { setRows(res.data || []); setMeta({ total: res.total || 0 }); } })
      .catch(() => { if (alive) { setRows([]); setMeta({ total: 0 }); } })
      .finally(() => { if (alive) setBusy(false); });
    return () => { alive = false; };
  }, [status, props.policy.id]);

  return (
    <div className="modal-backdrop" onClick={props.onClose}>
      <div className="modal detail-modal" onClick={e => e.stopPropagation()}>
        <Header title={`Persetujuan — ${props.policy.title}`} subtitle={`Versi v${props.policy.version}. Total ${meta.total} pengguna ${status === 'accepted' ? 'sudah menyetujui' : 'belum menyetujui'}.`} />
        <div className="seg-tabs">
          <button className={status === 'accepted' ? 'on' : ''} onClick={() => setStatus('accepted')}>Sudah setuju</button>
          <button className={status === 'pending' ? 'on' : ''} onClick={() => setStatus('pending')}>Belum setuju</button>
        </div>
        <Table>
          <thead><tr><th>Pengguna</th><th>{status === 'accepted' ? 'Disetujui' : 'Status'}</th></tr></thead>
          <tbody>
            {rows.map((u) => (
              <tr key={u.user_id}>
                <td><div className="user-cell"><strong>{u.full_name || u.username}</strong><span>@{u.username}</span></div></td>
                <td>{status === 'accepted' ? fmt(u.accepted_at) : <Badge danger>Belum</Badge>}</td>
              </tr>
            ))}
            {!busy && rows.length === 0 && <tr><td colSpan={2} className="empty-cell">Tidak ada data.</td></tr>}
            {busy && <tr><td colSpan={2} className="empty-cell">Memuat…</td></tr>}
          </tbody>
        </Table>
        <div className="modal-actions">
          <button className="secondary-button" onClick={props.onClose}><X size={16} /> Tutup</button>
        </div>
      </div>
    </div>
  );
}
