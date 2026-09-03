'use client';

import { useT } from '@/lib/i18n';

export function MeetFooter() {
  const { t } = useT();
  return (
    <footer className="w-full border-t border-gray-100">
      <div className="max-w-6xl mx-auto px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-2">
        <p className="text-xs text-gray-400">&copy; {new Date().getFullYear()} Portal SI Meet</p>
        <div className="flex items-center gap-4 text-xs text-gray-400">
          <span>{t('footer.free')}</span>
          <span className="text-gray-200">·</span>
          <span>{t('footer.secure')}</span>
          <span className="text-gray-200">·</span>
          <span>{t('footer.integrated')}</span>
        </div>
      </div>
    </footer>
  );
}
