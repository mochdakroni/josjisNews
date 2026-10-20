'use client';

import React, { useState } from 'react';
import { Loader2, Newspaper, RefreshCw } from 'lucide-react';
import { Article, PaginationMeta } from '@/types/news';
import ArticleCard from './ArticleCard';

interface LatestNewsFeedProps {
  initialArticles: Article[];
  initialMeta?: PaginationMeta;
  activeCategory?: string;
  activeTag?: string;
  searchQuery?: string;
}

/**
 * Seksi "Indeks Berita Terkini" dengan dukungan Load More / Infinite Scroll
 * yang mengambil halaman berikutnya dari GET /api/v1/articles.
 */
export default function LatestNewsFeed({
  initialArticles,
  initialMeta,
  activeCategory,
  activeTag,
  searchQuery,
}: LatestNewsFeedProps) {
  const [articles, setArticles] = useState<Article[]>(initialArticles);
  const [page, setPage] = useState<number>(initialMeta?.current_page ?? 1);
  const [lastPage, setLastPage] = useState<number>(initialMeta?.last_page ?? 1);
  const [isLoading, setIsLoading] = useState<boolean>(false);

  const hasMore = page < lastPage;

  const loadNextPage = async () => {
    if (isLoading || !hasMore) return;

    try {
      setIsLoading(true);
      const nextPage = page + 1;
      const baseUrl =
        process.env.NEXT_PUBLIC_API_BASE_URL?.replace(/\/$/, '') ||
        'http://localhost:8000/api/v1';

      const params = new URLSearchParams({
        page: String(nextPage),
        per_page: String(initialMeta?.per_page ?? 10),
      });

      if (activeCategory) params.set('category', activeCategory);
      if (activeTag) params.set('tag', activeTag);
      if (searchQuery) params.set('search', searchQuery);

      const response = await fetch(`${baseUrl}/articles?${params.toString()}`);
      if (!response.ok) throw new Error('Gagal memuat berita berikutnya');

      const json = await response.json();
      const newItems: Article[] = json.data || [];

      setArticles((prev) => [...prev, ...newItems]);
      setPage(json.meta?.current_page ?? nextPage);
      setLastPage(json.meta?.last_page ?? lastPage);
    } catch {
      // Biarkan state tetap aman bila gagal
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <section aria-labelledby="latest-news-heading">
      <div className="mb-4 flex items-center justify-between border-b-2 border-zinc-900 dark:border-zinc-100 pb-2">
        <div className="flex items-center gap-2">
          <Newspaper className="h-5 w-5 text-brand-600" />
          <h2
            id="latest-news-heading"
            className="text-sm font-black uppercase tracking-wider text-zinc-900 dark:text-white"
          >
            Daftaran Kabar Anyar
          </h2>
        </div>

        {(activeCategory || activeTag || searchQuery) && (
          <span className="rounded-full bg-brand-50 dark:bg-brand-900/30 px-3 py-0.5 text-xs font-semibold text-brand-600 dark:text-brand-400">
            Saringan Aktif:{' '}
            {searchQuery
              ? `"${searchQuery}"`
              : activeCategory
              ? `Kanal ${activeCategory}`
              : `#${activeTag}`}
          </span>
        )}
      </div>

      {articles.length === 0 ? (
        <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-10 text-center">
          <p className="text-sm text-zinc-500">
            Durung ana kabar sing pas karo saringan iki.
          </p>
        </div>
      ) : (
        <div className="divide-y divide-zinc-200/70 dark:divide-zinc-800/70 rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 bg-white dark:bg-zinc-900 p-3 sm:p-5 shadow-sm">
          {articles.map((article) => (
            <div key={article.id} className="py-2 first:pt-0 last:pb-0">
              <ArticleCard article={article} variant="horizontal" />
            </div>
          ))}
        </div>
      )}

      {/* Tombol Muat Lebih Banyak / Pagination */}
      {hasMore && (
        <div className="mt-6 flex justify-center">
          <button
            type="button"
            onClick={loadNextPage}
            disabled={isLoading}
            className="inline-flex items-center gap-2 rounded-full border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-zinc-800 dark:text-zinc-200 shadow-sm hover:border-brand-600 hover:text-brand-600 disabled:opacity-50 transition-all"
          >
            {isLoading ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin text-brand-600" />
                <span>Lagi Ngetokke Kabar...</span>
              </>
            ) : (
              <>
                <RefreshCw className="h-4 w-4" />
                <span>Tokne Kabar Liyane (Kaca {page + 1}/{lastPage})</span>
              </>
            )}
          </button>
        </div>
      )}
    </section>
  );
}
