import React from 'react';

type AdVariant = 'leaderboard' | 'in-feed' | 'sidebar';

interface AdSlotProps {
  variant: AdVariant;
  label?: string;
  className?: string;
}

/**
 * Komponen Placeholder Iklan dengan dimensi min-height eksplisit
 * untuk menjamin Cumulative Layout Shift (CLS) = 0 saat script iklan dimuat.
 */
export default function AdSlot({
  variant,
  label = 'ADVERTISEMENT',
  className = '',
}: AdSlotProps) {
  const variantStyles: Record<AdVariant, { wrapper: string; sizeText: string }> = {
    leaderboard: {
      // 728x90 / 970x90 pada Desktop, 320x100 pada Mobile
      wrapper: 'w-full min-h-[100px] md:min-h-[90px] max-w-5xl mx-auto',
      sizeText: 'Leaderboard 970×90 / 320×100',
    },
    'in-feed': {
      // Banner native di sela-sela seksi berita
      wrapper: 'w-full min-h-[120px] md:min-h-[140px]',
      sizeText: 'In-Feed Native Banner',
    },
    sidebar: {
      // 300x250 / 300x600 pada kolom kanan
      wrapper: 'w-full min-h-[250px] lg:min-h-[300px]',
      sizeText: 'Medium Rectangle 300×250',
    },
  };

  const current = variantStyles[variant];

  return (
    <aside
      aria-label={label}
      className={`my-4 flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-zinc-800 bg-zinc-100/80 dark:bg-zinc-900/60 px-4 py-3 text-center transition-colors ${current.wrapper} ${className}`}
    >
      <span className="text-[10px] font-semibold uppercase tracking-[0.2em] text-zinc-400 dark:text-zinc-500">
        {label}
      </span>
      <p className="mt-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
        Lapak Iklan Responsif ({current.sizeText})
      </p>
    </aside>
  );
}
