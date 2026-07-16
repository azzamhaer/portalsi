import { randomUUID } from 'crypto';
import { NextResponse } from 'next/server';
import { appendRoomAudit, createObserverHandoff, getRoom } from '@/lib/rooms';
import { createAccessToken, LIVEKIT_WS_URL } from '@/lib/livekit';
import { normalizeRoomId } from '@/lib/room-id';
import { PortalAuthError, portalDisplayName, requireVerifiedPortalAdmin } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function POST(
  req: Request,
  { params }: { params: { id: string } }
) {
  try {
    const admin = await requireVerifiedPortalAdmin(req);
    const id = normalizeRoomId(params.id);
    const room = await getRoom(id);
    if (!room) return NextResponse.json({ error: 'Room tidak ditemukan.' }, { status: 404 });

    const identity = `moderator-${admin.user.user_id}-${randomUUID()}`;
    const name = `Moderator - ${portalDisplayName(admin.user)}`.slice(0, 60);
    const token = await createAccessToken({
      roomId: id,
      identity,
      name,
      isHost: true,
      canPublish: false,
      canSubscribe: true,
      ttlSeconds: 15 * 60,
    });

    const handoffKey = randomUUID();
    await createObserverHandoff(handoffKey, {
      roomId: id,
      token,
      wsUrl: LIVEKIT_WS_URL,
      identity,
      name,
      isHost: true,
      moderator: true,
      adminUserId: admin.user.user_id,
      createdAt: Date.now(),
    });

    // Gunakan host publik (dari header proxy), bukan origin internal (localhost:3000).
    const proto = req.headers.get('x-forwarded-proto') || 'https';
    const host = req.headers.get('host') || new URL(req.url).host;
    const joinUrl = new URL(`/room/${id}`, `${proto}://${host}`);
    joinUrl.searchParams.set('admin_handoff', handoffKey);

    await appendRoomAudit(id, {
      action: 'observer_token.created',
      actorUserId: admin.user.user_id,
      actorUsername: admin.user.username,
      actorName: admin.user.full_name ?? null,
      metadata: { identity, visibleName: name },
      ts: Date.now(),
    });

    return NextResponse.json({
      token,
      wsUrl: LIVEKIT_WS_URL,
      identity,
      name,
      joinUrl: joinUrl.toString(),
      expiresInSeconds: 60,
      notice: 'Mode observer moderator tercatat di audit dan masuk dengan nama moderator yang terlihat.',
    });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Gagal membuat token observer.' }, { status });
  }
}
