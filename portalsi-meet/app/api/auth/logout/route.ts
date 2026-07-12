import { NextResponse } from 'next/server';
import { PORTAL_TOKEN_COOKIE, logoutFromPortal, readPortalToken } from '@/lib/portal-auth';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

export async function POST(req: Request) {
  const token = readPortalToken(req);
  if (token) {
    try {
      await logoutFromPortal(token);
    } catch {
      // Cookie removal still wins if Portal SI is temporarily unavailable.
    }
  }

  const res = NextResponse.json({ ok: true });
  res.cookies.delete(PORTAL_TOKEN_COOKIE);
  return res;
}
