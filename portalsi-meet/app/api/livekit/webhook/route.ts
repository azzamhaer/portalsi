import { NextResponse } from 'next/server';
import { WebhookReceiver } from 'livekit-server-sdk';
import { markRoomActive, markRoomEmpty } from '@/lib/rooms';
import { normalizeRoomId } from '@/lib/room-id';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

const receiver = new WebhookReceiver(
  process.env.LIVEKIT_API_KEY || '',
  process.env.LIVEKIT_API_SECRET || '',
);

// LiveKit mengirim event lifecycle room ke sini (dikonfigurasi di livekit.yaml).
// - room_started / participant_joined  -> room aktif, perpanjang masa aktif.
// - room_finished (kosong >= empty_timeout) -> mulai hitung mundur 1 jam lalu terhapus.
export async function POST(req: Request) {
  try {
    const body = await req.text();
    const authHeader = req.headers.get('Authorization') || '';
    const event = await receiver.receive(body, authHeader);

    const roomName = event.room?.name;
    if (roomName) {
      const id = normalizeRoomId(roomName);
      switch (event.event) {
        case 'room_started':
        case 'participant_joined':
          await markRoomActive(id);
          break;
        case 'room_finished':
          await markRoomEmpty(id);
          break;
        default:
          break;
      }
    }
    return NextResponse.json({ ok: true });
  } catch (err: any) {
    console.warn('[livekit webhook] gagal:', err?.message ?? err);
    return NextResponse.json({ ok: false }, { status: 400 });
  }
}
