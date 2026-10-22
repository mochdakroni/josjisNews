import type { MetadataRoute } from 'next';
import { SITE_URL } from '@/lib/api';

/**
 * Konfigurasi standar robots.txt untuk Portal Berita Publik (`/robots.txt`)
 * Mengizinkan perayapan penuh oleh Googlebot, Googlebot-News, dan mesin pencari utama.
 */
export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: '*',
        allow: ['/', '/artikel/'],
        disallow: ['/api/', '/admin/', '/_next/'],
      },
      {
        userAgent: 'Googlebot-News',
        allow: ['/', '/artikel/'],
      },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
    host: SITE_URL,
  };
}
