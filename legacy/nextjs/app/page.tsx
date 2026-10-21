import AdSlot from '@/components/ads/AdSlot';
import HeadlineHeroGrid from '@/components/news/HeadlineHeroGrid';
import TrendingSection from '@/components/news/TrendingSection';
import CategorySpotlight, {
  CategorySpotItem,
} from '@/components/news/CategorySpotlight';
import LatestNewsFeed from '@/components/news/LatestNewsFeed';
import {
  getHeadlineArticles,
  getTrendingArticles,
  getArticles,
  getPopularTags,
} from '@/lib/api';

interface HomePageProps {
  searchParams?: Promise<{
    category?: string;
    tag?: string;
    search?: string;
    page?: string;
  }>;
}

export default async function HomePage({ searchParams }: HomePageProps) {
  const resolvedParams = (await searchParams) || {};
  const activeCategory = resolvedParams.category;
  const activeTag = resolvedParams.tag;
  const searchQuery = resolvedParams.search;
  const currentPage = Number(resolvedParams.page || 1);

  // SSR Parallel Data Fetching dari REST API Backend (/api/v1/*)
  const [
    headlines,
    trendingArticles,
    popularTags,
    latestArticlesResponse,
    komunitasResponse,
    brandingResponse,
    lifestyleResponse,
    genzResponse,
  ] = await Promise.all([
    getHeadlineArticles(),
    getTrendingArticles(48, 5),
    getPopularTags(10),
    getArticles({
      page: currentPage,
      per_page: 10,
      category: activeCategory,
      tag: activeTag,
      search: searchQuery,
    }),
    getArticles({ category: 'event-komunitas', per_page: 4 }),
    getArticles({ category: 'personal-branding', per_page: 4 }),
    getArticles({ category: 'lifestyle', per_page: 4 }),
    getArticles({ category: 'seputar-gen-z', per_page: 4 }),
  ]);

  const categorySpotlights: CategorySpotItem[] = [
    {
      title: 'Event & Komunitas',
      slug: 'event-komunitas',
      articles: komunitasResponse.data,
    },
    {
      title: 'Personal Branding',
      slug: 'personal-branding',
      articles: brandingResponse.data,
    },
    {
      title: 'Lifestyle & Seputar Gen Z',
      slug: 'seputar-gen-z',
      articles: genzResponse.data.length > 0 ? genzResponse.data : lifestyleResponse.data,
    },
  ];

  const isFiltering = Boolean(activeCategory || activeTag || searchQuery);

  return (
    <main className="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
      {/* 1. Slot Iklan Leaderboard Atas (CLS-Safe) */}
      <AdSlot variant="leaderboard" label="SPONSORED LEADERBOARD" />

      {/* 2. Grid Headline Hero (1 Utama Besar di Kiri + 4 Sekunder di Kanan) */}
      {!isFiltering && <HeadlineHeroGrid headlines={headlines} />}

      {/* 3. Layout Dua Kolom Utama: Indeks Berita Terkini (Kiri) + Trending & Sidebar Ad (Kanan) */}
      <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-12">
        {/* Kolom Kiri (8 Kolom): Indeks Berita Terkini + In-Feed Ad */}
        <div className="lg:col-span-8 space-y-8">
          <LatestNewsFeed
            initialArticles={latestArticlesResponse.data}
            initialMeta={latestArticlesResponse.meta}
            activeCategory={activeCategory}
            activeTag={activeTag}
            searchQuery={searchQuery}
          />

          {/* Slot Iklan In-Feed di tengah alur baca */}
          <AdSlot variant="in-feed" label="ADVERTISEMENT • IN-FEED" />
        </div>

        {/* Kolom Kanan Sticky (4 Kolom): Berita Terpopuler 1-5 & Iklan Sidebar */}
        <aside className="lg:col-span-4">
          <div className="sticky top-24 space-y-6">
            <TrendingSection
              articles={trendingArticles}
              popularTags={popularTags}
            />

            <AdSlot variant="sidebar" label="ADVERTISEMENT • SIDEBAR" />
          </div>
        </aside>
      </div>

      {/* 4. Seksi Kategori Pilihan (Politik, Teknologi, Bisnis) dengan Tata Letak Card Bervariasi */}
      {!isFiltering && <CategorySpotlight spotlights={categorySpotlights} />}
    </main>
  );
}
