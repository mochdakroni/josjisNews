<?php
/**
 * Halaman yang dirender server: cangkang SPA dengan meta SEO, RSS, sitemap, robots, halaman statis.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repository.php';

function static_pages()
{
    $s = site();
    $n = e($s['name']);
    $co = e($s['company']);
    $mail = e($s['email']);
    $adsMail = e($s['ads_email']);
    $contact = '<ul><li>Email redaksi: <a href="mailto:' . $mail . '">' . $mail . '</a></li>'
        . '<li>Email iklan: <a href="mailto:' . $adsMail . '">' . $adsMail . '</a></li>'
        . ($s['phone'] !== '' ? '<li>Telepon / WhatsApp: ' . e($s['phone']) . '</li>' : '')
        . ($s['address'] !== '' ? '<li>Alamat: ' . e($s['address']) . '</li>' : '')
        . '</ul>';

    return [
        'tentang' => ['Tentang Kami', "
<p><strong>{$n}</strong> iku portal kabar digital sing nyuguhake berita nganggo basa Jawa ngoko. Pedoman kita cekak: <em>" . e($s['tagline']) . "</em> — blaka, cetha, lan ora ngapusi pamaca.</p>
<p>{$n} diterbitake lan dikelola dening <strong>{$co}</strong>.</p>
<h2>Visi</h2>
<p>Dadi sumber kabar basa Jawa sing bisa dipercaya, gampang diwaca, lan cedhak karo uripe wong akeh.</p>
<h2>Misi</h2>
<ul>
<li>Nyuguhake kabar sing akurat, imbang, lan nganggo sumber sing cetha.</li>
<li>Njaga lan nguri-uri basa Jawa ing ranah media digital.</li>
<li>Mbedakake kabar karo opini, lan iklan karo isi redaksi.</li>
<li>Mbukak dalan kanggo hak jawab lan koreksi.</li>
</ul>
<h2>Hubungi Kami</h2>{$contact}"],

        'redaksi' => ['Susunan Redaksi', "
<p>Redaksi {$n} dikelola dening {$co}.</p>
<ul>
<li>Penanggung jawab: " . ($s['editor_in_chief'] !== '' ? e($s['editor_in_chief']) : 'Pimpinan Redaksi (diisi sesuai data legal perusahaan)') . "</li>
<li>Penerbit: {$co}</li>
</ul>
<h2>Kontak Redaksi</h2>{$contact}
<p>Kiriman informasi, koreksi, lan aduan bisa dikirim menyang email redaksi ing dhuwur.</p>"],

        'pedoman-media-siber' => ['Pedoman Media Siber', "
<p>{$n} nuduhake komitmen nuruti <strong>UU No. 40 Tahun 1999 tentang Pers</strong>, <strong>Kode Etik Jurnalistik</strong>, lan <strong>Pedoman Pemberitaan Media Siber</strong> saka Dewan Pers.</p>
<h2>Prinsip Pemberitaan</h2>
<ul>
<li><strong>Verifikasi lan imbang.</strong> Informasi sing penting dicek menyang sumber sing bisa dipertanggungjawabake sadurunge ditayangke.</li>
<li><strong>Ora nyampur fakta lan opini.</strong> Opini lan analisis diwenehi tandha cetha.</li>
<li><strong>Praduga tak bersalah.</strong> Kita ora nghakimi wong sing isih dalam proses hukum.</li>
<li><strong>Perlindungan anak lan korban.</strong> Identitas anak lan korban kejahatan kesusilaan ora disebutake.</li>
<li><strong>Iklan dipisah.</strong> Konten sponsor lan iklan diwenehi label \"IKLAN\".</li>
</ul>
<h2>Koreksi lan Pembaruan</h2>
<p>Yen ana kesalahan, kita bakal ndandani lan menehi catetan koreksi ing artikel sing kena.</p>
<h2>Komentar lan Konten Pihak Ketiga</h2>
<p>Konten pihak ketiga dan tautan metu ora dadi tanggung jawab redaksi.</p>"],

        'hak-jawab' => ['Hak Jawab & Koreksi', "
<p>Saben pihak sing rumangsa dirugekake dening pemberitaan {$n} nduweni <strong>hak jawab</strong> lan <strong>hak koreksi</strong> manut UU Pers.</p>
<ol>
<li>Kirim email menyang <a href=\"mailto:{$mail}\">{$mail}</a> kanthi subjek <em>Hak Jawab</em>.</li>
<li>Sebutake judul, tautan artikel, lan bagean sing dikeluhake.</li>
<li>Lampirake identitas lan bukti pendukung.</li>
</ol>
<p>Hak jawab bakal ditayangke proporsional lan ditanggapi kanthi cepet.</p>"],

        'kebijakan-privasi' => ['Kebijakan Privasi', "
<p>Kebijakan iki njlentrehake carane <strong>{$co}</strong> (pengelola {$n}) ngolah data sampeyan.</p>
<h2>Data sing dikumpulake</h2>
<ul>
<li>Data teknis dhasar (alamat IP, jinis browser) kanggo statistik pembaca lan keamanan.</li>
<li>Cookie sesi kanggo staf redaksi sing login ing panel CMS.</li>
</ul>
<h2>Tujuan</h2>
<p>Data dienggo kanggo ngitung tayangan artikel, njaga keamanan, lan nambahi kualitas layanan. Kita ora ngedol data pribadi.</p>
<h2>Iklan lan pihak ketiga</h2>
<p>Iklan bisa ngandhut tautan menyang situs pihak ketiga sing nduweni kebijakan privasi dhewe.</p>
<h2>Hak sampeyan</h2>
<p>Sampeyan bisa njaluk akses, koreksi, utawa penghapusan data liwat <a href=\"mailto:{$mail}\">{$mail}</a>.</p>"],

        'syarat-ketentuan' => ['Syarat & Ketentuan', "
<p>Kanthi ngakses {$n}, sampeyan nyetujoni ketentuan iki.</p>
<ul>
<li>Seluruh konten redaksi diayomi hak cipta © {$s['year']} {$co}.</li>
<li>Kutipan diidini kanthi nyebutake sumber lan tautan asli; penyalinan lengkap mbutuhake izin tertulis.</li>
<li>Kita ora tanggung jawab marang kerugian sing muncul saka panggunaan informasi ing situs iki.</li>
<li>Kita bisa ngowahi ketentuan iki kapan wae.</li>
</ul>"],

        'pasang-iklan' => ['Pasang Iklan', "
<p>Jangkau pamaca basa Jawa lan masyarakat lokal bareng {$n}. Slot sing kasedhiya:</p>
<ul>
<li>Leaderboard Utama (970×90)</li>
<li>In-Feed Native ing dhaftar kabar</li>
<li>Sidebar 300×250 (laman ngarep lan laman kabar)</li>
</ul>
<p>Kabeh iklan diwenehi label cetha lan dipisahake saka konten redaksi.</p>
<h2>Kontak Iklan</h2>
<p>Email: <a href=\"mailto:{$adsMail}\">{$adsMail}</a>" . ($s['phone'] !== '' ? '<br>Telepon / WhatsApp: ' . e($s['phone']) : '') . "</p>"],
    ];
}

/** Render cangkang SPA (preview.html) dengan meta, JSON-LD, dan data boot. */
function render_shell(array $meta, array $boot = [], $status = 200)
{
    $s = site();
    $base = base_url();
    $tpl = file_get_contents(JOSJIS_ROOT . '/preview.html');

    $title = $meta['title'] ?? ($s['name'] . ' — ' . $s['tagline']);
    $desc = $meta['description'] ?? $s['description'];
    $canon = $base . ($meta['path'] ?? '/');
    $image = $meta['image'] ?? '';
    if ($image !== '' && $image[0] === '/') {
        $image = $base . $image;
    }
    $type = $meta['type'] ?? 'website';

    $head = '<title>' . e($title) . "</title>\n"
        . '<meta name="description" content="' . e($desc) . "\">\n"
        . '<link rel="canonical" href="' . e($canon) . "\">\n"
        . '<meta property="og:type" content="' . e($type) . "\">\n"
        . '<meta property="og:site_name" content="' . e($s['name']) . "\">\n"
        . '<meta property="og:title" content="' . e($title) . "\">\n"
        . '<meta property="og:description" content="' . e($desc) . "\">\n"
        . '<meta property="og:url" content="' . e($canon) . "\">\n"
        . '<meta property="og:locale" content="id_ID">' . "\n"
        . ($image !== '' ? '<meta property="og:image" content="' . e($image) . "\">\n" : '')
        . '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . "\">\n"
        . '<meta name="twitter:title" content="' . e($title) . "\">\n"
        . '<meta name="twitter:description" content="' . e($desc) . "\">\n"
        . ($image !== '' ? '<meta name="twitter:image" content="' . e($image) . "\">\n" : '')
        . (!empty($meta['noindex']) ? '<meta name="robots" content="noindex,follow">' . "\n" : '')
        . '<link rel="alternate" type="application/rss+xml" title="' . e($s['name']) . ' RSS" href="' . e($base) . "/rss.xml\">\n";

    $ld = $meta['jsonld'] ?? [
        '@context' => 'https://schema.org',
        '@type'    => 'NewsMediaOrganization',
        'name'     => $s['name'],
        'slogan'   => $s['tagline'],
        'url'      => $base . '/',
        'parentOrganization' => ['@type' => 'Organization', 'name' => $s['company']],
    ];
    $head .= '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";

    $tpl = preg_replace_callback('/<!--SEO:START-->.*?<!--SEO:END-->/s', function () use ($head) {
        return $head;
    }, $tpl, 1);

    $bootJson = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $bootTag = '<script>window.__BOOT__=' . $bootJson . ';window.__SITE__=' . json_encode(public_site_for_js(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
    $tpl = str_replace('<!--BOOT-->', $bootTag, $tpl);

    $noscript = $meta['noscript'] ?? '';
    $tpl = str_replace('<!--NOSCRIPT-->', $noscript, $tpl);

    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=60');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    echo $tpl;
    exit;
}

function public_site_for_js()
{
    $s = site();
    return [
        'name' => $s['name'], 'tagline' => $s['tagline'], 'company' => $s['company'],
        'year' => $s['year'], 'email' => $s['email'], 'url' => base_url(),
    ];
}

function page_home()
{
    render_shell([
        'title' => site()['name'] . ' — ' . site()['tagline'] . ': Portal Kabar Jawa Ngoko',
        'path'  => '/',
    ], ['type' => 'home']);
}

function page_article($slug)
{
    $pdo = db();
    $row = find_published_article($pdo, $slug);
    if (!$row) {
        return page_not_found();
    }
    $a = hydrate_articles($pdo, [$row])[0];
    $s = site();
    $base = base_url();
    $desc = $a['excerpt'] !== '' ? $a['excerpt'] : plain_excerpt($a['content_body'], 160);
    $img = $a['image'];
    $url = $base . '/artikel/' . $a['slug'];
    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'headline' => mb_substr($a['title'], 0, 110),
        'description' => $desc,
        'datePublished' => $a['published_at'],
        'dateModified' => $a['updated_at'] ?: $a['published_at'],
        'author' => [['@type' => 'Person', 'name' => $a['author']['name']]],
        'publisher' => ['@type' => 'Organization', 'name' => $s['company'], 'brand' => $s['name']],
        'articleSection' => $a['category']['name'],
        'inLanguage' => 'jv',
    ];
    if ($img !== '') {
        $ld['image'] = [$img[0] === '/' ? $base . $img : $img];
    }
    render_shell([
        'title' => $a['title'] . ' — ' . $s['name'],
        'description' => $desc,
        'image' => $img,
        'type' => 'article',
        'path' => '/artikel/' . $a['slug'],
        'jsonld' => $ld,
        'noscript' => '<article><h1>' . e($a['title']) . '</h1><p>' . e($desc) . '</p>' . $a['content_body'] . '</article>',
    ], ['type' => 'article', 'slug' => $a['slug'], 'id' => $a['id']]);
}

function page_category($slug)
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM categories WHERE slug = ? AND is_active = 1');
    $st->execute([$slug]);
    $c = $st->fetch();
    if (!$c) {
        return page_not_found();
    }
    render_shell([
        'title' => 'Kanal ' . $c['name'] . ' — ' . site()['name'],
        'description' => $c['description'] ?: ('Kabar ' . $c['name'] . ' paling anyar saka ' . site()['name'] . '.'),
        'path' => '/kanal/' . $c['slug'],
    ], ['type' => 'category', 'slug' => $c['slug']]);
}

