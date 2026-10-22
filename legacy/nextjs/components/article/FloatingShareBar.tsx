'use client';

import React, { useState } from 'react';
import {
  Share2,
  Send,
  Link2,
  Check,
  MessageCircle,
} from 'lucide-react';

interface FloatingShareBarProps {
  title: string;
  url: string;
}

/**
 * Tombol Share Media Sosial Melayang / Sticky:
 * - Pada Desktop (lg): Sticky vertikal di sisi kiri body artikel.
 * - Pada Mobile: Floating pill bar di bagian bawah layar.
 */
export default function FloatingShareBar({ title, url }: FloatingShareBarProps) {
  const [copied, setCopied] = useState(false);

  const encodedUrl = encodeURIComponent(url);
  const encodedTitle = encodeURIComponent(title);

  const shareLinks = [
    {
      name: 'WhatsApp',
      href: `https://api.whatsapp.com/send?text=${encodedTitle}%20-%20${encodedUrl}`,
      colorClass: 'hover:bg-emerald-600 hover:text-white hover:border-emerald-600',
      icon: <MessageCircle className="h-4 w-4" />,
    },
    {
      name: 'Telegram',
      href: `https://t.me/share/url?url=${encodedUrl}&text=${encodedTitle}`,
      colorClass: 'hover:bg-sky-500 hover:text-white hover:border-sky-500',
      icon: <Send className="h-4 w-4" />,
    },
    {
      name: 'X (Twitter)',
      href: `https://twitter.com/intent/tweet?text=${encodedTitle}&url=${encodedUrl}`,
      colorClass: 'hover:bg-zinc-900 dark:hover:bg-white dark:hover:text-zinc-950 hover:text-white',
      icon: (
        <span className="font-sans text-xs font-black leading-none">𝕏</span>
      ),
    },
    {
      name: 'Facebook',
      href: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
      colorClass: 'hover:bg-blue-600 hover:text-white hover:border-blue-600',
      icon: (
        <span className="font-serif text-sm font-black leading-none">f</span>
      ),
    },
  ];

  const handleCopyLink = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => setCopied(false), 2200);
    } catch {
      // Fallback
    }
  };

  return (
    <>
      {/* Desktop Sticky Vertical Bar */}
      <div
        aria-label="Sebarke Kabar"
        className="hidden lg:flex flex-col items-center gap-2.5 sticky top-28"
      >
        <span className="mb-1 flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
          <Share2 className="h-3 w-3" />
          Sebarke
        </span>

        {shareLinks.map((item) => (
          <a
            key={item.name}
            href={item.href}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={`Sebarke ning ${item.name}`}
            title={`Sebarke ning ${item.name}`}
            className={`flex h-10 w-10 items-center justify-center rounded-full border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-300 shadow-sm transition-all ${item.colorClass}`}
          >
            {item.icon}
          </a>
        ))}

        <button
          type="button"
          onClick={handleCopyLink}
          aria-label="Salin link kabar"
          title="Salin link kabar"
          className={`flex h-10 w-10 items-center justify-center rounded-full border transition-all shadow-sm ${
            copied
              ? 'border-emerald-600 bg-emerald-600 text-white'
              : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-300 hover:border-brand-600 hover:text-brand-600'
          }`}
        >
          {copied ? <Check className="h-4 w-4" /> : <Link2 className="h-4 w-4" />}
        </button>
      </div>

      {/* Mobile Floating Bottom Bar */}
      <div className="fixed bottom-4 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-full border border-zinc-200/90 dark:border-zinc-800 bg-white/95 dark:bg-zinc-900/95 px-4 py-2 shadow-xl backdrop-blur-md lg:hidden">
        <span className="mr-1 text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
          Sebarke:
        </span>
        {shareLinks.map((item) => (
          <a
            key={item.name}
            href={item.href}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={`Sebarke ning ${item.name}`}
            className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200"
          >
            {item.icon}
          </a>
        ))}
        <button
          type="button"
          onClick={handleCopyLink}
          aria-label="Salin link kabar"
          className={`flex h-8 items-center gap-1 rounded-full px-3 text-xs font-semibold transition-colors ${
            copied
              ? 'bg-emerald-600 text-white'
              : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200'
          }`}
        >
          {copied ? (
            <>
              <Check className="h-3.5 w-3.5" />
              <span>Kesalin</span>
            </>
          ) : (
            <>
              <Link2 className="h-3.5 w-3.5" />
              <span>Salin</span>
            </>
          )}
        </button>
      </div>
    </>
  );
}
