import bcrypt from 'bcryptjs';
import redis from './redis';

export interface RoomPermissions {
  allowChat: boolean;
  allowScreenShare: boolean;
  allowJoin: boolean;
  allowReactions: boolean;
  lobbyMode: boolean;
  allowRename: boolean;
}

export interface RoomRecord {
  id: string;
  hostIdentity: string;
  hostPortalUserId?: number;
  hostUsername?: string;
  hostEmail?: string | null;
  passwordHash?: string;
  lobby: boolean;
  createdAt: number;
  updatedAt?: number;
  hostName: string;
  permissions: RoomPermissions;
  scheduledFor?: number;
  mode?: 'instant' | 'later' | 'schedule';
  /** Waktu (ms) room otomatis kedaluwarsa / URL tidak valid. */
  expiresAt?: number;
  adminLocked?: boolean;
  moderatorNotes?: string | null;
  lastModeratedAt?: number;
  lastModeratedBy?: {
    userId: number;
    username?: string | null;
    fullName?: string | null;
  };
}

export interface WaitingUser {
  waitingId: string;
  name: string;
  ts: number;
}

export interface RoomAuditEvent {
  action: string;
  actorUserId: number;
  actorUsername?: string | null;
  actorName?: string | null;
  metadata?: Record<string, unknown>;
  ts: number;
}

export interface ObserverHandoff {
  roomId: string;
  token: string;
  wsUrl: string | undefined;
  identity: string;
  name: string;
  isHost: boolean;
  moderator: true;
  adminUserId: number;
  createdAt: number;
}

const ROOM_TTL = 24 * 60 * 60;
const WAITING_TTL = 30 * 60; // 30 min
const ROOM_AUDIT_TTL = 7 * 24 * 60 * 60;
const OBSERVER_HANDOFF_TTL = 60;

// --- Lifecycle room (Sub-4) ---
const INSTANT_IDLE_TTL = 60 * 60;        // instan: 1 jam sebelum ada yang bergabung
const LATER_TTL = 24 * 60 * 60;          // buat-nanti: berlaku maks 24 jam
const SCHEDULED_VALID_TTL = 5 * 60 * 60; // terjadwal: 5 jam sejak waktu jadwal
const ACTIVE_TTL = 24 * 60 * 60;         // saat aktif: jaga hidup (di-refresh tiap ada aktivitas)
const EMPTY_GRACE_TTL = 60 * 60;         // setelah semua keluar: hitung mundur 1 jam lalu terhapus

/** TTL detik dari waktu kedaluwarsa (min 60 detik). */
function ttlFromExpiry(expiresAt: number): number {
  return Math.max(60, Math.ceil((expiresAt - Date.now()) / 1000));
}

function roomKey(id: string) { return `room:${id}`; }
function waitKey(id: string) { return `waiting:${id}`; }
function waitStatusKey(waitingId: string) { return `wstatus:${waitingId}`; }
function roomAuditKey(id: string) { return `roomaudit:${id}`; }
function observerHandoffKey(key: string) { return `adminhandoff:${key}`; }

export async function createRoom(opts: {
  id: string; hostIdentity: string; hostName: string; password?: string; lobby?: boolean; scheduledFor?: number;
  mode?: 'instant' | 'later' | 'schedule';
  hostPortalUserId?: number; hostUsername?: string; hostEmail?: string | null;
}): Promise<RoomRecord> {
  const now = Date.now();
  const mode: 'instant' | 'later' | 'schedule' = opts.mode ?? (opts.scheduledFor ? 'schedule' : 'instant');
  // Masa berlaku AWAL (sebelum ada peserta). Setelah ada aktivitas, diperpanjang via webhook.
  let expiresAt: number;
  if (mode === 'schedule' && opts.scheduledFor) expiresAt = opts.scheduledFor + SCHEDULED_VALID_TTL * 1000;
  else if (mode === 'later') expiresAt = now + LATER_TTL * 1000;
  else expiresAt = now + INSTANT_IDLE_TTL * 1000;

  const record: RoomRecord = {
    id: opts.id, hostIdentity: opts.hostIdentity, hostName: opts.hostName,
    hostPortalUserId: opts.hostPortalUserId, hostUsername: opts.hostUsername, hostEmail: opts.hostEmail,
    passwordHash: opts.password ? await bcrypt.hash(opts.password, 10) : undefined,
    lobby: opts.lobby ?? false, createdAt: now, scheduledFor: opts.scheduledFor, mode, expiresAt,
    permissions: { allowChat: true, allowScreenShare: true, allowJoin: true, allowReactions: true, lobbyMode: false, allowRename: true },
  };
  await redis.setex(roomKey(opts.id), ttlFromExpiry(expiresAt), JSON.stringify(record));
  return record;
}

export async function getRoom(id: string): Promise<RoomRecord | null> {
  const raw = await redis.get(roomKey(id));
  if (!raw) return null;
  try {
    const room = JSON.parse(raw) as RoomRecord;
    if (room.expiresAt && room.expiresAt <= Date.now()) {
      await redis.del(roomKey(id));
      return null;
    }
    return room;
  } catch { return null; }
}

