'use client';

import React, { useEffect, useState } from 'react';
import { Type, Minus, Plus, RotateCcw } from 'lucide-react';

interface ArticleReaderContentProps {
  htmlContent: string;
}

type FontScale = 'sm' | 'base' | 'lg' | 'xl';

const FONT_SCALE_CONFIG: Record<
  FontScale,
  { label: string; sizeClass: string }
> = {
  sm: {
    label: 'Cilik',
    sizeClass: 'text-[15px] leading-[1.75]',
  },
  base: {
    label: 'Sedhengan',
    sizeClass: 'text-[17px] sm:text-[18px] leading-[1.85]',
  },
  lg: {
    label: 'Gedhe',
    sizeClass: 'text-[19px] sm:text-[20px] leading-[1.9]',
  },
  xl: {
    label: 'Gedhe Banget',
    sizeClass: 'text-[21px] sm:text-[23px] leading-[1.95]',
  },
};

const SCALES: FontScale[] = ['sm', 'base', 'lg', 'xl'];

/**
 * Defense-in-depth HTML Sanitizer di sisi Frontend sebelum dirender ke dangerouslySetInnerHTML:
 * - Menghapus tag <script>, <object>, <embed>, <form>, <style>
 * - Menghapus seluruh atribut inline event handler (onerror, onload, onclick, dll.)
 * - Memblokir protokol berbahaya (javascript:, vbscript:, data:text/html)
 * - Membungkus iframe YouTube resmi secara aman dengan atribut sandbox & referrerpolicy
 */
