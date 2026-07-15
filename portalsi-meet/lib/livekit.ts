import { AccessToken, RoomServiceClient } from 'livekit-server-sdk';

const LIVEKIT_API_KEY = process.env.LIVEKIT_API_KEY;
const LIVEKIT_API_SECRET = process.env.LIVEKIT_API_SECRET;
const LIVEKIT_HTTP_URL = process.env.LIVEKIT_HTTP_URL; // e.g. http://livekit:7880 (internal) or https://livekit.example.com
export const LIVEKIT_WS_URL = process.env.NEXT_PUBLIC_LIVEKIT_URL; // wss://livekit.example.com

if (!LIVEKIT_API_KEY || !LIVEKIT_API_SECRET) {
  console.warn(
    '[livekit] LIVEKIT_API_KEY / LIVEKIT_API_SECRET missing — set in .env before running'
  );
}

export interface TokenOptions {
  roomId: string;
  identity: string;
  name: string;
  isHost?: boolean;
  canPublish?: boolean;
  canSubscribe?: boolean;
  ttlSeconds?: number;
  /** JSON string metadata peserta (mis. { avatar }) — dibaca di UI room. */
  metadata?: string;
}

export async function createAccessToken(opts: TokenOptions): Promise<string> {
  const at = new AccessToken(LIVEKIT_API_KEY!, LIVEKIT_API_SECRET!, {
    identity: opts.identity,
    name: opts.name,
    metadata: opts.metadata,
    ttl: opts.ttlSeconds ?? 60 * 60, // 1h default
  });

  at.addGrant({
    room: opts.roomId,
    roomJoin: true,
    roomCreate: opts.isHost ?? false,
    roomAdmin: opts.isHost ?? false,
    canPublish: opts.canPublish ?? true,
    canSubscribe: opts.canSubscribe ?? true,
    canPublishData: true,
    canUpdateOwnMetadata: true,
  });

  return await at.toJwt();
}

export function getRoomService(): RoomServiceClient | null {
  if (!LIVEKIT_HTTP_URL || !LIVEKIT_API_KEY || !LIVEKIT_API_SECRET) return null;
  return new RoomServiceClient(LIVEKIT_HTTP_URL, LIVEKIT_API_KEY, LIVEKIT_API_SECRET);
}

export async function removeParticipant(roomId: string, identity: string) {
  const svc = getRoomService();
  if (!svc) throw new Error('LiveKit not configured');
  await svc.removeParticipant(roomId, identity);
}

/**
 * Tutup room LiveKit (memutus semua peserta). Aman dipanggil walau room belum
 * pernah aktif / sudah tertutup — error "room does not exist" diabaikan.
 */
export async function endLivekitRoom(roomId: string) {
  const svc = getRoomService();
  if (!svc) return;
  try {
    await svc.deleteRoom(roomId);
  } catch (err: any) {
    if (!String(err?.message ?? '').toLowerCase().includes('does not exist')) {
      console.warn('[livekit] deleteRoom gagal:', err?.message ?? err);
    }
  }
}

export async function listParticipants(roomId: string) {
  const svc = getRoomService();
  if (!svc) return [];
  return await svc.listParticipants(roomId);
}

export async function deleteLiveKitRoom(roomId: string) {
  const svc = getRoomService();
  if (!svc) return;
  await svc.deleteRoom(roomId);
}

export async function mutePublishedTrack(
  roomId: string,
  identity: string,
  trackSid: string,
  muted: boolean
) {
  const svc = getRoomService();
  if (!svc) throw new Error('LiveKit not configured');
  await svc.mutePublishedTrack(roomId, identity, trackSid, muted);
}
