import { Article, Category, Tag } from '@/types/news';

export const MOCK_CATEGORIES: Category[] = [
  { id: 1, name: 'Nasional', slug: 'nasional', articles_count: 42 },
  { id: 2, name: 'Politik', slug: 'politik', articles_count: 38 },
  { id: 3, name: 'Ekonomi & Bisnis', slug: 'bisnis', articles_count: 51 },
  { id: 4, name: 'Teknologi', slug: 'teknologi', articles_count: 64 },
  { id: 5, name: 'Sains & Iklim', slug: 'sains', articles_count: 19 },
  { id: 6, name: 'Olahraga', slug: 'olahraga', articles_count: 27 },
  { id: 7, name: 'Otomotif', slug: 'otomotif', articles_count: 15 },
];

export const MOCK_TAGS: Tag[] = [
  { id: 1, name: 'Kecerdasan Buatan', slug: 'kecerdasan-buatan', articles_count: 28 },
  { id: 2, name: 'Transisi Energi', slug: 'transisi-energi', articles_count: 21 },
  { id: 3, name: 'Ekonomi Digital', slug: 'ekonomi-digital', articles_count: 19 },
  { id: 4, name: 'Bank Sentral', slug: 'bank-sentral', articles_count: 16 },
  { id: 5, name: 'Semikonduktor', slug: 'semikonduktor', articles_count: 14 },
  { id: 6, name: 'Kereta Cepat', slug: 'kereta-cepat', articles_count: 12 },
  { id: 7, name: 'Keamanan Siber', slug: 'keamanan-siber', articles_count: 11 },
  { id: 8, name: 'Timnas Indonesia', slug: 'timnas-indonesia', articles_count: 10 },
];

const now = Date.now();
const minsAgo = (m: number) => new Date(now - m * 60 * 1000).toISOString();

