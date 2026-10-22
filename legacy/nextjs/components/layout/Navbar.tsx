'use client';

import React, { useEffect, useState, useRef } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  Search,
  Sun,
  Moon,
  Menu,
  X,
  TrendingUp,
  Loader2,
  ArrowUpRight,
} from 'lucide-react';
import { Article, Category } from '@/types/news';
import { formatRelativeTime } from '@/lib/formatters';

interface NavbarProps {
  categories: Category[];
}

export default function Navbar({ categories }: NavbarProps) {
  const router = useRouter();
  const [isDark, setIsDark] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [keyword, setKeyword] = useState('');
  const [results, setResults] = useState<Article[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Sinkronisasi tema Dark/Light Mode dengan localStorage & preferensi sistem
  useEffect(() => {
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const shouldBeDark = savedTheme === 'dark' || (!savedTheme && prefersDark);

    setIsDark(shouldBeDark);
    document.documentElement.classList.toggle('dark', shouldBeDark);
  }, []);

  const toggleTheme = () => {
    const nextDark = !isDark;
    setIsDark(nextDark);
    document.documentElement.classList.toggle('dark', nextDark);
    localStorage.setItem('theme', nextDark ? 'dark' : 'light');
  };

  // Fokus otomatis ke input pencarian ketika modal/bar dibuka (atau shortcut Cmd/Ctrl + K)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setSearchOpen((prev) => !prev);
      } else if (e.key === 'Escape') {
        setSearchOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  useEffect(() => {
    if (searchOpen) {
      setTimeout(() => searchInputRef.current?.focus(), 50);
    }
  }, [searchOpen]);

  // Live instant search ke endpoint GET /api/v1/articles?search=...
  useEffect(() => {
    const trimmed = keyword.trim();
    if (trimmed.length < 2) {
      setResults([]);
      setIsSearching(false);
      return;
    }

    const controller = new AbortController();
    const timer = setTimeout(async () => {
      try {
        setIsSearching(true);
        const baseUrl =
          process.env.NEXT_PUBLIC_API_BASE_URL?.replace(/\/$/, '') ||
          'http://localhost:8000/api/v1';

        const res = await fetch(
          `${baseUrl}/articles?search=${encodeURIComponent(trimmed)}&per_page=5`,
          { signal: controller.signal }
        );
        if (res.ok) {
          const json = await res.json();
          setResults(json.data || []);
        }
      } catch {
        // Ignore abort error
      } finally {
        setIsSearching(false);
      }
    }, 300);

    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [keyword]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!keyword.trim()) return;
    setSearchOpen(false);
    router.push(`/?search=${encodeURIComponent(keyword.trim())}`);
  };

  const d = new Date();
  const dDays = ['Minggu', 'Senen', 'Selasa', 'Rebo', 'Kemis', 'Jemuwah', 'Setu'];
  const dMonths = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  const todayFormatted = `${dDays[d.getDay()]}, ${d.getDate()} ${dMonths[d.getMonth()]} ${d.getFullYear()}`;

  return (
    <header className="sticky top-0 z-50 border-b border-zinc-200 dark:border-zinc-800 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-md">
      {/* Top Utility Bar */}
      <div className="hidden border-b border-zinc-100 dark:border-zinc-900 bg-zinc-50/70 dark:bg-zinc-900/50 py-1.5 text-xs text-zinc-500 dark:text-zinc-400 md:block">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
          <span>{todayFormatted}</span>
          <div className="flex items-center gap-4">
            <Link href="/?sort=popular" className="hover:text-brand-600 transition-colors">
              Paling Rame Diwaca
            </Link>
            <span>•</span>
            <Link href="#pedoman-media-siber" className="hover:text-brand-600 transition-colors">
              Aturan Media Siber
            </Link>
          </div>
        </div>
      </div>

      {/* Main Header Row */}
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
        {/* Left: Mobile Menu Button + Brand Logo */}
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            aria-label="Bukak menu"
            className="rounded-lg p-2 text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 lg:hidden"
          >
            {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>

          <Link href="/" className="flex items-center gap-2">
            <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 font-serif text-xl font-black tracking-tighter text-white shadow-sm">
              J
            </span>
            <div className="flex flex-col">
              <span className="font-serif text-2xl font-black tracking-tight text-zinc-900 dark:text-white leading-none">
                JOSJIS<span className="text-brand-600">NEWS</span>
              </span>
              <span className="text-[10px] font-bold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-500">
                Bloko Sutho
              </span>
            </div>
          </Link>
        </div>

        {/* Center: Desktop Category Navigation */}
        <nav aria-label="Navigasi Kategori Utama" className="hidden lg:flex items-center gap-1">
          <Link
            href="/"
            className="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors"
          >
            Omah
          </Link>
          {categories.slice(0, 7).map((cat) => (
            <Link
              key={cat.id}
              href={`/?category=${cat.slug}`}
              className="rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 hover:text-brand-600 dark:hover:text-brand-500 transition-colors"
            >
              {cat.name}
            </Link>
          ))}
        </nav>

        {/* Right: Interactive Search Button & Theme Toggle */}
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => setSearchOpen(!searchOpen)}
            aria-label="Golek kabar"
            className="flex items-center gap-2 rounded-full border border-zinc-200 dark:border-zinc-800 bg-zinc-100/80 dark:bg-zinc-900 px-3.5 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:border-brand-500 transition-colors"
          >
            <Search className="h-4 w-4 text-zinc-500" />
            <span className="hidden sm:inline">Golek kabar...</span>
            <kbd className="hidden rounded bg-white dark:bg-zinc-800 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-400 border border-zinc-200 dark:border-zinc-700 md:inline-block">
              ⌘K
            </kbd>
          </button>

          <button
            type="button"
            onClick={toggleTheme}
            aria-label="Ganti mode padhang utawa peteng"
            className="rounded-full p-2 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
          >
            {isDark ? (
              <Sun className="h-5 w-5 text-amber-400" />
            ) : (
              <Moon className="h-5 w-5 text-zinc-700" />
            )}
          </button>
        </div>
      </div>

      {/* Expandable Interactive Search Drawer */}
      {searchOpen && (
        <div className="border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 shadow-xl">
          <div className="mx-auto max-w-3xl px-4 py-4 sm:px-6">
            <form onSubmit={handleSearchSubmit} className="relative flex items-center">
              <Search className="absolute left-3.5 h-5 w-5 text-zinc-400" />
              <input
                ref={searchInputRef}
                type="search"
                value={keyword}
                onChange={(e) => setKeyword(e.target.value)}
                placeholder="Ketik tembung kunci kabar, tokoh, utawa topik terus pencet Enter..."
                className="w-full rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 py-3 pl-11 pr-24 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
              />
              {isSearching && (
                <Loader2 className="absolute right-20 h-4 w-4 animate-spin text-brand-600" />
              )}
              <button
                type="button"
                onClick={() => setSearchOpen(false)}
                className="absolute right-3 rounded-lg px-2.5 py-1 text-xs font-medium text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800"
              >
                ESC
              </button>
            </form>

            {/* Instant Search Results Preview */}
            {results.length > 0 && (
              <div className="mt-3 divide-y divide-zinc-100 dark:divide-zinc-800/80 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 p-2">
                {results.map((item) => (
                  <Link
                    key={item.id}
                    href={`/artikel/${item.slug}`}
                    onClick={() => setSearchOpen(false)}
                    className="flex items-start justify-between gap-3 rounded-lg p-2.5 hover:bg-white dark:hover:bg-zinc-800 transition-colors"
                  >
                    <div>
                      <span className="text-[11px] font-bold uppercase text-brand-600">
                        {item.category?.name || 'Kabar'}
                      </span>
                      <h4 className="text-sm font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-1">
                        {item.title}
                      </h4>
                      <span className="text-xs text-zinc-400">
                        {formatRelativeTime(item.published_at)}
                      </span>
                    </div>
                    <ArrowUpRight className="h-4 w-4 shrink-0 text-zinc-400 mt-1" />
                  </Link>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Mobile Navigation Drawer */}
      {mobileMenuOpen && (
        <div className="border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-4 py-4 lg:hidden">
          <div className="flex flex-col space-y-1">
            <Link
              href="/"
              onClick={() => setMobileMenuOpen(false)}
              className="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-900 dark:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800"
            >
              Omah Utama
            </Link>
            {categories.map((cat) => (
              <Link
                key={cat.id}
                href={`/?category=${cat.slug}`}
                onClick={() => setMobileMenuOpen(false)}
                className="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800"
              >
                <span>{cat.name}</span>
                {typeof cat.articles_count === 'number' && (
                  <span className="rounded-full bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-xs text-zinc-500">
                    {cat.articles_count}
                  </span>
                )}
              </Link>
            ))}
          </div>
        </div>
      )}
    </header>
  );
}