export async function saveRoom(room: RoomRecord) {
  room.updatedAt = Date.now();
  const ttl = room.expiresAt ? ttlFromExpiry(room.expiresAt) : ROOM_TTL;
  await redis.setex(roomKey(room.id), ttl, JSON.stringify(room));
}

/** Webhook: room jadi aktif (ada peserta) → perpanjang masa aktif. */
export async function markRoomActive(id: string) {
  const room = await getRoom(id);
  if (!room) return;
  room.expiresAt = Date.now() + ACTIVE_TTL * 1000;
  await saveRoom(room);
}

/** Webhook: room kosong (semua peserta keluar) → mulai hitung mundur sebelum dihapus. */
export async function markRoomEmpty(id: string) {
  const room = await getRoom(id);
  if (!room) return;
  room.expiresAt = Date.now() + EMPTY_GRACE_TTL * 1000;
  await saveRoom(room);
}

export async function listRooms(limit = 100): Promise<RoomRecord[]> {
  const rooms: RoomRecord[] = [];
  let cursor = '0';
  const cappedLimit = Math.max(1, Math.min(500, limit));

  do {
    const [nextCursor, keys] = await redis.scan(cursor, 'MATCH', 'room:*', 'COUNT', 100);
    cursor = nextCursor;

    if (keys.length > 0) {
      const values = await redis.mget(...keys);
      for (const value of values) {
        if (!value) continue;
        try {
          rooms.push(JSON.parse(value) as RoomRecord);
        } catch {
          // Ignore corrupted room payloads; a later write will repair the key.
        }
        if (rooms.length >= cappedLimit) break;
      }
    }
  } while (cursor !== '0' && rooms.length < cappedLimit);

  return rooms
    .sort((a, b) => (b.updatedAt ?? b.createdAt) - (a.updatedAt ?? a.createdAt))
    .slice(0, cappedLimit);
}

export async function verifyRoomPassword(room: RoomRecord, password?: string): Promise<boolean> {
  if (!room.passwordHash) return true;
  if (!password) return false;
  return bcrypt.compare(password, room.passwordHash);
}

export async function refreshRoomTtl(id: string) { await redis.expire(roomKey(id), ROOM_TTL); }
export async function deleteRoom(id: string) { await redis.del(roomKey(id)); }

export async function appendRoomAudit(roomId: string, event: RoomAuditEvent) {
  const key = roomAuditKey(roomId);
  await redis.lpush(key, JSON.stringify(event));
  await redis.ltrim(key, 0, 199);
  await redis.expire(key, ROOM_AUDIT_TTL);
}

export async function getRoomAudit(roomId: string, limit = 100): Promise<RoomAuditEvent[]> {
  const rows = await redis.lrange(roomAuditKey(roomId), 0, Math.max(0, Math.min(200, limit) - 1));
  return rows
    .map(row => {
      try { return JSON.parse(row) as RoomAuditEvent; } catch { return null; }
    })
    .filter(Boolean) as RoomAuditEvent[];
}

export async function createObserverHandoff(key: string, payload: ObserverHandoff) {
  await redis.setex(observerHandoffKey(key), OBSERVER_HANDOFF_TTL, JSON.stringify(payload));
}

export async function consumeObserverHandoff(key: string): Promise<ObserverHandoff | null> {
  const redisKey = observerHandoffKey(key);
  const raw = await redis.get(redisKey);
  if (!raw) return null;
  await redis.del(redisKey);
  try { return JSON.parse(raw) as ObserverHandoff; } catch { return null; }
}

// === Waiting / Lobby ===
export async function addWaiting(roomId: string, user: WaitingUser) {
  await redis.hset(waitKey(roomId), user.waitingId, JSON.stringify(user));
  await redis.expire(waitKey(roomId), WAITING_TTL);
  await redis.setex(waitStatusKey(user.waitingId), WAITING_TTL, 'waiting');
}

export async function getWaiting(roomId: string): Promise<WaitingUser[]> {
  const all = await redis.hgetall(waitKey(roomId));
  if (!all) return [];
  return Object.values(all).map(v => { try { return JSON.parse(v); } catch { return null; } }).filter(Boolean);
}

export async function approveWaiting(roomId: string, waitingId: string, token: string) {
  await redis.hdel(waitKey(roomId), waitingId);
  await redis.setex(waitStatusKey(waitingId), WAITING_TTL, JSON.stringify({ status: 'approved', token }));
}

export async function rejectWaiting(roomId: string, waitingId: string) {
  await redis.hdel(waitKey(roomId), waitingId);
  await redis.setex(waitStatusKey(waitingId), WAITING_TTL, 'rejected');
}

export async function getWaitingStatus(waitingId: string): Promise<{ status: 'waiting' | 'approved' | 'rejected'; token?: string }> {
  const raw = await redis.get(waitStatusKey(waitingId));
  if (!raw) return { status: 'rejected' };
  if (raw === 'waiting') return { status: 'waiting' };
  if (raw === 'rejected') return { status: 'rejected' };
  try { const d = JSON.parse(raw); return { status: 'approved', token: d.token }; } catch { return { status: 'rejected' }; }
}
