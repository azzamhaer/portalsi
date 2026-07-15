import { NextResponse } from 'next/server';
import { randomUUID } from 'crypto';
import { createRoom, getRoom } from '@/lib/rooms';
import { generateRoomId } from '@/lib/room-id';
import { createAccessToken, LIVEKIT_WS_URL } from '@/lib/livekit';
import { getClientIp, rateLimit } from '@/lib/rate-limit';
import { PortalAuthError, portalDisplayName, requirePortalUser } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function POST(req: Request) {
  const ip = getClientIp(req);
  let portal: Awaited<ReturnType<typeof requirePortalUser>>;

  try {
    portal = await requirePortalUser(req);
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 401;
    return NextResponse.json(
      { error: error?.message || 'Login dengan akun Portal SI diperlukan untuk membuat meeting.' },
      { status }
    );
  }

  // Rate limit: 20 room creations per hour per Portal SI account
  const rl = await rateLimit({
    key: `create:${portal.user.user_id}:${ip}`,
    max: 20,
    windowSeconds: 3600,
  });
  if (!rl.allowed) {
    return NextResponse.json(
      { error: 'Terlalu banyak ruang dibuat. Coba lagi nanti.' },
      { status: 429, headers: { 'Retry-After': String(rl.resetSeconds) } }
    );
  }

  let body: any = {};
  try {
    body = await req.json();
  } catch {
    // allow empty body
  }

  const requestedHostName = String(body.hostName ?? '').trim().slice(0, 40);
  const hostName = requestedHostName || portalDisplayName(portal.user);
  const password = body.password ? String(body.password).slice(0, 64) : undefined;
  const lobby = Boolean(body.lobby);
  const scheduledFor = body.scheduledFor ? Number(body.scheduledFor) : undefined;

  // Try up to 5 IDs to avoid rare collisions
  let id = '';
  for (let attempt = 0; attempt < 5; attempt++) {
    const candidate = generateRoomId(6);
    if (!await getRoom(candidate)) {
      id = candidate;
      break;
    }
  }

  if (!id) {
    return NextResponse.json({ error: 'Gagal membuat kode meeting unik. Coba lagi.' }, { status: 500 });
  }

  const hostIdentity = `host-${randomUUID()}`;

  await createRoom({
    id,
    hostIdentity,
    hostName,
    hostPortalUserId: portal.user.user_id,
    hostUsername: portal.user.username,
    hostEmail: portal.user.email ?? null,
    password,
    lobby,
    scheduledFor,
  });

  const token = await createAccessToken({
    roomId: id,
    identity: hostIdentity,
    name: hostName,
    isHost: true,
    metadata: JSON.stringify({ avatar: portal.user.profile_picture_url ?? null }),
  });

  return NextResponse.json({
    roomId: id,
    token,
    wsUrl: LIVEKIT_WS_URL,
    isHost: true,
  });
}
