export interface PortalSiUser {
  user_id: number;
  username: string;
  full_name?: string | null;
  email?: string | null;
  email_verified_at?: string | null;
  role?: string | null;
  is_verified?: boolean | number;
}

export class PortalAuthError extends Error {
  constructor(message: string, public readonly status = 401, public readonly data: any = null) {
    super(message);
  }
}

export const PORTAL_TOKEN_COOKIE = 'ps_meet_token';

const PORTAL_API_URL = (process.env.PORTALSI_API_URL || 'https://api-new.portalsi.com/api').replace(/\/+$/, '');
const REQUEST_TIMEOUT_MS = Number(process.env.PORTALSI_API_TIMEOUT_MS || 12000);

export function portalDisplayName(user: PortalSiUser): string {
  return (user.full_name || user.username || user.email || 'Host').slice(0, 40);
}

export function readPortalToken(req: Request): string | null {
  const cookie = req.headers.get('cookie') || '';
  const parts = cookie.split(';').map(part => part.trim());
  const item = parts.find(part => part.startsWith(`${PORTAL_TOKEN_COOKIE}=`));
  return item ? decodeURIComponent(item.slice(PORTAL_TOKEN_COOKIE.length + 1)) : null;
}

export function readPortalBearerToken(req: Request): string | null {
  const header = req.headers.get('authorization') || '';
  const match = header.match(/^Bearer\s+(.+)$/i);
  return match ? match[1].trim() : null;
}

export async function loginToPortal(login: string, password: string): Promise<{ token: string; user: PortalSiUser }> {
  const payload = await portalRequest('/login', {
    method: 'POST',
    body: JSON.stringify({ login, password }),
  });

  if (!payload.token || !payload.user) {
    throw new PortalAuthError('Response login Portal SI tidak valid.', 502, payload);
  }

  return { token: payload.token, user: payload.user };
}

export async function registerToPortal(input: {
  username: string;
  full_name: string;
  email: string;
  password: string;
}): Promise<{ token: string; user: PortalSiUser }> {
  const payload = await portalRequest('/register', {
    method: 'POST',
    body: JSON.stringify({
      username: input.username,
      full_name: input.full_name,
      email: input.email,
      password: input.password,
      role: 'student',
    }),
  });

  if (!payload.token || !payload.user) {
    throw new PortalAuthError('Response pendaftaran Portal SI tidak valid.', 502, payload);
  }

  return { token: payload.token, user: payload.user };
}

export async function getPortalUser(token: string): Promise<PortalSiUser> {
  const payload = await portalRequest('/user', {
    method: 'GET',
    headers: { Authorization: `Bearer ${token}` },
  });

  return payload.user ?? payload;
}

export async function logoutFromPortal(token: string): Promise<void> {
  await portalRequest('/logout', {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}` },
  });
}

export async function requirePortalUser(req: Request): Promise<{ token: string; user: PortalSiUser }> {
  const token = readPortalToken(req);
  if (!token) {
    throw new PortalAuthError('Login dengan akun Portal SI diperlukan untuk membuat meeting.', 401);
  }

  const user = await getPortalUser(token);
  return { token, user };
}

export async function requireVerifiedPortalAdmin(req: Request): Promise<{ token: string; user: PortalSiUser }> {
  const token = readPortalBearerToken(req) || readPortalToken(req);
  if (!token) {
    throw new PortalAuthError('Token admin Portal SI diperlukan.', 401);
  }

  const user = await getPortalUser(token);
  if (!Boolean(user.is_verified)) {
    throw new PortalAuthError('Akses admin panel hanya untuk akun Portal SI yang terverifikasi.', 403);
  }

  return { token, user };
}

async function portalRequest(path: string, init: RequestInit): Promise<any> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

  try {
    const res = await fetch(`${PORTAL_API_URL}${path}`, {
      ...init,
      signal: controller.signal,
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(init.headers || {}),
      },
    });

    const text = await res.text();
    let data: any = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { message: text }; }

    if (!res.ok) {
      throw new PortalAuthError(data.message || data.error || 'Portal SI menolak request ini.', res.status, data);
    }

    return data;
  } catch (error: any) {
    if (error instanceof PortalAuthError) throw error;
    throw new PortalAuthError('Portal SI tidak dapat dihubungi. Coba lagi beberapa saat.', 503);
  } finally {
    clearTimeout(timer);
  }
}
