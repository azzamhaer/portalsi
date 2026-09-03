'use client';

import { useState } from 'react';
import { HelpCircle, X, MessageCircle } from 'lucide-react';
import { useT } from '@/lib/i18n';

export function FaqButton() {
  const { t } = useT();
  const [isOpen, setIsOpen] = useState(false);

  const faqs = [
    { q: t('faq.q1'), a: t('faq.a1') },
    { q: t('faq.q2'), a: t('faq.a2') },
    { q: t('faq.q3'), a: t('faq.a3') },
    { q: t('faq.q4'), a: t('faq.a4') },
    { q: t('faq.q5'), a: t('faq.a5') },
  ];

  return (
    <>
      <button 
        onClick={() => setIsOpen(true)}
        className="flex items-center gap-1.5 text-[11px] sm:text-xs font-medium text-dove-green hover:text-green-700 transition-all bg-green-50/50 hover:bg-green-50 px-2.5 sm:px-3 py-1.5 rounded-full border border-green-100/50 hover:border-green-200"
      >
        <HelpCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4" />
        <span>{t('faq.btn')}</span>
      </button>

      {isOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-fade-in">
          <div 
            className="absolute inset-0 bg-black/40 backdrop-blur-sm"
            onClick={() => setIsOpen(false)}
          />
          <div className="relative w-full max-w-lg bg-white rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[85vh] animate-scale-in">
            <div className="p-4 sm:p-6 border-b border-gray-100 flex items-center justify-between bg-white sticky top-0 z-10">
              <h2 className="text-lg font-semibold text-gray-800 flex items-center gap-2">
                <HelpCircle className="w-5 h-5 text-dove-green" />
                {t('faq.title')}
              </h2>
              <button 
                onClick={() => setIsOpen(false)}
                className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            
            <div className="p-4 sm:p-6 overflow-y-auto flex-1 bg-gray-50/50">
              <div className="space-y-3 sm:space-y-4">
                {faqs.map((faq, index) => (
                  <div key={index} className="bg-white p-4 rounded-xl border border-gray-100 shadow-sm transition-all hover:shadow-md">
                    <h3 className="font-medium text-gray-800 text-[13px] sm:text-sm mb-1.5">{faq.q}</h3>
                    <p className="text-gray-500 text-xs sm:text-sm leading-relaxed">{faq.a}</p>
                  </div>
                ))}
              </div>

              <div className="mt-6 p-4 sm:p-5 bg-green-50 rounded-xl border border-green-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div className="text-center sm:text-left">
                  <h4 className="text-[13px] sm:text-sm font-medium text-green-900">{t('faq.needHelp')}</h4>
                  <p className="text-[11px] sm:text-xs text-green-700 mt-1">{t('faq.contactVia')}</p>
                </div>
                <a 
                  href="https://wa.me/6281350880733" 
                  target="_blank" 
                  rel="noreferrer"
                  className="flex items-center gap-2 bg-dove-green hover:bg-[#4a855b] text-white px-4 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors whitespace-nowrap w-full sm:w-auto justify-center shadow-sm"
                >
                  <MessageCircle className="w-4 h-4" />
                  {t('faq.contactBtn')}
                </a>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
