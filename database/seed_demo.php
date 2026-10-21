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
    ['Kopdar Komunitas Fotografi Solo Ngumpulake Atusan Pamotret Enom', 'event-komunitas', 'Komunitas, Fotografi, Solo', 1, 'published', 2, 1840,
        'Pamotret enom saka sawetara kutha nglumpuk kanggo hunting bareng lan tuku-tukar ilmu.',
        '<p>Atusan pamotret enom nglumpuk ing alun-alun kutha dina Minggu esuk kanggo kopdar komunitas fotografi. Acara iki diwiwiti hunting bareng, banjur dilanjutake sesi tukar ilmu.</p><h2>Isi acara</h2><ul><li>Hunting foto jalanan bareng mentor.</li><li>Sesi kurasi karya lan masukan.</li><li>Pameran cilik karya peserta.</li></ul><blockquote>Kene kita ora saingan, kita sinau bareng.</blockquote><p>Panitia nyebut acara bakal diadakake saben telung sasi.</p>'],

    ['Festival Zine Lokal: Karya Bocah Enom Dipamerke Ing Kafe Kampus', 'event-komunitas', 'Zine, Seni, Kampus', 1, 'published', 6, 1320,
        'Puluhan zine buatan tangan dipamerke lan didol kanthi rega ramah kanggo mahasiswa.',
        '<p>Festival zine lokal ngumpulake kreator saka macem-macem kampus. Zine sing dipamerke ngrembug saka kuliner, musik, nganti curhatan urip kampus.</p><h2>Kenapa zine isih rame?</h2><p>Para kreator rumangsa zine menehi ruang bebas tanpa algoritma. Saben lembar dibuat dhewe, dadi duwe rasa pribadi.</p>'],

    ['Cara Mbangun Portofolio Online Sing Gampang Dicolong Mripate Recruiter', 'personal-branding', 'Portofolio, Karir, Tips', 1, 'published', 9, 2210,
        'Telung kesalahan umum ing portofolio lan carane ndandani supaya luwih menonjol.',
        '<p>Portofolio sing apik ora kudu ruwet. Sing penting yaiku nuduhake proses lan asil kanthi cetha.</p><h2>Telung kesalahan umum</h2><ol><li>Kakehan karya tanpa cerita.</li><li>Ora ana kontak sing gampang ditemokake.</li><li>Ora diperbarui nganti pirang-pirang taun.</li></ol><p>Pilih lima karya paling apik, jlentrehake masalahe, solusi sampeyan, lan hasile.</p>'],

    ['Personal Branding Ora Padha Karo Pamer: Cara Tampil Jujur Ing Medsos', 'personal-branding', 'Medsos, Branding', 0, 'published', 14, 960,
        'Mbangun citra dhiri bisa tetep blaka tanpa kudu pura-pura sempurna.',
        '<p>Akeh wong ngira personal branding tegese pamer. Nyatane, branding sing kuwat biasane lair saka konsistensi lan kejujuran.</p><h2>Prinsip prasaja</h2><ul><li>Cerita proses, ora mung asil.</li><li>Konsisten ing nada lan topik.</li><li>Akoni yen lagi sinau.</li></ul>'],

    ['Warung Kopi Susu Kekinian Ing Gang Cilik Dadi Rebutan Bocah Nongkrong', 'lifestyle', 'Kuliner, Nongkrong, Kopi', 0, 'published', 20, 1530,
        'Warung cilik karo menu kopi susu aren iki ora tau sepi, utamane nalika wayah sore.',
        '<p>Ing sawijining gang cilik, warung kopi susu iki saben sore kebak bocah enom. Menu andalane kopi susu aren karo rega sing ora nggawe kantong jebol.</p><h2>Apa sing digoleki wong?</h2><p>Akeh sing ngaku teka ora mung kanggo ngopi, nanging kanggo golek panggonan sing nyaman kanggo ngobrol lan nggarap tugas.</p>'],

    ['Thrifting Ora Mung Murah: Cara Milih Klambi Bekas Sing Isih Apik', 'lifestyle', 'Fashion, Thrifting', 0, 'published', 30, 780,
        'Tips prasaja kanggo thrifting: priksa jahitan, bahan, lan ukuran sadurunge nawar.',
        '<p>Thrifting dadi tren amarga rega lan model sing ora padha karo liyane. Nanging ora kabeh klambi bekas pantes dituku.</p><h2>Telung cek cepet</h2><ol><li>Priksa jahitan lan kancing.</li><li>Ambu bahane, mesthekake ora apek.</li><li>Cobak nganggo sadurunge nawar.</li></ol>'],

    ['Gen Z Ngomongake Kesehatan Mental: Saka Tabu Dadi Obrolan Biasa', 'seputar-gen-z', 'Gen Z, Kesehatan Mental', 0, 'published', 52, 1105,
        'Bocah enom saya kendel rembugan babagan stres lan kesehatan mental karo kanca-kancane.',
        '<p>Yen sadurunge kesehatan mental dianggep tabu, saiki akeh Gen Z sing kendel nyritakake pengalamane ing medsos lan komunitas.</p><h2>Apa sing owah?</h2><p>Akses informasi lan komunitas dukungan online mbantu bocah enom ngerti yen ora sepi dhewe. Nanging para ahli ngelingake supaya tetep golek bantuan profesional yen perlu.</p>'],

    ['Konten Kreator Enom Wiwit Nyambut Gawe Bareng Lewat Kolaborasi Lintas Kutha', 'event-komunitas', 'Kolaborasi, Kreator', 0, 'published', 70, 365,
        'Kolaborasi online lan offline mbantu kreator cilik nggedhekake audiens bareng-bareng.',
        '<p>Sawetara kreator konten enom nggawe proyek kolaborasi lintas kutha. Dheweke gantian dadi tuan rumah lan nggawe konten bareng.</p><p>Manut para kreator, kolaborasi luwih migunani tinimbang saingan, amarga audiens bisa kenal karo kreator liyane.</p>'],

    ['Draf: Liputan Khusus Festival Musik Indie Taunan', 'event-komunitas', 'Musik, Festival', 0, 'draft', 1, 0,
        'Catetan awal liputan festival musik indie (durung rampung, isih draf).',
        '<p>Draf liputan, isih dikumpulake wawancarane.</p>'],

    ['Review: Konten Menunggu Persetujuan Editor', 'lifestyle', 'Review', 0, 'review', 1, 0,
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
