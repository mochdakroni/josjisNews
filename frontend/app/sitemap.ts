import type { MetadataRoute } from 'next';
import { getActiveCategories, getArticles, SITE_URL } from '@/lib/api';

/**
 * Sitemap XML Dinamis (`/sitemap.xml`)
 * Memuat otomatis hingga 1.000 artikel terbaru yang sudah terbit dari REST API
 * beserta halaman beranda dan kanal kategori aktif.
 */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const PER_PAGE = 50; // Maksimal per_page sesuai validasi ArticleIndexRequest di backend
  const MAX_PAGES = 20; // 20 halaman x 50 artikel = 1.000 artikel terbaru

  // 1. Ambil halaman pertama + daftar kategori aktif secara paralel
  const [firstPageRes, categories] = await Promise.all([
    getArticles({ page: 1, per_page: PER_PAGE, sort: 'latest' }),
    getActiveCategories(),
  ]);

  const allArticles = [...(firstPageRes.data || [])];
  const lastPage = Math.min(firstPageRes.meta?.last_page ?? 1, MAX_PAGES);

  // 2. Jika terdapat lebih dari 1 halaman, ambil halaman berikutnya hingga maksimal 1.000 artikel
  if (lastPage > 1) {
    const remainingPages = Array.from(
      { length: lastPage - 1 },
      (_, idx) => idx + 2
    );

    const responses = await Promise.all(
      remainingPages.map((page) =>
        getArticles({ page, per_page: PER_PAGE, sort: 'latest' })
      )
    );

    for (const res of responses) {
      if (res.data?.length) {
        allArticles.push(...res.data);
      }
    }
  }

  // 3. Susun entri URL statis & kategori
  const staticAndCategoryEntries: MetadataRoute.Sitemap = [
    {
      url: SITE_URL,
      lastModified: new Date(),
      changeFrequency: 'always',
      priority: 1.0,
    },
    ...categories.map((cat) => ({
      url: `${SITE_URL}/?category=${cat.slug}`,
      lastModified: new Date(),
      changeFrequency: 'hourly' as const,
      priority: 0.8,
    })),
  ];

  // 4. Susun hingga 1.000 entri URL artikel berita terbaru
  const articleEntries: MetadataRoute.Sitemap = allArticles
    .slice(0, 1000)
    .map((article) => ({
      url: `${SITE_URL}/artikel/${article.slug}`,
      lastModified: new Date(article.updated_at || article.published_at),
      changeFrequency: 'daily' as const,
      priority: article.is_headline ? 0.9 : 0.7,
    }));

  return [...staticAndCategoryEntries, ...articleEntries];
}
