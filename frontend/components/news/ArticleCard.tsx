import Image from 'next/image';
import Link from 'next/link';
import { Clock, User } from 'lucide-react';
import { Article } from '@/types/news';
import { formatRelativeTime, IMAGE_BLUR_DATA_URL } from '@/lib/formatters';

export type ArticleCardVariant = 'hero' | 'vertical' | 'horizontal' | 'compact';

interface ArticleCardProps {
  article: Article;
  variant?: ArticleCardVariant;
  priority?: boolean;
}

const FALLBACK_IMAGE =
  'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1200&q=80';

export default function ArticleCard({
  article,
  variant = 'vertical',
  priority = false,
}: ArticleCardProps) {
  const imageUrl = article.featured_image_url || FALLBACK_IMAGE;
  const relativeTime = formatRelativeTime(article.published_at);

  // 1. Varian HERO (Berita Utama berukuran besar di sisi kiri grid Headline)
  if (variant === 'hero') {
    return (
      <article className="group relative flex flex-col overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 bg-white dark:bg-zinc-900 shadow-sm">
        <Link
          href={`/artikel/${article.slug}`}
          className="relative aspect-video w-full overflow-hidden bg-zinc-200 dark:bg-zinc-800"
        >
          <Image
            src={imageUrl}
            alt={article.title}
            fill
            priority={priority}
            sizes="(max-width: 1024px) 100vw, 60vw"
            placeholder="blur"
            blurDataURL={IMAGE_BLUR_DATA_URL}
            className="object-cover transition-transform duration-500 group-hover:scale-105"
          />
          {article.category && (
            <span className="absolute left-4 top-4 rounded-md bg-brand-600 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white shadow">
              {article.category.name}
            </span>
          )}
        </Link>

        <div className="flex flex-1 flex-col justify-between p-5 sm:p-6">
          <div>
            <Link href={`/artikel/${article.slug}`}>
              <h2 className="font-serif text-xl sm:text-2xl lg:text-3xl font-bold leading-tight text-zinc-900 dark:text-zinc-50 group-hover:text-brand-600 dark:group-hover:text-brand-500 transition-colors">
                {article.title}
              </h2>
            </Link>

            {article.excerpt && (
              <p className="mt-3 line-clamp-3 text-sm sm:text-base leading-relaxed text-zinc-600 dark:text-zinc-400">
                {article.excerpt}
              </p>
            )}
          </div>

          <div className="mt-5 flex flex-wrap items-center gap-4 border-t border-zinc-100 dark:border-zinc-800/80 pt-4 text-xs text-zinc-500 dark:text-zinc-400">
            {article.author && (
              <span className="inline-flex items-center gap-1.5 font-medium text-zinc-700 dark:text-zinc-300">
                <User className="h-3.5 w-3.5 text-brand-600" />
                {article.author.name}
              </span>
            )}
            <time
              dateTime={article.published_at}
              className="inline-flex items-center gap-1"
            >
              <Clock className="h-3.5 w-3.5" />
              {relativeTime}
            </time>
          </div>
        </div>
      </article>
    );
  }

  // 2. Varian HORIZONTAL (Thumbnail 16:9 di kiri, teks di kanan — cocok untuk sekunder & indeks)
  if (variant === 'horizontal') {
    return (
      <article className="group grid grid-cols-12 items-start gap-3.5 sm:gap-4 rounded-xl p-2 hover:bg-zinc-50 dark:hover:bg-zinc-900/60 transition-colors">
        <Link
          href={`/artikel/${article.slug}`}
          className="relative col-span-5 sm:col-span-4 aspect-video w-full overflow-hidden rounded-lg bg-zinc-200 dark:bg-zinc-800"
        >
          <Image
            src={imageUrl}
            alt={article.title}
            fill
            priority={priority}
            loading={priority ? undefined : 'lazy'}
            sizes="(max-width: 640px) 40vw, 240px"
            placeholder="blur"
            blurDataURL={IMAGE_BLUR_DATA_URL}
            className="object-cover transition-transform duration-300 group-hover:scale-105"
          />
        </Link>

        <div className="col-span-7 sm:col-span-8 flex flex-col justify-between">
          {article.category && (
            <Link
              href={`/?category=${article.category.slug}`}
              className="w-fit text-[11px] font-bold uppercase tracking-wider text-brand-600 dark:text-brand-500 hover:underline"
            >
              {article.category.name}
            </Link>
          )}

          <Link href={`/artikel/${article.slug}`} className="mt-1">
            <h3 className="font-serif text-sm sm:text-base font-bold leading-snug text-zinc-900 dark:text-zinc-100 line-clamp-2 group-hover:text-brand-600 dark:group-hover:text-brand-500 transition-colors">
              {article.title}
            </h3>
          </Link>

          <div className="mt-2 flex flex-wrap items-center gap-2.5 text-[11px] text-zinc-500 dark:text-zinc-400">
            {article.author && (
              <span className="font-medium text-zinc-700 dark:text-zinc-300">
                {article.author.name}
              </span>
            )}
            <span>•</span>
            <time dateTime={article.published_at}>{relativeTime}</time>
          </div>
        </div>
      </article>
    );
  }

  // 3. Varian COMPACT (Tanpa gambar atau sangat ringkas untuk daftar suplemen kategori)
  if (variant === 'compact') {
    return (
      <article className="group border-b border-zinc-200/70 dark:border-zinc-800/70 py-3 last:border-none">
        {article.category && (
          <span className="text-[10px] font-bold uppercase tracking-wider text-brand-600 dark:text-brand-500">
            {article.category.name}
          </span>
        )}
        <Link href={`/artikel/${article.slug}`}>
          <h4 className="mt-0.5 font-serif text-sm font-semibold leading-snug text-zinc-900 dark:text-zinc-100 group-hover:text-brand-600 transition-colors line-clamp-2">
            {article.title}
          </h4>
        </Link>
        <div className="mt-1.5 flex items-center gap-2 text-[11px] text-zinc-400">
          {article.author && <span>{article.author.name}</span>}
          <span>•</span>
          <time dateTime={article.published_at}>{relativeTime}</time>
        </div>
      </article>
    );
  }

  // 4. Varian VERTICAL Default (Card standar dengan rasio gambar 16:9 di atas)
  return (
    <article className="group flex flex-col overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-800/80 bg-white dark:bg-zinc-900 transition-shadow hover:shadow-md">
      <Link
        href={`/artikel/${article.slug}`}
        className="relative aspect-video w-full overflow-hidden bg-zinc-200 dark:bg-zinc-800"
      >
        <Image
          src={imageUrl}
          alt={article.title}
          fill
          priority={priority}
          loading={priority ? undefined : 'lazy'}
          sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
          placeholder="blur"
          blurDataURL={IMAGE_BLUR_DATA_URL}
          className="object-cover transition-transform duration-500 group-hover:scale-105"
        />
        {article.category && (
          <span className="absolute left-3 top-3 rounded bg-zinc-950/80 backdrop-blur-sm px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white">
            {article.category.name}
          </span>
        )}
      </Link>

      <div className="flex flex-1 flex-col justify-between p-4">
        <div>
          <Link href={`/artikel/${article.slug}`}>
            <h3 className="font-serif text-base sm:text-lg font-bold leading-snug text-zinc-900 dark:text-zinc-100 line-clamp-2 group-hover:text-brand-600 dark:group-hover:text-brand-500 transition-colors">
              {article.title}
            </h3>
          </Link>

          {article.excerpt && (
            <p className="mt-2 line-clamp-2 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400">
              {article.excerpt}
            </p>
          )}
        </div>

        <div className="mt-4 flex items-center justify-between border-t border-zinc-100 dark:border-zinc-800/80 pt-3 text-xs text-zinc-500 dark:text-zinc-400">
          <span className="truncate font-medium text-zinc-700 dark:text-zinc-300">
            {article.author?.name || 'Redaksi'}
          </span>
          <time dateTime={article.published_at} className="shrink-0">
            {relativeTime}
          </time>
        </div>
      </div>
    </article>
  );
}