export const MOCK_ARTICLES: Article[] = [
  {
    id: 101,
    title:
      'Indonesia Resmi Luncurkan Peta Jalan Kedaulatan AI & Pusat Data Hijau Asia Tenggara 2026',
    slug: 'indonesia-resmi-luncurkan-peta-jalan-kedaulatan-ai-pusat-data-hijau-2026',
    excerpt:
      'Pemerintah bersama konsorsium industri teknologi meresmikan kerangka regulasi komputasi awan berenergi panas bumi untuk menopang ekosistem riset AI nasional.',
    content_body: `
      <p><strong>JAKARTA, JOSJISNEWS</strong> — Pemerintah Republik Indonesia secara resmi meluncurkan Peta Jalan Kedaulatan Kecerdasan Buatan (AI) dan Infrastruktur Pusat Data Hijau 2026–2030 dalam konferensi tingkat tinggi ekonomi digital di Jakarta, Selasa (20/10/2026).</p>
      <p>Langkah strategis ini menandai babak baru transformasi industri teknologi nasional, di mana seluruh fasilitas <em>hyperscale data center</em> generasi terbaru diwajibkan memanfaatkan minimal 70 persen bauran energi terbarukan berbasis panas bumi dan surya.</p>
      <h2>Fokus pada Talenta dan Riset Bahasa Lokal</h2>
      <p>Selain pembangunan infrastruktur fisik di kawasan industri Batam, Karawang, dan Sulawesi Tengah, peta jalan tersebut menetapkan pendanaan riset terbuka untuk pengembangan <em>Large Language Model</em> (LLM) berbahasa Indonesia dan bahasa daerah.</p>
      <blockquote>“Kedaulatan digital tidak cukup hanya dengan membangun server di dalam negeri, melainkan memastikan algoritma dan model kecerdasan buatan memahami konteks sosial, hukum, serta kekayaan bahasa Nusantara,” ujar tim perumus kebijakan teknologi nasional dalam keterangan resminya.</blockquote>
      <figure>
        <img src="https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1200&q=80" alt="Fasilitas ruang server pusat data modern" />
        <figcaption>Ilustrasi infrastruktur ruang server pusat data berpendingin cairan generasi baru. (Foto: Unsplash/Taylor Vick)</figcaption>
      </figure>
      <h3>Tiga Pilar Utama Regulasi Komputasi Nasional</h3>
      <ul>
        <li><strong>Audit Transparansi Algoritma:</strong> Sistem AI berisiko tinggi di sektor keuangan, kesehatan, dan layanan publik wajib lolos uji keamanan siber dan bias data.</li>
        <li><strong>Insentif Fiskal Energi Bersih:</strong> Pengurangan pajak korporasi bagi operator pusat data yang membangun pembangkit listrik tenaga panas bumi atau mikrohidro mandiri.</li>
        <li><strong>Kemitraan Kampus & Industri:</strong> Beasiswa penuh bagi 15.000 insinyur semikonduktor, peneliti <em>machine learning</em>, dan arsitek keamanan jaringan.</li>
      </ul>
      <p>Para pelaku industri menilai kebijakan ini akan menarik arus investasi asing langsung (FDI) senilai lebih dari US$ 14,5 miliar dalam tiga tahun ke depan, sekaligus memperkuat posisi Indonesia sebagai hub digital utama di kawasan Asia Tenggara.</p>
    `,
    featured_image_url:
      'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1400&q=80',
    caption_image:
      'Ilustrasi chip pemrosesan AI dan jaringan serat optik berkecepatan tinggi di pusat riset teknologi Jakarta. (Foto: JosjisNews/Arif Rahman)',
    is_headline: true,
    is_trending: true,
    views_count: 48920,
    recent_views_count: 19450,
    reading_time_minutes: 4,
    published_at: minsAgo(18),
    updated_at: minsAgo(5),
    author: { id: 1, name: 'Arif Rahman Hakim', role: 'editor' },
    category: { id: 4, name: 'Teknologi', slug: 'teknologi' },
    tags: [MOCK_TAGS[0], MOCK_TAGS[1], MOCK_TAGS[4]],
  },
  {
    id: 102,
    title:
      'Bank Sentral Pertahankan Suku Bunga Acuan di Tengah Penguatan Rupiah dan Surplus Neraca Dagang',
    slug: 'bank-sentral-pertahankan-suku-bunga-acuan-penguatan-rupiah',
    excerpt:
      'Keputusan Rapat Dewan Gubernur diambil untuk menjaga stabilitas nilai tukar sekaligus mendorong penyaluran kredit sektor manufaktur berorientasi ekspor.',
    content_body: `
      <p><strong>JAKARTA, JOSJISNEWS</strong> — Rapat Dewan Gubernur Bank Sentral memutuskan untuk mempertahankan suku bunga acuan menyusul tren penguatan nilai tukar rupiah selama enam pekan berturut-turut.</p>
      <p>Surplus neraca perdagangan yang didorong oleh ekspor produk hilirisasi bernilai tambah tinggi menjadi bantalan utama ketahanan eksternal ekonomi domestik.</p>
      <blockquote>“Fokus kebijakan moneter pada kuartal keempat tahun ini adalah memastikan likuiditas perbankan mengalir deras ke sektor riil tanpa memicu tekanan inflasi pangan,” jelas laporan kebijakan moneter terbaru.</blockquote>
    `,
    featured_image_url:
      'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80',
    caption_image:
      'Layar pergerakan indeks saham dan nilai tukar mata uang di kawasan SCBD Jakarta. (Foto: JosjisNews/Nadia Putri)',
    is_headline: true,
    is_trending: true,
    views_count: 35210,
    recent_views_count: 14300,
    reading_time_minutes: 3,
    published_at: minsAgo(42),
    updated_at: minsAgo(30),
    author: { id: 2, name: 'Nadia Putri Kusuma', role: 'journalist' },
    category: { id: 3, name: 'Ekonomi & Bisnis', slug: 'bisnis' },
    tags: [MOCK_TAGS[2], MOCK_TAGS[3]],
  },
  {
    id: 103,
    title:
      'Parlemen Bahas RUU Perlindungan Data Lintas Batas dan Standar Enkripsi Layanan Publik',
    slug: 'parlemen-bahas-ruu-perlindungan-data-lintas-batas-enkripsi-publik',
    excerpt:
      'Komisi terkait menekankan pentingnya arsitektur zero-trust dan audit berkala pada seluruh instansi pengelola data kependudukan dan finansial.',
    content_body: `
      <p><strong>JAKARTA, JOSJISNEWS</strong> — Pembahasan revisi regulasi keamanan siber dan perlindungan data lintas batas memasuki tahap sinkronisasi pasal-pasal krusial bersama pakar kriptografi dan asosiasi industri.</p>
      <p>Setiap penyelenggara sistem elektronik strategis diwajibkan menerapkan enkripsi ujung-ke-ujung serta melaporkan insiden anomali trafik dalam waktu kurang dari 3x24 jam.</p>
    `,
    featured_image_url:
      'https://images.unsplash.com/photo-1541872703-74c5e44368f9?auto=format&fit=crop&w=1200&q=80',
    caption_image:
      'Suasana rapat dengar pendapat umum mengenai regulasi perlindungan data siber. (Foto: JosjisNews/Bagus Pratama)',
    is_headline: true,
    is_trending: true,
    views_count: 29800,
    recent_views_count: 11200,
    reading_time_minutes: 4,
    published_at: minsAgo(75),
    author: { id: 3, name: 'Bagus Pratama', role: 'journalist' },
    category: { id: 2, name: 'Politik', slug: 'politik' },
    tags: [MOCK_TAGS[6], MOCK_TAGS[0]],
  },
  {
    id: 104,
    title:
      'Jaringan Kereta Cepat Fase Dua Mulai Uji Dinamis Jalur Pegunungan dengan Teknologi Kemudi Otomatis',
    slug: 'jaringan-kereta-cepat-fase-dua-uji-dinamis-jalur-pegunungan',
    excerpt:
      'Rangkaian kereta generasi terbaru dilengkapi sensor seismik real-time dan sistem pengereman regeneratif hemat energi.',
    content_body: `
      <p><strong>BANDUNG, JOSJISNEWS</strong> — Uji dinamis jaringan kereta cepat fase lanjutan berjalan mulus melintasi terowongan kembar terpanjang di Jawa Barat.</p>
    `,
    featured_image_url:
      'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=1200&q=80',
    caption_image:
      'Rangkaian kereta cepat saat menjalani pengujian kecepatan tinggi di jalur layang. (Foto: JosjisNews/Raka Wijaya)',
    is_headline: true,
    is_trending: true,
    views_count: 24150,
    recent_views_count: 9840,
    reading_time_minutes: 3,
    published_at: minsAgo(110),
    author: { id: 4, name: 'Raka Wijaya', role: 'journalist' },
    category: { id: 1, name: 'Nasional', slug: 'nasional' },
    tags: [MOCK_TAGS[5], MOCK_TAGS[1]],
  },
  {
    id: 105,
    title:
      'Terobosan Baterai Solid-State Berbasis Nikel Silikat Mampu Isi Daya 80 Persen dalam 9 Menit',
    slug: 'terobosan-baterai-solid-state-nikel-silikat-isi-daya-9-menit',
    excerpt:
      'Kolaborasi peneliti baterai nasional dan manufaktur otomotif berhasil meningkatkan kepadatan energi hingga 420 Wh/kg tanpa risiko thermal runaway.',
    content_body: `
      <p><strong>SURABAYA, JOSJISNEWS</strong> — Tim konsorsium riset penyimpanan energi berhasil memproduksi prototipe sel baterai solid-state generasi baru yang memangkas waktu pengisian daya kendaraan listrik secara drastis.</p>
    `,
    featured_image_url:
      'https://images.unsplash.com/photo-1593941707882-a5bba14938c7?auto=format&fit=crop&w=1200&q=80',
    caption_image:
      'Pengujian pengisian daya ultra-cepat pada fasilitas laboratorium kendaraan listrik. (Foto: JosjisNews/Sinta Dewi)',
    is_headline: true,
    is_trending: true,
    views_count: 21900,
    recent_views_count: 8650,
    reading_time_minutes: 5,
    published_at: minsAgo(145),
    author: { id: 5, name: 'Sinta Dewi Larasati', role: 'editor' },
    category: { id: 4, name: 'Teknologi', slug: 'teknologi' },
    tags: [MOCK_TAGS[1], MOCK_TAGS[4]],
  },
  {
    id: 106,
    title:
      'Reformasi Tata Kelola Logistik Pelabuhan Pangkas Waktu Bongkar Muat Kontainer Jadi 18 Jam',
    slug: 'reformasi-tata-kelola-logistik-pelabuhan-pangkas-waktu-bongkar-muat',
    excerpt:
      'Integrasi sistem kepabeanan berbasis AI dan derek otomatis di lima pelabuhan utama berhasil menekan biaya logistik antarpulau.',
    content_body: `<p><strong>SURABAYA, JOSJISNEWS</strong> — Digitalisasi ekosistem pelabuhan terpadu mencatat efisiensi signifikan sepanjang semester kedua tahun ini.</p>`,
    featured_image_url:
      'https://images.unsplash.com/photo-1494412574643-ff11b0a5c1c3?auto=format&fit=crop&w=1200&q=80',
    caption_image: 'Aktivitas bongkar muat peti kemas di terminal pelabuhan otomatis.',
    is_headline: false,
    is_trending: false,
    views_count: 14200,
    recent_views_count: 4200,
    reading_time_minutes: 3,
    published_at: minsAgo(190),
    author: { id: 2, name: 'Nadia Putri Kusuma', role: 'journalist' },
    category: { id: 3, name: 'Ekonomi & Bisnis', slug: 'bisnis' },
    tags: [MOCK_TAGS[2]],
  },
  {
    id: 107,
    title:
      'KPU dan Komisi Pemerintahan Sepakati Digitalisasi Rekapitulasi Suara Berbasis Kriptografi Terbuka',
    slug: 'kpu-komisi-pemerintahan-sepakati-digitalisasi-rekapitulasi-kriptografi',
    excerpt:
      'Masyarakat dapat memverifikasi keaslian dokumen penghitungan suara secara real-time melalui tanda tangan digital publik.',
    content_body: `<p><strong>JAKARTA, JOSJISNEWS</strong> — Transparansi penyelenggaraan pemilu daerah diperkuat melalui adopsi verifikasi kriptografi terbuka.</p>`,
    featured_image_url:
      'https://images.unsplash.com/photo-1575320181282-9afab399332c?auto=format&fit=crop&w=1200&q=80',
    caption_image: 'Rapat koordinasi pembaruan sistem informasi kepemiluan.',
    is_headline: false,
    is_trending: false,
    views_count: 12800,
    recent_views_count: 3900,
    reading_time_minutes: 4,
    published_at: minsAgo(230),
    author: { id: 3, name: 'Bagus Pratama', role: 'journalist' },
    category: { id: 2, name: 'Politik', slug: 'politik' },
    tags: [MOCK_TAGS[6]],
  },
  {
    id: 108,
    title:
      'Arsitektur Chip RISC-V Open Source Mulai Diadopsi Startup Perangkat IoT Industri di Bandung',
    slug: 'arsitektur-chip-risc-v-open-source-diadopsi-startup-iot-bandung',
    excerpt:
      'Desain mikroprosesor bebas lisensi memungkinkan insinyur lokal merancang sensor pertanian presisi dan pemantau jaringan listrik pintar.',
    content_body: `<p><strong>BANDUNG, JOSJISNEWS</strong> — Ekosistem desain semikonduktor tanah air tumbuh pesat berkat kolaborasi kampus teknik dan fabless startup.</p>`,
    featured_image_url:
      'https://images.unsplash.com/photo-1517077304055-6e89abbf09b0?auto=format&fit=crop&w=1200&q=80',
    caption_image: 'Papan sirkuit mikroprosesor pada laboratorium perangkat keras.',
    is_headline: false,
    is_trending: false,
    views_count: 11900,
    recent_views_count: 3500,
    reading_time_minutes: 4,
    published_at: minsAgo(270),
    author: { id: 1, name: 'Arif Rahman Hakim', role: 'editor' },
    category: { id: 4, name: 'Teknologi', slug: 'teknologi' },
    tags: [MOCK_TAGS[4], MOCK_TAGS[0]],
  },
  {
    id: 109,
    title:
      'Emiten Energi Terbarukan Catat Lonjakan Laba Bersih 34 Persen Berkat Kontrak Listrik Industri',
    slug: 'emiten-energi-terbarukan-catat-lonjakan-laba-bersih-34-persen',
    excerpt:
      'Permintaan sertifikat energi bersih dari kawasan industri manufaktur mendorong ekspansi kapasitas pembangkit surya atap.',
    content_body: `<p><strong>JAKARTA, JOSJISNEWS</strong> — Kinerja keuangan emiten energi hijau melampaui konsensus analis pada kuartal ketiga tahun ini.</p>`,
    featured_image_url:
      'https://images.unsplash.com/photo-1509391366360-2e959784a276?auto=format&fit=crop&w=1200&q=80',
    caption_image: 'Hamparan panel surya skala utilitas.',
    is_headline: false,
    is_trending: false,
    views_count: 10400,
    recent_views_count: 3100,
    reading_time_minutes: 3,
    published_at: minsAgo(310),
    author: { id: 2, name: 'Nadia Putri Kusuma', role: 'journalist' },
    category: { id: 3, name: 'Ekonomi & Bisnis', slug: 'bisnis' },
    tags: [MOCK_TAGS[1], MOCK_TAGS[2]],
  },
  {
    id: 110,
    title:
      'Diplomasi Maritim ASEAN Sepakati Koridor Pelayaran Hijau dan Sistem Peringatan Dini Cuaca Ekstrem',
    slug: 'diplomasi-maritim-asean-sepakati-koridor-pelayaran-hijau',
    excerpt:
      'Pertukaran data satelit meteorologi antinegara anggota akan melindungi jalur perdagangan strategis dan nelayan tradisional.',
    content_body: `<p><strong>BALI, JOSJISNEWS</strong> — Para menteri luar negeri dan transportasi kawasan menyepakati protokol keamanan maritim terpadu.</p>`,
    featured_image_url:
      'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80',
    caption_image: 'Pertemuan delegasi maritim kawasan Asia Tenggara.',
    is_headline: false,
    is_trending: false,
    views_count: 9700,
    recent_views_count: 2800,
    reading_time_minutes: 3,
    published_at: minsAgo(360),
    author: { id: 3, name: 'Bagus Pratama', role: 'journalist' },
    category: { id: 2, name: 'Politik', slug: 'politik' },
    tags: [MOCK_TAGS[1]],
  },
];
