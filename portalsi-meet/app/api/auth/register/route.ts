import { NextResponse } from 'next/server';
import { PortalAuthError, registerToPortal } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function POST(req: Request) {
  try {
    const body = await req.json();
    const username = String(body.username ?? '').trim();
    const fullName = String(body.fullName ?? body.full_name ?? '').trim();
    const email = String(body.email ?? '').trim();
    const password = String(body.password ?? '');

    if (!username || !fullName || !email || !password) {
      return NextResponse.json({ error: 'Username, nama lengkap, email, dan password wajib diisi.' }, { status: 422 });
    }

    const { user, verificationStatus, message } = await registerToPortal({ username, full_name: fullName, email, password });
    // Tidak set cookie / tidak auto-login: user harus verifikasi email dulu.
    return NextResponse.json({ user, verificationStatus, message, needsVerification: true }, { status: 201 });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Pendaftaran gagal.' }, { status });
  }
}