export function sanitizeAndEnhanceHtml(rawHtml: string): string {
  if (!rawHtml) return '';

  let clean = rawHtml
    // 1. Hapus tag <script>, <object>, <embed>, <form>, <base>, <meta> beserta isinya
    .replace(/<(script|object|embed|form|base|meta|svg|math)[\s\S]*?>[\s\S]*?<\/\1>/gi, '')
    .replace(/<(script|object|embed|form|base|meta|input|button)[^>]*\/?>/gi, '')
    // 2. Hapus atribut event handler inline (on*=...)
    .replace(/\s+on[a-z]+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi, '')
    // 3. Blokir javascript: dan vbscript: di dalam href/src
    .replace(/(href|src)\s*=\s*["']?\s*(?:javascript|vbscript|data:text\/html)[^"'\s>]*/gi, '$1="#"');

  // 4. Izinkan iframe dari YouTube resmi, Google Drive Preview (/file/d/{id}/preview), atau dokumen PDF
  clean = clean.replace(
    /<iframe([^>]*)><\/iframe>/gi,
    (fullMatch, attrs: string) => {
      const ytMatch = attrs.match(
        /src=["']https:\/\/(?:www\.)?(?:youtube\.com\/embed\/|youtube-nocookie\.com\/embed\/)([a-zA-Z0-9_-]+)[^"']*["']/i
      );
      if (ytMatch) {
        const videoId = ytMatch[1];
        return `<div class="my-6 aspect-video w-full overflow-hidden rounded-xl shadow-sm bg-zinc-900"><iframe src="https://www.youtube-nocookie.com/embed/${videoId}" class="h-full w-full border-0" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-presentation" allowfullscreen></iframe></div>`;
      }

      const driveMatch = attrs.match(
        /src=["'](https:\/\/drive\.google\.com\/file\/d\/[a-zA-Z0-9_-]+\/preview)["']/i
      );
      if (driveMatch) {
        return `<div class="my-6 aspect-video w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm bg-zinc-900"><iframe src="${driveMatch[1]}" class="h-full w-full border-0" loading="lazy" sandbox="allow-scripts allow-same-origin allow-popups" allowfullscreen></iframe></div>`;
      }

      const pdfMatch = attrs.match(
        /src=["'](https?:\/\/[^\s"'<>]+\.pdf(?:\?[^\s"'<>]*)?)["']/i
      );
      if (pdfMatch) {
        return `<div class="my-6 h-[560px] w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm bg-zinc-100"><iframe src="${pdfMatch[1]}" class="h-full w-full border-0" loading="lazy"></iframe></div>`;
      }

      return '';
    }
  );

  return clean;
}

/**
 * Komponen Konten Artikel dengan:
 * - Pengatur ukuran font pembaca interaktif (A- / A+) yang disimpan di localStorage
 * - Sanitasi XSS berlapis & tipografi editorial nyaman dibaca
 */
export default function ArticleReaderContent({
  htmlContent,
}: ArticleReaderContentProps) {
  const [scaleIndex, setScaleIndex] = useState<number>(1); // default: 'base'

  useEffect(() => {
    const saved = localStorage.getItem('reader_font_scale') as FontScale | null;
    if (saved && SCALES.includes(saved)) {
      setScaleIndex(SCALES.indexOf(saved));
    }
  }, []);

  const updateScale = (nextIndex: number) => {
    const clamped = Math.max(0, Math.min(SCALES.length - 1, nextIndex));
    setScaleIndex(clamped);
    localStorage.setItem('reader_font_scale', SCALES[clamped]);
  };

  const currentScale = FONT_SCALE_CONFIG[SCALES[scaleIndex]];
  const processedHtml = sanitizeAndEnhanceHtml(htmlContent);

  return (
    <div>
      {/* Toolbar Pengatur Ukuran Font Pembaca (A- / A+) */}
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-100/70 dark:bg-zinc-900/70 px-4 py-2.5">
        <div className="flex items-center gap-2 text-xs font-medium text-zinc-600 dark:text-zinc-400">
          <Type className="h-4 w-4 text-brand-600" />
          <span>Ukuran Aksara Wacan:</span>
          <strong className="text-zinc-900 dark:text-zinc-100">
            {currentScale.label}
          </strong>
        </div>

        <div className="flex items-center gap-1.5">
          <button
            type="button"
            onClick={() => updateScale(scaleIndex - 1)}
            disabled={scaleIndex === 0}
            aria-label="Perkecil ukuran teks (A-)"
            className="inline-flex h-8 items-center gap-1 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 text-xs font-bold text-zinc-700 dark:text-zinc-200 hover:border-brand-600 disabled:opacity-40 transition-colors"
          >
            <Minus className="h-3 w-3" />
            <span>A-</span>
          </button>

          <button
            type="button"
            onClick={() => updateScale(1)}
            aria-label="Reset ukuran teks normal"
            title="Reset ke ukuran normal"
            className="inline-flex h-8 items-center justify-center rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2 text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-white transition-colors"
          >
            <RotateCcw className="h-3.5 w-3.5" />
          </button>

          <button
            type="button"
            onClick={() => updateScale(scaleIndex + 1)}
            disabled={scaleIndex === SCALES.length - 1}
            aria-label="Perbesar ukuran teks (A+)"
            className="inline-flex h-8 items-center gap-1 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 text-xs font-bold text-zinc-700 dark:text-zinc-200 hover:border-brand-600 disabled:opacity-40 transition-colors"
          >
            <span>A+</span>
            <Plus className="h-3 w-3" />
          </button>
        </div>
      </div>

      {/* Isi Konten Artikel dengan Styling Prose Editorial */}
      <div
        className={`article-prose font-sans text-zinc-800 dark:text-zinc-200 transition-all duration-150 ${currentScale.sizeClass}
          [&>p]:mb-6
          [&>h2]:mt-10 [&>h2]:mb-4 [&>h2]:font-serif [&>h2]:text-2xl [&>h2]:font-bold [&>h2]:text-zinc-950 dark:[&>h2]:text-white
          [&>h3]:mt-8 [&>h3]:mb-3 [&>h3]:font-serif [&>h3]:text-xl [&>h3]:font-bold [&>h3]:text-zinc-950 dark:[&>h3]:text-white
          [&>blockquote]:my-8 [&>blockquote]:border-l-4 [&>blockquote]:border-brand-600 [&>blockquote]:bg-zinc-100/80 dark:[&>blockquote]:bg-zinc-900 [&>blockquote]:py-4 [&>blockquote]:pl-5 [&>blockquote]:pr-4 [&>blockquote]:font-serif [&>blockquote]:italic [&>blockquote]:text-zinc-900 dark:[&>blockquote]:text-zinc-100 [&>blockquote]:rounded-r-xl
          [&>ul]:my-6 [&>ul]:list-disc [&>ul]:pl-6 [&>ul>li]:mb-2
          [&>ol]:my-6 [&>ol]:list-decimal [&>ol]:pl-6 [&>ol>li]:mb-2
          [&_a]:font-semibold [&_a]:text-brand-600 dark:[&_a]:text-brand-500 [&_a]:underline [&_a]:underline-offset-4
          [&_figure]:my-8 [&_figure>img]:w-full [&_figure>img]:rounded-xl [&_figcaption]:mt-2.5 [&_figcaption]:text-center [&_figcaption]:text-xs [&_figcaption]:text-zinc-500
          [&_img]:my-6 [&_img]:w-full [&_img]:rounded-xl`}
        dangerouslySetInnerHTML={{ __html: processedHtml }}
      />
    </div>
  );
}
