import { NextResponse } from 'next/server';
import { listRooms } from '@/lib/rooms';
import { PortalAuthError, requireVerifiedPortalAdmin } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function GET(req: Request) {
  try {
    await requireVerifiedPortalAdmin(req);

    const url = new URL(req.url);
    const limit = Number(url.searchParams.get('limit') || 100);
    const search = (url.searchParams.get('search') || '').trim().toLowerCase();
    const locked = url.searchParams.get('locked');
    const rooms = await listRooms(limit);

    let filtered = rooms;
    if (search) {
      filtered = filtered.filter(room =>
        room.id.toLowerCase().includes(search) ||
        room.hostName.toLowerCase().includes(search) ||
        (room.hostUsername || '').toLowerCase().includes(search)
      );
    }
    if (locked === '1' || locked === 'true') {
      filtered = filtered.filter(room => room.adminLocked);
    }
    if (locked === '0' || locked === 'false') {
      filtered = filtered.filter(room => !room.adminLocked);
    }

    return NextResponse.json({ rooms: filtered });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Gagal memuat room Meet.' }, { status });
  }
}
