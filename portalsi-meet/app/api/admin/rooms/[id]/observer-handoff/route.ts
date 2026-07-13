import { NextResponse } from 'next/server';
import { appendRoomAudit, consumeObserverHandoff } from '@/lib/rooms';
import { normalizeRoomId } from '@/lib/room-id';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function GET(
  req: Request,
  { params }: { params: { id: string } }
) {
  const id = normalizeRoomId(params.id);
  const key = new URL(req.url).searchParams.get('key') || '';
  if (!key) {
    return NextResponse.json({ error: 'Handoff key diperlukan.' }, { status: 400 });
  }

  const payload = await consumeObserverHandoff(key);
  if (!payload || payload.roomId !== id) {
    return NextResponse.json({ error: 'Handoff observer tidak valid atau sudah kedaluwarsa.' }, { status: 404 });
  }

  await appendRoomAudit(id, {
    action: 'observer_handoff.consumed',
    actorUserId: payload.adminUserId,
    metadata: { identity: payload.identity, visibleName: payload.name },
    ts: Date.now(),
  });

  return NextResponse.json(payload);
}