function page_static($slug)
{
    $pages = static_pages();
    if (!isset($pages[$slug])) {
        return page_not_found();
    }
    list($title, $html) = $pages[$slug];
    render_shell([
        'title' => $title . ' — ' . site()['name'],
        'description' => plain_excerpt($html, 160),
        'path' => '/halaman/' . $slug,
        'noscript' => '<h1>' . e($title) . '</h1>' . $html,
    ], ['type' => 'page', 'slug' => $slug, 'title' => $title, 'html' => $html]);
}

function page_not_found()
{
    render_shell([
        'title' => 'Kaca ora ditemokake — ' . site()['name'],
        'noindex' => true,
        'path' => '/404',
    ], ['type' => 'notfound'], 404);
}

function render_robots()
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nDisallow: /api/\n\nSitemap: " . base_url() . "/sitemap.xml\n";
    exit;
}

function render_sitemap()
{
    $pdo = db();
    $base = base_url();
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=600');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo '<url><loc>' . e($base) . "/</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>\n";
    foreach ($pdo->query('SELECT slug FROM categories WHERE is_active = 1 ORDER BY id')->fetchAll() as $c) {
        echo '<url><loc>' . e($base . '/kanal/' . $c['slug']) . "</loc><changefreq>hourly</changefreq><priority>0.7</priority></url>\n";
    }
    foreach (array_keys(static_pages()) as $slug) {
        echo '<url><loc>' . e($base . '/halaman/' . $slug) . "</loc><changefreq>yearly</changefreq><priority>0.3</priority></url>\n";
    }
    $rows = $pdo->query("SELECT a.slug, COALESCE(a.updated_at, a.published_at) AS lm FROM articles a WHERE " . PUBLISHED_SQL . " ORDER BY a.published_at DESC LIMIT 5000")->fetchAll();
    foreach ($rows as $r) {
        echo '<url><loc>' . e($base . '/artikel/' . $r['slug']) . '</loc><lastmod>' . e(iso_date($r['lm'])) . "</lastmod><priority>0.8</priority></url>\n";
    }
    echo '</urlset>';
    exit;
}

