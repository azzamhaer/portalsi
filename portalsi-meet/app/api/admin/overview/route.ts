import { NextResponse } from 'next/server';
import { listRooms } from '@/lib/rooms';
import { PortalAuthError, requireVerifiedPortalAdmin } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function GET(req: Request) {
  try {
    const admin = await requireVerifiedPortalAdmin(req);
    const rooms = await listRooms(200);
    const now = Date.now();

    return NextResponse.json({
      admin: {
        user_id: admin.user.user_id,
        username: admin.user.username,
        full_name: admin.user.full_name,
        email: admin.user.email,
        is_verified: Boolean(admin.user.is_verified),
      },
      rooms: {
        total: rooms.length,
        locked: rooms.filter(room => room.adminLocked).length,
        join_disabled: rooms.filter(room => room.permissions && !room.permissions.allowJoin).length,
        lobby_enabled: rooms.filter(room => room.permissions?.lobbyMode || room.lobby).length,
        scheduled: rooms.filter(room => room.scheduledFor && room.scheduledFor > now).length,
      },
      recent_rooms: rooms.slice(0, 12),
    });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Gagal memuat overview admin Meet.' }, { status });
  }
}
