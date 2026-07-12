import { NextResponse } from 'next/server';
import { PORTAL_TOKEN_COOKIE, PortalAuthError, loginToPortal } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function POST(req: Request) {
  try {
    const body = await req.json();
    const login = String(body.login ?? '').trim();
    const password = String(body.password ?? '');

    if (!login || !password) {
      return NextResponse.json({ error: 'Email/username dan password wajib diisi.' }, { status: 422 });
    }

    const { token, user } = await loginToPortal(login, password);
    const res = NextResponse.json({ user });
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
    return NextResponse.json({ error: error?.message || 'Login gagal.' }, { status });
  }
}
