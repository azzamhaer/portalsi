'use client';

import { useT, type Lang } from '@/lib/i18n';

export function MeetFooter() {
  const { t, lang, setLang } = useT();
  return (
    <footer className="w-full border-t border-gray-100">
      <div className="max-w-6xl mx-auto px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3">
        <p className="text-xs text-gray-400">&copy; {new Date().getFullYear()} Portal SI Meet</p>
        <div className="flex items-center gap-4 text-xs text-gray-400">
          <span>{t('footer.free')}</span>
          <span className="text-gray-200">·</span>
          <span>{t('footer.secure')}</span>
          <span className="text-gray-200">·</span>
          <span>{t('footer.integrated')}</span>
          <span className="text-gray-200">·</span>
          <span className="inline-flex items-center rounded-full border border-gray-200 p-0.5">
            {(['id', 'en'] as Lang[]).map((l) => (
              <button
                key={l}
                type="button"
                onClick={() => setLang(l)}
                className={`rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors ${lang === l ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-800'}`}
              >
                {l.toUpperCase()}
              </button>
            ))}
          </span>
        </div>
      </div>
    </footer>
  );
}
