import { NextResponse } from 'next/server';
import { appendRoomAudit, deleteRoom, getRoom, saveRoom } from '@/lib/rooms';
import { deleteLiveKitRoom, listParticipants } from '@/lib/livekit';
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
    const room = await getRoom(id);
    if (!room) return NextResponse.json({ error: 'Room tidak ditemukan.' }, { status: 404 });

    let participants: any[] = [];
    try {
      const rows = await listParticipants(id);
      participants = rows.map((participant: any) => ({
        identity: participant.identity,
        name: participant.name,
        state: participant.state,
        joined_at: participant.joinedAt,
        metadata: participant.metadata,
        track_count: participant.tracks?.length ?? 0,
      }));
    } catch {
      participants = [];
    }

    return NextResponse.json({ room, participants });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Gagal memuat detail room.' }, { status });
  }
}

export async function PATCH(
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

    if (typeof body.lobby === 'boolean') room.lobby = body.lobby;
    if (typeof body.adminLocked === 'boolean') room.adminLocked = body.adminLocked;
    if (typeof body.moderatorNotes === 'string' || body.moderatorNotes === null) {
      room.moderatorNotes = body.moderatorNotes ? String(body.moderatorNotes).slice(0, 2000) : null;
    }
    if (body.scheduledFor === null) {
      room.scheduledFor = undefined;
    } else if (body.scheduledFor !== undefined) {
      const scheduledFor = Number(body.scheduledFor);
      if (!Number.isFinite(scheduledFor)) {
        return NextResponse.json({ error: 'scheduledFor harus timestamp milidetik.' }, { status: 422 });
      }
      room.scheduledFor = scheduledFor;
    }

    if (body.permissions && typeof body.permissions === 'object') {
      const current = room.permissions || {
        allowChat: true,
        allowScreenShare: true,
        allowJoin: true,
        allowReactions: true,
        lobbyMode: false,
        allowRename: true,
      };
      room.permissions = {
        allowChat: typeof body.permissions.allowChat === 'boolean' ? body.permissions.allowChat : current.allowChat,
        allowScreenShare: typeof body.permissions.allowScreenShare === 'boolean' ? body.permissions.allowScreenShare : current.allowScreenShare,
        allowJoin: typeof body.permissions.allowJoin === 'boolean' ? body.permissions.allowJoin : current.allowJoin,
        allowReactions: typeof body.permissions.allowReactions === 'boolean' ? body.permissions.allowReactions : current.allowReactions,
        lobbyMode: typeof body.permissions.lobbyMode === 'boolean' ? body.permissions.lobbyMode : current.lobbyMode,
        allowRename: typeof body.permissions.allowRename === 'boolean' ? body.permissions.allowRename : current.allowRename,
      };
    }

    room.lastModeratedAt = Date.now();
    room.lastModeratedBy = {
      userId: admin.user.user_id,
      username: admin.user.username,
      fullName: admin.user.full_name ?? null,
    };

    await saveRoom(room);
    await appendRoomAudit(id, {
      action: 'room.updated',
      actorUserId: admin.user.user_id,
      actorUsername: admin.user.username,
      actorName: admin.user.full_name ?? null,
      metadata: {
        adminLocked: room.adminLocked ?? false,
        lobby: room.lobby,
        permissions: room.permissions,
        scheduledFor: room.scheduledFor,
      },
      ts: Date.now(),
    });

    return NextResponse.json({ message: 'Room berhasil diperbarui.', room });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Gagal memperbarui room.' }, { status });
  }
}

export async function DELETE(
  req: Request,
  { params }: { params: { id: string } }
) {
  try {
    const admin = await requireVerifiedPortalAdmin(req);
    const id = normalizeRoomId(params.id);
    const room = await getRoom(id);
    if (!room) return NextResponse.json({ error: 'Room tidak ditemukan.' }, { status: 404 });

    await appendRoomAudit(id, {
      action: 'room.deleted',
      actorUserId: admin.user.user_id,
      actorUsername: admin.user.username,
      actorName: admin.user.full_name ?? null,
      metadata: { room },
      ts: Date.now(),
    });

    await deleteLiveKitRoom(id);
    await deleteRoom(id);

    return NextResponse.json({ message: 'Room berhasil dihapus dan sesi LiveKit ditutup jika aktif.' });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Gagal menghapus room.' }, { status });
  }
}