function render_rss($categorySlug = null)
{
    $pdo = db();
    $s = site();
    $base = base_url();
    list($items) = query_published($pdo, ['per_page' => 30, 'category' => $categorySlug, 'with_body' => true]);
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<?xml-stylesheet type="text/xsl" href="/rss.xsl"?>' . "\n";
    echo '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom"><channel>' . "\n";
    echo '<title>' . e($s['name'] . ' — ' . $s['tagline']) . '</title><link>' . e($base) . '/</link>';
    echo '<description>' . e($s['description']) . '</description><language>jv</language>';
    echo '<atom:link href="' . e($base) . '/rss.xml" rel="self" type="application/rss+xml"/>' . "\n";
    foreach ($items as $a) {
        $url = $base . '/artikel/' . $a['slug'];
        echo '<item><title>' . e($a['title']) . '</title><link>' . e($url) . '</link><guid isPermaLink="true">' . e($url) . '</guid>';
        echo '<pubDate>' . gmdate('r', strtotime($a['published_at'])) . '</pubDate>';
        echo '<category>' . e($a['category']['name']) . '</category>';
        echo '<description>' . e($a['excerpt']) . '</description>';
        echo '<content:encoded><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $a['content_body']) . ']]></content:encoded></item>' . "\n";
    }
    echo '</channel></rss>';
    exit;
}

