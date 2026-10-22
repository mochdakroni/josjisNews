<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Tim Redaksi & Pengguna CMS (6 Akun Realistis)
        $users = [
            'pemred' => User::updateOrCreate(
                ['email' => 'admin@josjisnews.com'],
                [
                    'name' => 'Dr. Hendra Wijaya (Pemred)',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::SUPER_ADMIN,
                    'email_verified_at' => now(),
                ]
            ),
            'editor_tekno' => User::updateOrCreate(
                ['email' => 'editor@josjisnews.com'],
                [
                    'name' => 'Arif Rahman Hakim',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::EDITOR,
                    'email_verified_at' => now(),
                ]
            ),
            'editor_ekonomi' => User::updateOrCreate(
                ['email' => 'sinta.editor@josjisnews.com'],
                [
                    'name' => 'Sinta Dewi Larasati',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::EDITOR,
                    'email_verified_at' => now(),
                ]
            ),
            'jurnalis_ekonomi' => User::updateOrCreate(
                ['email' => 'jurnalis@josjisnews.com'],
                [
                    'name' => 'Nadia Putri Kusuma',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::JOURNALIST,
                    'email_verified_at' => now(),
                ]
            ),
            'jurnalis_politik' => User::updateOrCreate(
                ['email' => 'bagus@josjisnews.com'],
                [
                    'name' => 'Bagus Pratama Putra',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::JOURNALIST,
                    'email_verified_at' => now(),
                ]
            ),
            'jurnalis_nasional' => User::updateOrCreate(
                ['email' => 'raka@josjisnews.com'],
                [
                    'name' => 'Raka Aditya Mahendra',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::JOURNALIST,
                    'email_verified_at' => now(),
                ]
            ),
        ];

        // 2. Seed Kategori Kanal Berita
        $categoriesData = [
            'nasional' => [
                'name' => 'Nasional',
                'slug' => 'nasional',
                'description' => 'Berita terkini seputar peristiwa nasional, kebijakan publik, infrastruktur, dan pembangunan daerah.',
                'is_active' => true,
            ],
            'politik' => [
                'name' => 'Politik & Hukum',
                'slug' => 'politik',
                'description' => 'Liputan mendalam parlemen DPR RI, kebijakan kementerian, konstitusi, dan diplomasi luar negeri.',
                'is_active' => true,
            ],
            'bisnis' => [
                'name' => 'Ekonomi & Bisnis',
                'slug' => 'bisnis',
                'description' => 'Analisis makroekonomi, kebijakan moneter Bank Indonesia, bursa saham IDX, perbankan, dan hilirisasi.',
                'is_active' => true,
            ],
            'teknologi' => [
                'name' => 'Teknologi & Sains',
                'slug' => 'teknologi',
                'description' => 'Perkembangan kecerdasan buatan (AI), pusat data, semikonduktor, keamanan siber, dan inovasi sains.',
                'is_active' => true,
            ],
            'otomotif' => [
                'name' => 'Otomotif & EV',
                'slug' => 'otomotif',
                'description' => 'Industri kendaraan listrik (Electric Vehicle), ekosistem baterai nikel, dan transportasi masa depan.',
                'is_active' => true,
            ],
            'olahraga' => [
                'name' => 'Olahraga',
                'slug' => 'olahraga',
                'description' => 'Kabar terbaru Timnas Sepak Bola Indonesia, kualifikasi Piala Dunia, dan turnamen internasional.',
                'is_active' => true,
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $key => $cat) {
            $categories[$key] = Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        // 3. Seed Tag Populer
        $tagsData = [
            'ai' => ['name' => 'Kecerdasan Buatan', 'slug' => 'kecerdasan-buatan'],
            'datacenter' => ['name' => 'Pusat Data Hijau', 'slug' => 'pusat-data-hijau'],
            'bi' => ['name' => 'Bank Indonesia', 'slug' => 'bank-indonesia'],
            'rupiah' => ['name' => 'Nilai Tukar Rupiah', 'slug' => 'nilai-tukar-rupiah'],
            'pdp' => ['name' => 'Perlindungan Data Pribadi', 'slug' => 'perlindungan-data-pribadi'],
            'siber' => ['name' => 'Keamanan Siber', 'slug' => 'keamanan-siber'],
            'kereta' => ['name' => 'Kereta Cepat', 'slug' => 'kereta-cepat'],
            'ev' => ['name' => 'Kendaraan Listrik', 'slug' => 'kendaraan-listrik'],
            'nikel' => ['name' => 'Hilirisasi Nikel', 'slug' => 'hilirisasi-nikel'],
            'timnas' => ['name' => 'Timnas Indonesia', 'slug' => 'timnas-indonesia'],
            'energi' => ['name' => 'Transisi Energi', 'slug' => 'transisi-energi'],
            'chip' => ['name' => 'Semikonduktor', 'slug' => 'semikonduktor'],
        ];

        $tags = [];
        foreach ($tagsData as $key => $t) {
            $tags[$key] = Tag::updateOrCreate(['slug' => $t['slug']], $t);
        }

        // 4. Seed Artikel Berita Realistis
        $articlesSeed = [
            [
                'user_id' => $users['editor_tekno']->id,
                'category_id' => $categories['teknologi']->id,
                'title' => 'Indonesia Resmi Luncurkan Peta Jalan Kedaulatan AI & Pusat Data Hijau Asia Tenggara 2026',
                'slug' => 'indonesia-resmi-luncurkan-peta-jalan-kedaulatan-ai-pusat-data-hijau-2026',
                'excerpt' => 'Pemerintah bersama konsorsium industri teknologi meresmikan kerangka regulasi komputasi awan berenergi panas bumi untuk menopang ekosistem riset AI nasional.',
                'content_body' => '<p class="lead-paragraph"><strong>JAKARTA, JOSJISNEWS</strong> — Pemerintah Republik Indonesia secara resmi meluncurkan Peta Jalan Kedaulatan Kecerdasan Buatan (AI) dan Infrastruktur Pusat Data Hijau 2026–2030 dalam konferensi tingkat tinggi ekonomi digital di Jakarta, Selasa (20/10/2026).</p><p>Langkah strategis ini menandai babak baru transformasi industri teknologi nasional, di mana seluruh fasilitas <em>hyperscale data center</em> generasi terbaru diwajibkan memanfaatkan minimal 70 persen bauran energi terbarukan berbasis panas bumi dan surya.</p><h2>Fokus pada Talenta dan Riset Bahasa Lokal</h2><p>Selain pembangunan infrastruktur fisik di kawasan industri Batam, Karawang, dan Sulawesi Tengah, peta jalan tersebut menetapkan pendanaan riset terbuka untuk pengembangan <em>Large Language Model</em> (LLM) berbahasa Indonesia dan bahasa daerah.</p><blockquote><p>“Kedaulatan digital tidak cukup hanya dengan membangun server di dalam negeri, melainkan memastikan algoritma dan model kecerdasan buatan memahami konteks sosial, hukum, serta kekayaan bahasa Nusantara,” ujar tim perumus kebijakan teknologi nasional.</p></blockquote>',
                'featured_image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1400&q=80',
                'caption_image' => 'Ilustrasi chip pemrosesan AI dan jaringan serat optik berkecepatan tinggi di pusat riset teknologi Jakarta. (Foto: JosjisNews/Arif Rahman)',
                'status' => ArticleStatus::PUBLISHED,
                'is_headline' => true,
                'is_trending' => true,
                'views_count' => 54820,
                'published_at' => now()->subMinutes(25),
                'tags' => [$tags['ai']->id, $tags['datacenter']->id, $tags['energi']->id, $tags['chip']->id],
            ],
            [
                'user_id' => $users['jurnalis_ekonomi']->id,
                'category_id' => $categories['bisnis']->id,
                'title' => 'Bank Indonesia Pertahankan BI-Rate di Level 5,75 Persen Seiring Penguatan Rupiah dan Surplus Dagang',
                'slug' => 'bank-indonesia-pertahankan-bi-rate-penguatan-rupiah-surplus-dagang',
                'excerpt' => 'Rapat Dewan Gubernur BI memastikan kebijakan moneter tetap pro-stability sembari memperlonggar insentif likuiditas makroprudensial bagi sektor hilirisasi dan perumahan.',
                'content_body' => '<p class="lead-paragraph"><strong>JAKARTA, JOSJISNEWS</strong> — Rapat Dewan Gubernur (RDG) Bank Indonesia memutuskan untuk mempertahankan suku bunga acuan BI-Rate sebesar 5,75 persen seiring penguatan nilai tukar Rupiah ke Rp15.180 per dolar AS.</p><p>Surplus neraca perdagangan sebesar US$ 4,12 miliar pada bulan lalu menjadi penopang utama ketahanan eksternal ekonomi domestik.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80',
                'caption_image' => 'Layar pergerakan IHSG dan nilai tukar rupiah di Bursa Efek Indonesia, Jakarta. (Foto: JosjisNews/Nadia Putri)',
                'status' => ArticleStatus::PUBLISHED,
                'is_headline' => true,
                'is_trending' => true,
                'views_count' => 41290,
                'published_at' => now()->subMinutes(55),
                'tags' => [$tags['bi']->id, $tags['rupiah']->id, $tags['nikel']->id],
            ],
            [
                'user_id' => $users['jurnalis_politik']->id,
                'category_id' => $categories['politik']->id,
                'title' => 'DPR dan Pemerintah Sepakati Pembentukan Badan Pengawas Perlindungan Data Pribadi Independen',
                'slug' => 'dpr-pemerintah-sepakati-badan-pengawas-perlindungan-data-pribadi-independen',
                'excerpt' => 'Lembaga pengawas baru memiliki kewenangan penuh melakukan audit forensik siber dan menjatuhkan sanksi denda administratif hingga 2 persen dari pendapatan tahunan korporasi.',
                'content_body' => '<p class="lead-paragraph"><strong>JAKARTA, JOSJISNEWS</strong> — Komisi I DPR RI bersama Kementerian Komunikasi dan Digital resmi menyepakati aturan pelaksana pembentukan Lembaga Pengawas Perlindungan Data Pribadi (PDP).</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?auto=format&fit=crop&w=1200&q=80',
                'caption_image' => 'Suasana rapat kerja pembahasan regulasi keamanan siber di Kompleks Parlemen Senayan, Jakarta. (Foto: JosjisNews/Bagus Pratama)',
                'status' => ArticleStatus::PUBLISHED,
                'is_headline' => true,
                'is_trending' => true,
                'views_count' => 33640,
                'published_at' => now()->subHours(2),
                'tags' => [$tags['pdp']->id, $tags['siber']->id],
            ],
            [
                'user_id' => $users['jurnalis_nasional']->id,
                'category_id' => $categories['nasional']->id,
                'title' => 'Uji Dinamis Kereta Cepat Jakarta–Surabaya Segmen Bandung–Kertajati Tembus Kecepatan 350 Km/Jam',
                'slug' => 'uji-dinamis-kereta-cepat-bandung-kertajati-tembus-350-km-jam',
                'excerpt' => 'Rangkaian kereta inspeksi generasi terbaru berhasil melintasi jalur terowongan kembar dan jembatan tahan gempa dengan tingkat getaran kabin di bawah ambang batas internasional.',
                'content_body' => '<p class="lead-paragraph"><strong>MAJALENGKA, JOSJISNEWS</strong> — Konsorsium perkeretaapian nasional sukses melaksanakan uji dinamis kecepatan penuh (350 km/jam) pada trase perpanjangan Kereta Cepat segmen Padalarang–Kertajati.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=1200&q=80',
                'caption_image' => 'Rangkaian kereta cepat saat menjalani pengujian kecepatan tinggi di jalur layang Jawa Barat. (Foto: JosjisNews/Raka Aditya)',
                'status' => ArticleStatus::PUBLISHED,
                'is_headline' => true,
                'is_trending' => true,
                'views_count' => 29750,
                'published_at' => now()->subHours(3),
                'tags' => [$tags['kereta']->id],
            ],
            [
                'user_id' => $users['editor_ekonomi']->id,
                'category_id' => $categories['otomotif']->id,
                'title' => 'Pabrik Sel Baterai Solid-State Pertama di Karawang Mulai Produksi Massal, Jarak Tempuh EV Tembus 850 Km',
                'slug' => 'pabrik-sel-baterai-solid-state-karawang-produksi-massal-ev-850-km',
                'excerpt' => 'Menggunakan katoda nikel kadar tinggi hasil pemurnian dalam negeri, baterai generasi baru ini mampu mengisi daya dari 10 ke 80 persen hanya dalam waktu 9 menit.',
                'content_body' => '<p class="lead-paragraph"><strong>KARAWANG, JOSJISNEWS</strong> — Fasilitas manufaktur sel baterai kendaraan listrik generasi kedua di kawasan industri Karawang resmi memulai produksi komersial baterai berbasis elektrolit padat.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1593941707882-a5bba14938c7?auto=format&fit=crop&w=1200&q=80',
                'caption_image' => 'Lini perakitan otomatis modul baterai kendaraan listrik di kawasan industri Karawang. (Foto: JosjisNews/Sinta Dewi)',
                'status' => ArticleStatus::PUBLISHED,
                'is_headline' => true,
                'is_trending' => true,
                'views_count' => 26410,
                'published_at' => now()->subHours(4),
                'tags' => [$tags['ev']->id, $tags['nikel']->id, $tags['energi']->id],
            ],
        ];

        foreach ($articlesSeed as $item) {
            $tagIds = $item['tags'];
            unset($item['tags']);

            $article = Article::updateOrCreate(['slug' => $item['slug']], $item);
            $article->tags()->sync($tagIds);

            ArticleView::firstOrCreate([
                'article_id' => $article->id,
                'ip_address' => '103.144.12.' . rand(10, 99),
            ], [
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'viewed_at' => now()->subMinutes(rand(5, 180)),
            ]);
        }
    }
}
