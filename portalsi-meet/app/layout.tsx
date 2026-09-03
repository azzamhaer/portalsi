import type { Metadata, Viewport } from 'next';
import { Inter } from 'next/font/google';
import { cookies } from 'next/headers';
import '@livekit/components-styles';
import './globals.css';
import { LangProvider, LangToggle, type Lang } from '@/lib/i18n';
const inter = Inter({
  subsets: ['latin'],
  variable: '--font-inter',
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'Portal SI Meet',
  description:
    'Meeting online berkualitas HD. Host membuat ruang dengan akun Portal SI, peserta bisa bergabung cukup dengan nama dan Room ID.',
  keywords: ['video conference', 'meeting online', 'webinar', 'video call', 'portalsi'],
  openGraph: {
    title: 'PortalSI Meet',
    description: 'Video conference real-time dengan akun Portal SI untuk host dan akses cepat untuk peserta.',
    type: 'website',
  },
  robots: { index: true, follow: true },
  icons: {
    icon: '/logo.png',
  },
};

export const viewport: Viewport = {
  themeColor: '#0a0a0f',
  width: 'device-width',
  initialScale: 1,
  maximumScale: 1,
  userScalable: false,
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const c = cookies().get('portalsi_lang')?.value;
  const initialLang: Lang = c === 'en' || c === 'id' ? c : 'id';
  return (
    <html lang={initialLang} className={inter.variable}>
      <body className={`${inter.className} theme-comic`}>
        <LangProvider initial={initialLang}>
          {children}
          <LangToggle />
        </LangProvider>
      </body>
    </html>
  );
}
