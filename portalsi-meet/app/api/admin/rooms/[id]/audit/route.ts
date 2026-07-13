import { NextResponse } from 'next/server';
import { getRoomAudit } from '@/lib/rooms';
import { normalizeRoomId } from '@/lib/room-id';
import { PortalAuthError, requireVerifiedPortalAdmin } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function GET(
  req: Request,
  { params }: { params: { id: string } }
) {
  try {
    await requireVerifiedPortalAdmin(req);
    const id = normalizeRoomId(params.id);
    const limit = Number(new URL(req.url).searchParams.get('limit') || 100);
    const audit = await getRoomAudit(id, limit);

    return NextResponse.json({ roomId: id, audit });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Gagal memuat audit room.' }, { status });
  }
}
