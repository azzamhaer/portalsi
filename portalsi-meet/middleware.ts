import { NextRequest, NextResponse } from 'next/server';

const allowedOrigins = (process.env.ADMIN_CORS_ORIGINS ||
  'https://admin.portalsi.com,http://localhost:5173,http://127.0.0.1:5173')
  .split(',')
  .map(origin => origin.trim())
  .filter(Boolean);

function corsHeaders(origin: string | null) {
  const headers = new Headers();
  if (origin && allowedOrigins.includes(origin)) {
    headers.set('Access-Control-Allow-Origin', origin);
  }
  headers.set('Vary', 'Origin');
  headers.set('Access-Control-Allow-Methods', 'GET,POST,PATCH,DELETE,OPTIONS');
  headers.set('Access-Control-Allow-Headers', 'Accept,Authorization,Content-Type');
  headers.set('Access-Control-Max-Age', '600');
  return headers;
}

export function middleware(req: NextRequest) {
  const origin = req.headers.get('origin');
  const headers = corsHeaders(origin);

  if (req.method === 'OPTIONS') {
    return new NextResponse(null, { status: 204, headers });
  }

  const response = NextResponse.next();
  headers.forEach((value, key) => response.headers.set(key, value));
  return response;
}

export const config = {
  matcher: ['/api/admin/:path*'],
};
