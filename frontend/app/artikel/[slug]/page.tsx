import type { Metadata } from 'next';
import Image from 'next/image';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import {
  ChevronRight,
  Clock,
  Calendar,
  UserCheck,
  Camera,
  Tag as TagIcon,
  Newspaper,
  BookOpen,
} from 'lucide-react';
import AdSlot from '@/components/ads/AdSlot';
import ArticleCard from '@/components/news/ArticleCard';
import FloatingShareBar from '@/components/article/FloatingShareBar';
import ArticleReaderContent from '@/components/article/ArticleReaderContent';
import { getArticleBySlug, getArticles, SITE_URL } from '@/lib/api';
import {
  formatFullDateTime,
  IMAGE_BLUR_DATA_URL,
} from '@/lib/formatters';

interface ArticleDetailPageProps {
  params: Promise<{ slug: string }>;
}

const DEFAULT_OG_IMAGE = `${SITE_URL}/og-default.jpg`;

/**
 * 2. Technical SEO & Social Sharing Otomatis (generateMetadata)
 * Menghasilkan Canonical URL, Open Graph (type=article), dan Twitter Card (summary_large_image).
 */
export async function generateMetadata({
  params,
}: ArticleDetailPageProps): Promise<Metadata> {
  const { slug } = await params;
  const article = await getArticleBySlug(slug);

  if (!article) {
    return {
      title: 'Kabar Ora Ketemu',
      robots: { index: false, follow: false },
    };
  }

  const canonicalUrl = `${SITE_URL}/artikel/${article.slug}`;
  const description =
    article.excerpt ||
    `Waca kabar jangkep babagan "${article.title}" mung ning JosjisNews.`;
  const ogImage = article.featured_image_url || DEFAULT_OG_IMAGE;
  const authorName = article.author?.name || 'Redaksi JosjisNews';
  const tagsList = article.tags?.map((t) => t.name) || [];

  return {
    title: article.title,
    description,
    keywords: tagsList,
    authors: [{ name: authorName }],
    alternates: {
      canonical: canonicalUrl,
    },
    openGraph: {
      type: 'article',
      url: canonicalUrl,
      title: article.title,
      description,
      siteName: 'JosjisNews',
      locale: 'id_ID',
      publishedTime: article.published_at,
      modifiedTime: article.updated_at || article.published_at,
      section: article.category?.name || 'Nasional',
      authors: [authorName],
      tags: tagsList,
      images: [
        {
          url: ogImage,
          width: 1200,
          height: 675,
          alt: article.caption_image || article.title,
        },
      ],
    },
    twitter: {
      card: 'summary_large_image',
      title: article.title,
      description,
      images: [ogImage],
    },
    robots: {
      index: true,
      follow: true,
      'max-image-preview': 'large',
      'max-snippet': -1,
      'max-video-preview': -1,
    },
  };
}

