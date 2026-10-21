import Link from 'next/link';
import { ArrowRight } from 'lucide-react';
import { Article } from '@/types/news';
import ArticleCard from './ArticleCard';

export interface CategorySpotItem {
  title: string;
  slug: string;
  accentColor?: string;
  articles: Article[];
}

interface CategorySpotlightProps {
  spotlights: CategorySpotItem[];
}

/**
 * Seksi "Kategori Pilihan" (misal: Politik, Teknologi, Bisnis)
 * Menggunakan tata letak card bervariasi:
 * - 1 artikel unggulan bergambar 16:9 di bagian atas
 * - 3 artikel lanjutan dengan gaya compact di bawahnya
 */
export default function CategorySpotlight({ spotlights }: CategorySpotlightProps) {
  if (!spotlights || spotlights.length === 0) return null;

  return (
    <section aria-label="Sorotan Kategori Pilihan" className="py-6">
      <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        {spotlights.map((block) => {
          const featured = block.articles[0];
          const subArticles = block.articles.slice(1, 4);

          return (
            <div
              key={block.slug}
              className="flex flex-col rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 bg-white dark:bg-zinc-900 p-4 sm:p-5 shadow-sm"
            >
              {/* Header Kategori */}
              <div className="mb-4 flex items-center justify-between border-b-2 border-zinc-900 dark:border-zinc-100 pb-2.5">
                <h2 className="text-sm font-black uppercase tracking-wider text-zinc-900 dark:text-white">
                  {block.title}
                </h2>
                <Link
                  href={`/?category=${block.slug}`}
                  className="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 dark:text-brand-500 hover:underline"
                >
                  <span>Delok Kabeh</span>
                  <ArrowRight className="h-3.5 w-3.5" />
                </Link>
              </div>

              {/* 1 Card Utama Vertical */}
              {featured ? (
                <>
                  <ArticleCard article={featured} variant="vertical" />
                  {/* Daftar artikel lanjutan dengan tata letak Compact */}
                  <div className="mt-3 flex-1 divide-y divide-zinc-100 dark:divide-zinc-800/70">
                    {subArticles.map((item) => (
                      <ArticleCard
                        key={item.id}
                        article={item}
                        variant="compact"
                      />
                    ))}
                  </div>
                </>
              ) : (
                <p className="py-8 text-center text-xs text-zinc-400">
                  Durung ana kabar ning kanal {block.title}.
                </p>
              )}
            </div>
          );
        })}
      </div>
    </section>
  );
}
