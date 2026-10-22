import type { Metadata } from 'next';
import { Inter, Merriweather } from 'next/font/google';
import './globals.css';
import Navbar from '@/components/layout/Navbar';
import BreakingNewsTicker from '@/components/layout/BreakingNewsTicker';
import Footer from '@/components/layout/Footer';
import { getActiveCategories, getTrendingArticles } from '@/lib/api';

const inter = Inter({
  subsets: ['latin'],
  variable: '--font-inter',
  display: 'swap',
});

const merriweather = Merriweather({
  subsets: ['latin'],
  weight: ['400', '700', '900'],
  variable: '--font-serif',
  display: 'swap',
});

export const metadata: Metadata = {
  title: {
    default: 'JosjisNews — Bloko Sutho | Event Komunitas, Personal Branding, Lifestyle & Seputar Gen Z',
    template: '%s | JosjisNews — Bloko Sutho',
  },
  description:
    'JosjisNews (Bloko Sutho) — Portal kabar digital sing ngrembug event-event komunitas, personal branding, lifestyle, lan seputar Gen Z nganggo basa Jawa ngoko.',
};

export default async function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  // Fetch paralel di sisi server untuk Navbar Categories & Breaking News Ticker
  const [categories, tickerArticles] = await Promise.all([
    getActiveCategories(),
    getTrendingArticles(24, 6),
  ]);

  return (
    <html
      lang="id"
      suppressHydrationWarning
      className={`${inter.variable} ${merriweather.variable}`}
    >
      <body className="min-h-screen flex flex-col font-sans antialiased selection:bg-brand-600 selection:text-white">
        {/* Global Sticky Header / Navbar */}
        <Navbar categories={categories} />

        {/* Breaking News Running Ticker di bawah Navbar */}
        <BreakingNewsTicker articles={tickerArticles} />

        {/* Main Content Container */}
        <div className="flex-1">{children}</div>

        {/* Global Footer */}
        <Footer categories={categories} />
      </body>
    </html>
  );
}
