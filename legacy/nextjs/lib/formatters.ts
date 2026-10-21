/**
 * Mengubah ISO timestamp menjadi waktu relatif Bahasa Indonesia
 * (contoh: "Baru saja", "15 menit yang lalu", "3 jam yang lalu", "2 hari yang lalu")
 */
export function formatRelativeTime(isoDate?: string | null): string {
  if (!isoDate) return '';

  const date = new Date(isoDate);
  const now = new Date();
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

  if (Number.isNaN(diffInSeconds) || diffInSeconds < 60) {
    return 'Mentas wae';
  }

  const minutes = Math.floor(diffInSeconds / 60);
  if (minutes < 60) {
    return `${minutes} menit kepungkur`;
  }

  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return `${hours} jam kepungkur`;
  }

  const days = Math.floor(hours / 24);
  if (days < 7) {
    return `${days} dina kepungkur`;
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date);
}

/**
 * Format tanggal dan waktu lengkap (WIB) untuk header halaman baca artikel
 * Contoh: "Selasa, 20 Oktober 2026 • 08:40 WIB"
 */
export function formatFullDateTime(isoDate?: string | null): string {
  if (!isoDate) return '';
  const date = new Date(isoDate);
  if (Number.isNaN(date.getTime())) return '';

  const datePart = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
  }).format(date);

  const timePart = new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'Asia/Jakarta',
  }).format(date);

  return `${datePart} • ${timePart} WIB`;
}

/**
 * Base64 SVG shimmer ringan untuk blurDataURL pada Next.js <Image placeholder="blur" />
 * Menjaga CLS = 0 dan pengalaman loading visual yang halus.
 */
export const IMAGE_BLUR_DATA_URL =
  'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iIzFlMjkzYiIvPjwvc3ZnPg==';
