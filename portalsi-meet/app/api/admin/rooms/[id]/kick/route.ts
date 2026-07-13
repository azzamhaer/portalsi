import { NextResponse } from 'next/server';
import { appendRoomAudit, getRoom } from '@/lib/rooms';
import { removeParticipant } from '@/lib/livekit';
import { normalizeRoomId } from '@/lib/room-id';
import { PortalAuthError, requireVerifiedPortalAdmin } from '@/lib/portal-auth';

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

    let body: any = {};
    try { body = await req.json(); } catch { return NextResponse.json({ error: 'Body tidak valid.' }, { status: 400 }); }

    const identity = String(body.identity || '').trim();
    if (!identity) return NextResponse.json({ error: 'identity peserta wajib diisi.' }, { status: 422 });

    await removeParticipant(id, identity);
    await appendRoomAudit(id, {
      action: 'participant.kicked',
      actorUserId: admin.user.user_id,
      actorUsername: admin.user.username,
      actorName: admin.user.full_name ?? null,
      metadata: { identity },
      ts: Date.now(),
    });

    return NextResponse.json({ message: 'Peserta berhasil dikeluarkan dari room.' });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Gagal mengeluarkan peserta.' }, { status });
  }
}