export default async function ArticleDetailPage({
  params,
}: ArticleDetailPageProps) {
  const { slug } = await params;
  const article = await getArticleBySlug(slug);

  if (!article) {
    notFound();
  }

  // Ambil rekomendasi "Berita Terkait" berdasarkan kategori yang sama (kecuali artikel yang sedang dibuka)
  const relatedResponse = await getArticles({
    category: article.category?.slug,
    per_page: 6,
  });

  const relatedArticles = (relatedResponse.data || [])
    .filter((item) => item.slug !== article.slug)
    .slice(0, 4);

  const canonicalUrl = `${SITE_URL}/artikel/${article.slug}`;
  const featuredImage =
    article.featured_image_url ||
    'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1200&q=80';

  const isUpdated =
    article.updated_at &&
    article.published_at &&
    new Date(article.updated_at).getTime() -
      new Date(article.published_at).getTime() >
      60_000;

  // Schema.org NewsArticle & BreadcrumbList Structured Data (JSON-LD) untuk Google News / Rich Snippet
  const newsArticleJsonLd = {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'NewsArticle',
        mainEntityOfPage: {
          '@type': 'WebPage',
          '@id': canonicalUrl,
        },
        headline: article.title,
        description: article.excerpt || article.title,
        image: [featuredImage],
        datePublished: article.published_at,
        dateModified: article.updated_at || article.published_at,
        articleSection: article.category?.name || 'Berita',
        keywords: article.tags?.map((t) => t.name).join(', ') || '',
        author: {
          '@type': 'Person',
          name: article.author?.name || 'Redaksi JosjisNews',
          url: `${SITE_URL}/penulis/${article.author?.id || 1}`,
        },
        publisher: {
          '@type': 'NewsMediaOrganization',
          name: 'JosjisNews',
          url: SITE_URL,
          logo: {
            '@type': 'ImageObject',
            url: `${SITE_URL}/logo.png`,
            width: 600,
            height: 60,
          },
        },
      },
      {
        '@type': 'BreadcrumbList',
        itemListElement: [
          {
            '@type': 'ListItem',
            position: 1,
            name: 'Home',
            item: SITE_URL,
          },
          ...(article.category
            ? [
                {
                  '@type': 'ListItem',
                  position: 2,
                  name: article.category.name,
                  item: `${SITE_URL}/?category=${article.category.slug}`,
                },
                {
                  '@type': 'ListItem',
                  position: 3,
                  name: article.title,
                  item: canonicalUrl,
                },
              ]
            : [
                {
                  '@type': 'ListItem',
                  position: 2,
                  name: article.title,
                  item: canonicalUrl,
                },
              ]),
        ],
      },
    ],
  };

  return (
    <>
      {/* Injeksi JSON-LD Rich Snippet di Server-Side (Escaped '</' untuk mencegah Script Tag Breakout XSS) */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify(newsArticleJsonLd).replace(/</g, '\\u003c'),
        }}
      />

      <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        {/* Slot Iklan Leaderboard Atas */}
        <AdSlot variant="leaderboard" label="SPONSORED LEADERBOARD" />

        {/* 1. Breadcrumb Navigasi (Home > Kategori > Judul) */}
        <nav
          aria-label="Breadcrumb"
          className="mt-4 flex flex-wrap items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400"
        >
          <Link
            href="/"
            className="hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
          >
            Omah
          </Link>

          {article.category && (
            <>
              <ChevronRight className="h-3.5 w-3.5 shrink-0 text-zinc-400" />
              <Link
                href={`/?category=${article.category.slug}`}
                className="font-semibold text-brand-600 dark:text-brand-500 hover:underline"
              >
                {article.category.name}
              </Link>
            </>
          )}

          <ChevronRight className="h-3.5 w-3.5 shrink-0 text-zinc-400" />
          <span
            aria-current="page"
            className="line-clamp-1 max-w-xs sm:max-w-md text-zinc-700 dark:text-zinc-300"
          >
            {article.title}
          </span>
        </nav>

        {/* Grid Utama: Artikel (8 Kolom) & Sidebar Berita Terkait + Iklan (4 Kolom) */}
        <div className="mt-5 grid grid-cols-1 gap-10 lg:grid-cols-12">
          {/* KOLOM KIRI: Artikel Lengkap */}
          <article className="lg:col-span-8">
            {/* Header Metadata Artikel */}
            <header className="border-b border-zinc-200 dark:border-zinc-800 pb-6">
              {article.category && (
                <Link
                  href={`/?category=${article.category.slug}`}
                  className="inline-block rounded-md bg-brand-600 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-sm hover:bg-brand-700 transition-colors"
                >
                  {article.category.name}
                </Link>
              )}

              {/* Judul H1 Besar */}
              <h1 className="mt-4 font-serif text-2xl sm:text-3xl lg:text-4xl font-black leading-[1.2] tracking-tight text-zinc-950 dark:text-white">
                {article.title}
              </h1>

              {/* Excerpt / Deck Subjudul */}
              {article.excerpt && (
                <p className="mt-4 text-base sm:text-lg leading-relaxed text-zinc-600 dark:text-zinc-300">
                  {article.excerpt}
                </p>
              )}

              {/* Bar Informasi Penulis, Waktu Terbit & Update, serta Estimasi Waktu Baca */}
              <div className="mt-6 flex flex-wrap items-center justify-between gap-4 pt-2 text-xs text-zinc-500 dark:text-zinc-400">
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-200 dark:bg-zinc-800 font-serif text-sm font-bold text-zinc-800 dark:text-zinc-200">
                    {(article.author?.name || 'R').charAt(0).toUpperCase()}
                  </div>
                  <div>
                    <div className="flex items-center gap-1.5 font-semibold text-zinc-900 dark:text-zinc-100">
                      <UserCheck className="h-3.5 w-3.5 text-brand-600" />
                      <span>{article.author?.name || 'Redaksi JosjisNews'}</span>
                    </div>
                    <span className="text-[11px] capitalize text-zinc-500">
                      {article.author?.role === 'editor'
                        ? 'Editor Redaksi'
                        : 'Wartawan JosjisNews'}
                    </span>
                  </div>
                </div>

                <div className="flex flex-col sm:items-end gap-1">
                  <div className="inline-flex items-center gap-1.5">
                    <Calendar className="h-3.5 w-3.5 text-brand-600" />
                    <time dateTime={article.published_at}>
                      Tayang: {formatFullDateTime(article.published_at)}
                    </time>
                  </div>

                  {isUpdated && (
                    <div className="inline-flex items-center gap-1.5 text-[11px] text-zinc-400">
                      <Clock className="h-3 w-3" />
                      <time dateTime={article.updated_at}>
                        Dianyari: {formatFullDateTime(article.updated_at)}
                      </time>
                    </div>
                  )}

                  {article.reading_time_minutes && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium text-brand-600 dark:text-brand-400">
                      <BookOpen className="h-3 w-3" />
                      Kira-kira diwaca {article.reading_time_minutes} menit
                    </span>
                  )}
                </div>
              </div>
            </header>

            {/* Featured Image Resolusi Tinggi (Priority LCP) + Caption & Atribusi Fotografer */}
            <figure className="my-6 overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-100 dark:bg-zinc-900">
              <div className="relative aspect-video w-full">
                <Image
                  src={featuredImage}
                  alt={article.caption_image || article.title}
                  fill
                  priority
                  sizes="(max-width: 1024px) 100vw, 800px"
                  placeholder="blur"
                  blurDataURL={IMAGE_BLUR_DATA_URL}
                  className="object-cover"
                />
              </div>
              {article.caption_image && (
                <figcaption className="flex items-start gap-2 border-t border-zinc-200/60 dark:border-zinc-800 px-4 py-3 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">
                  <Camera className="mt-0.5 h-4 w-4 shrink-0 text-zinc-400" />
                  <span>{article.caption_image}</span>
                </figcaption>
              )}
            </figure>

            {/* Area Konten: Sticky Share Bar di Sisi Kiri + Isi Artikel & Font Sizer di Kanan */}
            <div className="mt-8 flex flex-col gap-8 lg:flex-row">
              {/* Tombol Share Sosial Media Sticky / Melayang */}
              <div className="shrink-0">
                <FloatingShareBar title={article.title} url={canonicalUrl} />
              </div>

              {/* Body Artikel + Kontrol Ukuran Font (A- / A+) */}
              <div className="min-w-0 flex-1">
                <ArticleReaderContent
                  htmlContent={
                    article.content_body ||
                    `<p>${article.excerpt || ''}</p>`
                  }
                />

                {/* Seksi Tagar / Tag Terkait */}
                {article.tags && article.tags.length > 0 && (
                  <div className="mt-10 border-t border-zinc-200 dark:border-zinc-800 pt-6">
                    <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                      <TagIcon className="h-4 w-4 text-brand-600" />
                      <span>Topik / Tagar Sing Nyambung</span>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {article.tags.map((tag) => (
                        <Link
                          key={tag.id}
                          href={`/?tag=${tag.slug}`}
                          className="rounded-full border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3.5 py-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:border-brand-600 hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-950/40 dark:hover:text-brand-400 transition-colors"
                        >
                          #{tag.name}
                        </Link>
                      ))}
                    </div>
                  </div>
                )}

                {/* Slot Iklan In-Feed di Bawah Artikel */}
                <div className="mt-8">
                  <AdSlot variant="in-feed" label="ADVERTISEMENT • IN-ARTICLE" />
                </div>
              </div>
            </div>
          </article>

          {/* KOLOM KANAN (SIDEBAR): Rekomendasi Berita Terkait & Slot Iklan 300x250 */}
          <aside className="lg:col-span-4">
            <div className="sticky top-24 space-y-6">
              {/* Slot Iklan Banner 300x250 */}
              <AdSlot variant="sidebar" label="ADVERTISEMENT • 300×250" />

              {/* Rekomendasi Berita Terkait (Kategori yang Sama) */}
              <div className="rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                <div className="flex items-center gap-2 border-b-2 border-zinc-900 dark:border-zinc-100 pb-3">
                  <Newspaper className="h-5 w-5 text-brand-600" />
                  <h2 className="text-sm font-black uppercase tracking-wider text-zinc-900 dark:text-white">
                    Kabar Sing Nyambung
                  </h2>
                </div>

                {relatedArticles.length > 0 ? (
                  <div className="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800/80">
                    {relatedArticles.map((related) => (
                      <div
                        key={related.id}
                        className="py-3 first:pt-0 last:pb-0"
                      >
                        <ArticleCard article={related} variant="horizontal" />
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="py-6 text-center text-xs text-zinc-500">
                    Durung ana kabar liyane sing nyambung ning kanal iki.
                  </p>
                )}
              </div>
            </div>
          </aside>
        </div>
      </main>
    </>
  );
}
