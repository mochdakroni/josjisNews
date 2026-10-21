<?php
/**
 * Isi konten CONTOH untuk pengembangan lokal (fiktif, bukan berita nyata).
 *
 *   php database/seed_demo.php
 *
 * Aman dijalankan berulang: artikel dengan slug yang sama dilewati.
 * JANGAN dipakai di produksi.
 */

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/repository.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();
$note = '<p><em>Catetan: artikel iki conto fiktif kanggo uji coba tampilan, dudu kabar sing nyata.</em></p>';

// [judul, kategori(slug), tags, headline, status, jam kepungkur, views, excerpt, isi]
$articles = [
    ['Pasar Tradisional Solo Saiki Wiwit Nganggo QRIS', 'bisnis', 'UMKM, Solo, QRIS', 1, 'published', 2, 1840,
        'Pedagang pasar mulai ninggalake dhuwit tunai lan nampa pembayaran digital saka pamaca.',
        '<p>Ing pirang-pirang pasar tradisional Solo, pedagang saiki padha nempelake kode QRIS ing lapak. Pembeli mung kudu mindai kode, lan dhuwit langsung mlebu rekening.</p><h2>Kenapa pedagang gelem pindhah?</h2><p>Jare para pedagang, pembayaran digital nyuda risiko kembalian sing kleru lan ngirit wektu nalika pasar lagi rame. Sawetara malah wiwit nampa pesenan liwat WhatsApp.</p><blockquote>Biyen kudu nyepak recehan, saiki cukup ndeleng layar ponsel.</blockquote><h2>Tantangan sing isih ana</h2><ul><li>Sinyal internet ing sawetara blok pasar isih kurang stabil.</li><li>Pedagang sepuh butuh pendhampingan nggunakake aplikasi.</li><li>Biaya transaksi isih dadi pitakonan para pedagang cilik.</li></ul>'],

    ['Persis Solo Menang Tipis Ing Kandhang Dhewe', 'olahraga', 'Bal-balan, Persis', 1, 'published', 5, 1420,
        'Gol ing menit pungkasan nggawa telung poin wigati kanggo tim tuan rumah.',
        '<p>Stadion kebak ing dina Setu bengi nalika tim tuan rumah mungkasi pertandhingan kanthi skor 1-0. Gol tunggal lahir ing menit 89 saka sundulan gelandang tengah.</p><h2>Jalane pertandhingan</h2><p>Paruh pisanan dikuwasani tim tamu, nanging kiper tuan rumah bisa njaga gawang. Sawise rehat, tempo mundhak lan peluang saya akeh.</p><p>Pelatih ngakoni tim isih kudu nandhingi penyelesaian akhir, nanging bungah merga poin kebak bisa dijaga.</p>'],

    ['Aplikasi Seluler Mbantu Petani Ngramal Wektu Panen', 'teknologi', 'Pertanian, Aplikasi', 0, 'published', 9, 960,
        'Data cuaca lan kahanan lemah dipadhukake supaya petani bisa milih wektu panen sing paling pas.',
        '<p>Sekelompok pangembang lokal nggawe aplikasi sing nggunakake data cuaca lan sensor lemah kanggo ngramal wektu panen. Petani mung kudu ngisi jinis tanduran lan tanggal tandur.</p><h2>Cara kerjane</h2><ol><li>Petani ndhaftar lan milih lahan.</li><li>Aplikasi njupuk ramalan cuaca lan data lemah.</li><li>Notifikasi dikirim nalika wektu panen wis cedhak.</li></ol><p>Uji coba ing patang desa nuduhake panen luwih sithik sing rusak merga udan.</p>'],

    ['Prototipe Mobil Listrik Lokal Wiwit Dites Ing Dalan Raya', 'otomotif', 'EV, Mobil Listrik', 1, 'published', 14, 2210,
        'Telung kutha dadi lokasi uji dalan prototipe kendaraan listrik buatan dalam negeri.',
        '<p>Prototipe mobil listrik sing digarap tim insinyur lokal wiwit dites ing dalan umum. Tes dilakoni ing telung kutha kanggo ngukur daya tahan baterai lan kenyamanan.</p><h2>Spesifikasi cekak</h2><ul><li>Jarak tempuh tes: kira-kira 300 km sapisan ngecas.</li><li>Ngecas cepet: 30 menit nganti 80 persen.</li><li>Kapasitas penumpang: lima wong.</li></ul><p>Tim isih nglumpukake masukan sadurunge nemtokake jadwal produksi.</p>'],

    ['Anggaran Desa Dibukak Online, Warga Bisa Melu Ngawasi', 'nasional', 'Desa, Transparansi', 0, 'published', 20, 530,
        'Pemerintah desa nayangake rincian anggaran ing situs supaya warga gampang nyemak.',
        '<p>Sawetara pemerintah desa ing Jawa Tengah wiwit nayangake rincian anggaran lan realisasi ing situs resmi. Warga bisa ndeleng dhuwit digunakake kanggo apa wae saben sasi.</p><h2>Kenapa penting</h2><p>Keterbukaan informasi dianggep bisa nyuda salah paham lan nambah kapercayan warga marang perangkat desa.</p>'],

    ['Rapat Dewan Mbahas Aturan Anyar Babagan Parkir Kutha', 'politik', 'Dewan, Parkir', 0, 'published', 30, 410,
        'Rancangan aturan parkir kutha dibahas, kalebu tarif lan pengelolaan lahan parkir.',
        '<p>Dewan perwakilan daerah miwiti pembahasan rancangan aturan parkir kutha. Sing dibahas yaiku tarif, pengelolaan lahan, lan pengawasan juru parkir.</p><p>Sawetara fraksi njaluk supaya masyarakat dilibatake sadurunge aturan disahake.</p>'],

    ['Cara Gampang Ngatur Dhuwit Saben Sasi Kanggo Keluarga Enom', 'bisnis', 'Keuangan, Tips', 0, 'published', 52, 780,
        'Telung langkah prasaja kanggo ngatur pemasukan, tabungan, lan pengeluaran rutin.',
        '<p>Ngatur dhuwit ora kudu ruwet. Mulai saka nyathet pemasukan lan pengeluaran, banjur pisahake dhuwit kanggo kebutuhan, tabungan, lan keinginan.</p><h2>Telung langkah</h2><ol><li>Nyathet kabeh pengeluaran sak sasi.</li><li>Nyisihake tabungan sanalika gajian.</li><li>Nyetel bates belanja kanggo kebutuhan sing ora wajib.</li></ol>'],

    ['Atlet Pelajar Jawa Tengah Nyabet Medali Emas Ing Kejuaraan Atletik', 'olahraga', 'Atletik, Pelajar', 0, 'published', 70, 365,
        'Pelari 400 meter nyetel rekor pribadi anyar ing kejuaraan tingkat nasional.',
        '<p>Atlet pelajar saka Jawa Tengah nyabet medali emas nomer lari 400 meter. Waktune luwih cepet rong detik tinimbang rekor pribadine sadurunge.</p><p>Pelatih ngandharake latihan rutin esuk lan sore dadi kunci keberhasilan.</p>'],

    ['Draf: Liputan Khusus Festival Gamelan Taunan', 'nasional', 'Budaya, Gamelan', 0, 'draft', 1, 0,
        'Catetan awal liputan festival gamelan (durung rampung, isih draf).',
        '<p>Draf liputan, isih dikumpulake wawancarane.</p>'],

    ['Review: Konten Menunggu Persetujuan Editor', 'teknologi', 'Review', 0, 'review', 1, 0,
        'Contoh artikel sing lagi nunggu review editor.',
        '<p>Artikel iki dikirim jurnalis lan lagi nunggu persetujuan editor.</p>'],
];

