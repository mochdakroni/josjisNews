import { Sparkles } from 'lucide-react';
import { Article } from '@/types/news';
import ArticleCard from './ArticleCard';

interface HeadlineHeroGridProps {
  headlines: Article[];
}

/**
 * Grid Headline Hero:
 * - 1 Berita Utama berukuran besar di kolom kiri (LCP Priority Image)
 * - 4 Berita Sekunder di kolom kanan
 */
export default function HeadlineHeroGrid({ headlines }: HeadlineHeroGridProps) {
  if (!headlines || headlines.length === 0) return null;

  const mainHeadline = headlines[0];
  const secondaryHeadlines = headlines.slice(1, 5);

  return (
    <section aria-labelledby="headline-section-title" className="py-4">
      <div className="mb-4 flex items-center justify-between border-b-2 border-zinc-900 dark:border-zinc-100 pb-2">
        <div className="flex items-center gap-2">
          <Sparkles className="h-5 w-5 text-brand-600" />
          <h1
            id="headline-section-title"
            className="text-sm font-black uppercase tracking-wider text-zinc-900 dark:text-white"
          >
            Kabar Utama (Headline)
          </h1>
        </div>
        <span className="text-xs font-medium text-zinc-500 dark:text-zinc-400">
          Pilihan Redaksi Dina Iki
        </span>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
        {/* Kiri: 1 Berita Utama Berukuran Besar (7 Kolom pada Desktop) */}
        <div className="lg:col-span-7 flex">
          <ArticleCard article={mainHeadline} variant="hero" priority={true} />
        </div>

        {/* Kanan: 4 Berita Sekunder (5 Kolom pada Desktop) */}
        <div className="lg:col-span-5 flex flex-col justify-between divide-y divide-zinc-200/70 dark:divide-zinc-800/70 rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 bg-white dark:bg-zinc-900 p-3 sm:p-4">
          {secondaryHeadlines.map((article, idx) => (
            <div key={article.id} className={idx === 0 ? 'pb-2' : 'py-2'}>
              <ArticleCard
                article={article}
                variant="horizontal"
                priority={idx < 2}
              />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
