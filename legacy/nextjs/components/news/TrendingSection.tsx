import Link from 'next/link';
import { TrendingUp, Eye } from 'lucide-react';
import { Article, Tag } from '@/types/news';
import { formatRelativeTime } from '@/lib/formatters';

interface TrendingSectionProps {
  articles: Article[];
  popularTags?: Tag[];
}

/**
 * Seksi "Berita Terpopuler / Trending" (1–5 dengan nomor urut mencolok ala The Verge / Kompas)
 */
export default function TrendingSection({
  articles,
  popularTags = [],
}: TrendingSectionProps) {
  const topFive = articles.slice(0, 5);

  return (
    <div className="rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 bg-white dark:bg-zinc-900 p-5 shadow-sm">
      <div className="flex items-center gap-2 border-b-2 border-brand-600 pb-3">
        <TrendingUp className="h-5 w-5 text-brand-600" />
        <h2 className="text-sm font-black uppercase tracking-wider text-zinc-900 dark:text-white">
          Paling Rame 48 Jam Iki
        </h2>
      </div>

      <ol className="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800/80">
        {topFive.map((article, index) => {
          const rankNumber = String(index + 1).padStart(2, '0');
          return (
            <li
              key={article.id}
              className="group flex items-start gap-4 py-3.5 first:pt-1 last:pb-1"
            >
              {/* Nomor Urut Mencolok */}
              <span
                aria-hidden="true"
                className="font-serif text-3xl font-black leading-none text-zinc-200 dark:text-zinc-800 group-hover:text-brand-600 dark:group-hover:text-brand-500 transition-colors select-none"
              >
                {rankNumber}
              </span>

              <div className="flex-1 min-w-0">
                {article.category && (
                  <Link
                    href={`/?category=${article.category.slug}`}
                    className="text-[10px] font-bold uppercase tracking-wider text-brand-600 dark:text-brand-500 hover:underline"
                  >
                    {article.category.name}
                  </Link>
                )}

                <Link href={`/artikel/${article.slug}`}>
                  <h3 className="mt-0.5 font-serif text-sm sm:text-base font-bold leading-snug text-zinc-900 dark:text-zinc-100 group-hover:text-brand-600 dark:group-hover:text-brand-500 transition-colors line-clamp-2">
                    {article.title}
                  </h3>
                </Link>

                <div className="mt-1.5 flex items-center gap-3 text-[11px] text-zinc-400 dark:text-zinc-500">
                  <span>{formatRelativeTime(article.published_at)}</span>
                  {typeof article.recent_views_count === 'number' &&
                  article.recent_views_count > 0 ? (
                    <span className="inline-flex items-center gap-1 font-medium text-zinc-500 dark:text-zinc-400">
                      <Eye className="h-3 w-3" />
                      {article.recent_views_count.toLocaleString('id-ID')}
                    </span>
                  ) : null}
                </div>
              </div>
            </li>
          );
        })}
      </ol>

      {/* Widget Topik / Tag Populer */}
      {popularTags.length > 0 && (
        <div className="mt-6 border-t border-zinc-100 dark:border-zinc-800 pt-4">
          <h3 className="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
            Topik Lagi Rame
          </h3>
          <div className="mt-2.5 flex flex-wrap gap-1.5">
            {popularTags.map((tag) => (
              <Link
                key={tag.id}
                href={`/?tag=${tag.slug}`}
                className="rounded-full border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 px-3 py-1 text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:border-brand-600 hover:text-brand-600 dark:hover:border-brand-500 dark:hover:text-brand-400 transition-colors"
              >
                #{tag.name}
              </Link>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
