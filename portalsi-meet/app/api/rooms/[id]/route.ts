import { NextResponse } from 'next/server';
import { getRoom, deleteRoom } from '@/lib/rooms';
import { endLivekitRoom } from '@/lib/livekit';
import { normalizeRoomId } from '@/lib/room-id';
import { readPortalToken, getPortalUser } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function GET(
  _req: Request,
  { params }: { params: { id: string } }
) {
  const id = normalizeRoomId(params.id);
  const room = await getRoom(id);
  if (!room) {
    return NextResponse.json({ exists: false }, { status: 404 });
  }
  return NextResponse.json({
    exists: true,
    requiresPassword: Boolean(room.passwordHash),
    lobby: room.lobby,
    hostName: room.hostName,
  });
}

export async function DELETE(
  req: Request,
  { params }: { params: { id: string } }
) {
  const id = normalizeRoomId(params.id);
  const room = await getRoom(id);
  if (!room) {
    return NextResponse.json({ error: 'Room not found' }, { status: 404 });
  }

  let body: any = {};
  try { body = await req.json(); } catch {}
  const hostIdentity = String(body.hostIdentity ?? new URL(req.url).searchParams.get('hostIdentity') ?? '');

  // Otorisasi: cocok hostIdentity LiveKit ATAU token Portal SI milik pemilik room.
  // (Host yang masuk lewat link punya identity 'user-...' yang beda dari room.hostIdentity,
  //  jadi kita juga terima berdasarkan pemilik akun room.)
  let authorized = hostIdentity !== '' && hostIdentity === room.hostIdentity;
  if (!authorized && room.hostPortalUserId) {
    const portalToken = readPortalToken(req);
    if (portalToken) {
      try {
        const pu = await getPortalUser(portalToken);
        authorized = pu.user_id === room.hostPortalUserId;
      } catch { /* token invalid → tetap tidak berwenang */ }
    }
  }
  if (!authorized) {
    return NextResponse.json({ error: 'Not authorized' }, { status: 403 });
  }

  // End for everyone: tutup room LiveKit (putuskan semua peserta) + hapus record (URL jadi tidak valid).
  await endLivekitRoom(id);
  await deleteRoom(id);
  return NextResponse.json({ success: true });
}