$cat = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
$exists = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE slug = ?');
$ins = $pdo->prepare('INSERT INTO articles (user_id, category_id, title, slug, excerpt, content_body, status, is_headline,
    views_count, published_at, created_at, updated_at) VALUES (1,?,?,?,?,?,?,?,?,?,?,?)');
$insView = $pdo->prepare('INSERT INTO article_views (article_id, ip_address, viewed_at) VALUES (?,?,?)');

$added = 0;
foreach ($articles as $a) {
    list($title, $catSlug, $tags, $headline, $status, $hoursAgo, $views, $excerpt, $body) = $a;
    $slug = slugify($title);
    $exists->execute([$slug]);
    if ((int) $exists->fetchColumn() > 0) {
        continue;
    }
    $cat->execute([$catSlug]);
    $catId = (int) $cat->fetchColumn();
    if (!$catId) {
        continue;
    }
    $when = gmdate('Y-m-d H:i:s', time() - $hoursAgo * 3600);
    $ins->execute([
        $catId, $title, $slug, $excerpt, sanitize_html($body . $note), $status, $headline, $views,
        $status === 'published' ? $when : null, $when, $when,
    ]);
    $id = (int) $pdo->lastInsertId();
    sync_article_tags($pdo, $id, $tags);

    // Sebar tayangan 48 jam terakhir agar "Lagi Rame Diwaca" & grafik dasbor terisi.
    if ($status === 'published') {
        $recent = min(40, (int) ($views / 40));
        for ($i = 0; $i < $recent; $i++) {
            $insView->execute([$id, '127.0.0.1', gmdate('Y-m-d H:i:s', time() - mt_rand(60, 6 * 86400))]);
        }
    }
    $added++;
}

echo "Selesai: {$added} artikel contoh ditambahkan (sing wis ana dilewati).\n";
