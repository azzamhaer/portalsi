import { NextResponse } from 'next/server';
import { PORTAL_TOKEN_COOKIE, PortalAuthError, registerToPortal } from '@/lib/portal-auth';

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

    const { token, user } = await registerToPortal({ username, full_name: fullName, email, password });
    const res = NextResponse.json({ user }, { status: 201 });
    res.cookies.set(PORTAL_TOKEN_COOKIE, token, {
      httpOnly: true,
      sameSite: 'lax',
      secure: process.env.NODE_ENV === 'production',
      path: '/',
      maxAge: 60 * 60 * 24 * 30,
    });
    return res;
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    return NextResponse.json({ error: error?.message || 'Pendaftaran gagal.' }, { status });
  }
}
