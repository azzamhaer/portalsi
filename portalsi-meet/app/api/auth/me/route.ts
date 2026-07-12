import { NextResponse } from 'next/server';
import { PORTAL_TOKEN_COOKIE, PortalAuthError, getPortalUser, readPortalToken } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function GET(req: Request) {
  const token = readPortalToken(req);
  if (!token) {
    return NextResponse.json({ user: null }, { status: 401 });
  }

  try {
    return NextResponse.json({ user: await getPortalUser(token) });
  } catch (error: any) {
    const status = error instanceof PortalAuthError ? error.status : 500;
    const res = NextResponse.json({ error: error?.message || 'Sesi tidak valid.' }, { status });
    if (status === 401 || status === 403) {
      res.cookies.delete(PORTAL_TOKEN_COOKIE);
    }
    return res;
  }
}