/** Stylesheet agar RSS terlihat rapi & informatif saat dibuka di browser (pembaca RSS mengabaikannya). */
function render_rss_xsl()
{
    $s = site();
    header('Content-Type: text/xsl; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="html" encoding="UTF-8" indent="yes"/>
<xsl:template match="/rss/channel">
<html lang="jv"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>RSS — <xsl:value-of select="title"/></title>
<style>
body{font-family:system-ui,sans-serif;background:#fafafa;color:#18181b;margin:0}
main{max-width:760px;margin:0 auto;padding:24px 16px}
.box{background:#fff1f2;border:1px solid #fecdd3;border-radius:14px;padding:16px 18px;font-size:14px;line-height:1.6}
h1{font-family:Georgia,serif;margin:20px 0 4px}
a{color:#be123c}
article{background:#fff;border:1px solid #e4e4e7;border-radius:14px;padding:14px 18px;margin:12px 0}
article h2{font-size:17px;margin:0 0 4px;font-family:Georgia,serif}
small{color:#71717a}
</style></head><body><main>
<div class="box"><strong>Iki RSS feed, dudu kaca web biasa.</strong><br/>
RSS ndadekake sampeyan bisa melu langganan kabar anyar tanpa mbukak situs. Salin alamat kaca iki
(<code><xsl:value-of select="atom:link/@href" xmlns:atom="http://www.w3.org/2005/Atom"/></code>)
lan tempel ing aplikasi pembaca RSS (Feedly, Inoreader, NetNewsWire, dsb).</div>
<h1><xsl:value-of select="title"/></h1>
<p><small><xsl:value-of select="description"/> · <a href="/">Balik menyang situs</a></small></p>
<xsl:if test="not(item)"><p>Durung ana kabar sing ditayangke.</p></xsl:if>
<xsl:for-each select="item">
<article><h2><a href="{link}"><xsl:value-of select="title"/></a></h2>
<small><xsl:value-of select="category"/> · <xsl:value-of select="pubDate"/></small>
<p><xsl:value-of select="description"/></p></article>
</xsl:for-each>
</main></body></html>
</xsl:template></xsl:stylesheet>';
    exit;
}
