import Link from 'next/link';
import { Flame, ChevronRight } from 'lucide-react';
import { Article } from '@/types/news';
import { formatRelativeTime } from '@/lib/formatters';

interface BreakingNewsTickerProps {
  articles: Article[];
}

export default function BreakingNewsTicker({ articles }: BreakingNewsTickerProps) {
  if (!articles || articles.length === 0) return null;

  // Gandakan array untuk efek infinite marquee yang mulus
  const tickerItems = [...articles, ...articles];

  return (
    <div className="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/90 text-xs">
      <div className="mx-auto flex max-w-7xl items-center overflow-hidden px-4 sm:px-6 lg:px-8 h-10">
        {/* Badge Breaking / Terkini */}
        <div className="z-10 flex shrink-0 items-center gap-1.5 rounded-md bg-brand-600 px-2.5 py-1 font-bold uppercase tracking-wider text-white shadow-sm">
          <Flame className="h-3.5 w-3.5 fill-current animate-pulse" />
          <span>Kabar Anyar</span>
        </div>

        {/* Running Text Area */}
        <div className="relative ml-4 flex flex-1 overflow-hidden">
          <div className="flex w-max animate-marquee items-center gap-8 hover:[animation-play-state:paused]">
            {tickerItems.map((item, idx) => (
              <Link
                key={`${item.id}-${idx}`}
                href={`/artikel/${item.slug}`}
                className="group flex items-center gap-2 whitespace-nowrap text-zinc-700 dark:text-zinc-300 hover:text-brand-600 dark:hover:text-brand-500 transition-colors"
              >
                {item.category && (
                  <span className="font-semibold text-brand-600 dark:text-brand-500">
                    [{item.category.name}]
                  </span>
                )}
                <span className="font-medium group-hover:underline">
                  {item.title}
                </span>
                <span className="text-[11px] text-zinc-400 dark:text-zinc-500">
                  • {formatRelativeTime(item.published_at)}
                </span>
                <ChevronRight className="h-3.5 w-3.5 text-zinc-300 dark:text-zinc-700" />
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
