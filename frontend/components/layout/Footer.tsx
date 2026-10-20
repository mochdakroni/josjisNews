import Link from 'next/link';
import { Globe, Mail, ShieldCheck, Share2, MessageSquare } from 'lucide-react';
import { Category } from '@/types/news';

interface FooterProps {
  categories: Category[];
}

export default function Footer({ categories }: FooterProps) {
  const currentYear = new Date().getFullYear();

  return (
    <footer
      id="pedoman-media-siber"
      className="mt-16 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-950 text-zinc-300"
    >
      <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 gap-10 md:grid-cols-2 lg:grid-cols-4">
          {/* Kolom 1: Identitas Portal */}
          <div className="space-y-4">
            <Link href="/" className="flex items-center gap-2">
              <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 font-serif text-lg font-black text-white">
                J
              </span>
              <span className="font-serif text-2xl font-black tracking-tight text-white">
                JOSJIS<span className="text-brand-500">NEWS</span>
              </span>
            </Link>
            <p className="text-sm leading-relaxed text-zinc-400">
              Portal kabar digital mandhiri sing nyuguhke berita nganggo data
              cetha, kupasan jero, karo info paling anyar sing bener, blaka, lan
              ora berat sebelah.
            </p>
            <div className="flex items-center gap-3 pt-1">
              <a
                href="https://x.com"
                target="_blank"
                rel="noreferrer"
                aria-label="X / Twitter"
                className="rounded-full bg-zinc-900 p-2 text-zinc-400 hover:bg-brand-600 hover:text-white transition-colors"
              >
                <Share2 className="h-4 w-4" />
              </a>
              <a
                href="https://instagram.com"
                target="_blank"
                rel="noreferrer"
                aria-label="Instagram"
                className="rounded-full bg-zinc-900 p-2 text-zinc-400 hover:bg-brand-600 hover:text-white transition-colors"
              >
                <Globe className="h-4 w-4" />
              </a>
              <a
                href="https://youtube.com"
                target="_blank"
                rel="noreferrer"
                aria-label="YouTube"
                className="rounded-full bg-zinc-900 p-2 text-zinc-400 hover:bg-brand-600 hover:text-white transition-colors"
              >
                <MessageSquare className="h-4 w-4" />
              </a>
              <a
                href="mailto:redaksi@josjisnews.com"
                aria-label="Email Redaksi"
                className="rounded-full bg-zinc-900 p-2 text-zinc-400 hover:bg-brand-600 hover:text-white transition-colors"
              >
                <Mail className="h-4 w-4" />
              </a>
            </div>
          </div>

          {/* Kolom 2: Kanal Berita */}
          <div>
            <h3 className="text-xs font-bold uppercase tracking-wider text-white">
              Kanal Kabar
            </h3>
            <ul className="mt-4 grid grid-cols-2 gap-2 text-sm text-zinc-400">
              {categories.slice(0, 8).map((cat) => (
                <li key={cat.id}>
                  <Link
                    href={`/?category=${cat.slug}`}
                    className="hover:text-brand-500 transition-colors"
                  >
                    {cat.name}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Kolom 3: Informasi Redaksi */}
          <div>
            <h3 className="text-xs font-bold uppercase tracking-wider text-white">
              Babagan Awak Dewe & Redaksi
            </h3>
            <ul className="mt-4 space-y-2.5 text-sm text-zinc-400">
              <li>
                <Link href="#tentang-kami" className="hover:text-white transition-colors">
                  Susunan Wong Redaksi
                </Link>
              </li>
              <li>
                <Link href="#pedoman-media-siber" className="hover:text-white transition-colors">
                  Aturan Media Siber
                </Link>
              </li>
              <li>
                <Link href="#kode-etik" className="hover:text-white transition-colors">
                  Kode Etik Jurnalistik
                </Link>
              </li>
              <li>
                <Link href="#info-iklan" className="hover:text-white transition-colors">
                  Info Pasang Iklan & Kerja Bareng
                </Link>
              </li>
              <li>
                <Link href="#kebijakan-privasi" className="hover:text-white transition-colors">
                  Aturan Privasi & Hak Jawab
                </Link>
              </li>
            </ul>
          </div>

          {/* Kolom 4: Kepatuhan Dewan Pers & Media Siber */}
          <div className="rounded-xl border border-zinc-800 bg-zinc-900/60 p-4">
            <div className="flex items-center gap-2 text-emerald-400">
              <ShieldCheck className="h-5 w-5 shrink-0" />
              <span className="text-xs font-bold uppercase tracking-wider">
                Pathokan Jurnalisme
              </span>
            </div>
            <p className="mt-2 text-xs leading-relaxed text-zinc-400">
              Kabeh tulisan lan kabar ning JosjisNews digarap manut Undang-Undang
              No. 40 Taun 1999 babagan Pers lan Aturan Media Siber sing ditetepke
              Dewan Pers.
            </p>
            <p className="mt-3 text-xs text-zinc-500">
              Wadul & Hak Jawab:{' '}
              <a
                href="mailto:redaksi@josjisnews.com"
                className="font-medium text-zinc-300 underline"
              >
                redaksi@josjisnews.com
              </a>
            </p>
          </div>
        </div>

        {/* Bottom Copyright */}
        <div className="mt-10 flex flex-col items-center justify-between gap-4 border-t border-zinc-800/80 pt-6 text-xs text-zinc-500 sm:flex-row">
          <p>© {currentYear} PT Josjis Media Nusantara. Kabeh Hak Cipta Dilindhungi Undang-Undang.</p>
          <p>Disengkuyung karo Laravel 11 REST API & Next.js App Router</p>
        </div>
      </div>
    </footer>
  );
}
