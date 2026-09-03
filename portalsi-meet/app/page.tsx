import Link from 'next/link';
import { HomeHero } from '@/components/HomeHero';
import { FaqButton } from '@/components/FaqButton';
import { MeetFooter } from '@/components/MeetFooter';

export default function HomePage() {
  return (
    <main className="homepage min-h-dvh flex flex-col">
      {/* Navbar */}
      <header className="w-full border-b border-gray-100">
        <div className="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
          <Link href="/" className="flex items-center gap-2.5 group">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl overflow-hidden">
              <img
                src="/logo.png"
                alt="logo"
                className="h-full w-full object-cover"
              />
            </div>
            <div className="flex flex-col leading-none">
              <span className="text-[15px] font-semibold text-gray-800 tracking-tight">Portal SI</span>
              <span className="text-[10px] font-medium text-dove-green tracking-[0.15em] uppercase">Meet</span>
            </div>
          </Link>
          <div className="flex items-center gap-4">
            <FaqButton />
          </div>
        </div>
      </header>

      {/* Content */}
      <div className="flex-1 flex flex-col justify-center">
        <HomeHero />
      </div>

      {/* Footer */}
      <MeetFooter />
    </main>
  );
}
