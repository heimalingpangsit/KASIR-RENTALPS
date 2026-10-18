<?php
// Halaman promosi publik (rentalpsku.site.je). Hanya informasi — pesan/bayar/timer TIDAK ada di sini.
// Pelanggan masuk ke aplikasi lewat scan QR di samping TV (…/index.php?unit=..&token=..) -> diteruskan ke app.php.
require __DIR__ . '/config.php';
require __DIR__ . '/rental.php';

if (isset($_GET['unit'], $_GET['token'])) {   // QR lama/baru tetap jalan
    header('Location: app.php?' . http_build_query(['unit' => (string) $_GET['unit'], 'token' => (string) $_GET['token']])); exit;
}
boot();
$hasSession = !empty($_SESSION['unit_id']);    // pelanggan yang tadi scan QR: tampilkan jalan pintas kembali ke timer

// Data dari database; kalau DB bermasalah halaman tetap tampil.
$tv = 0; $info = ['address' => '', 'hours' => '', 'wa' => '', 'ig' => ''];
try { $d = db(); $tv = (int) $d->query('SELECT COUNT(*) FROM units WHERE is_active = 1')->fetchColumn(); $info = shop_info($d); } catch (Throwable $ex) {}
$hours = shop_hours_list($info['hours']);
$wa = preg_replace('/\D+/', '', $info['wa']); $ig = preg_replace('/[^A-Za-z0-9._]/', '', $info['ig']); $addr = trim($info['address']);
$hasLoc = $addr !== '' || $hours || $wa !== '' || $ig !== '';
$steps = [
    ['fa-qrcode',          'Scan QR di samping TV', 'Setiap TV punya kode QR sendiri. Buka kamera HP, scan, selesai — tanpa aplikasi, tanpa akun.'],
    ['fa-clock',           'Pilih paket & menu',    'Pilih durasi main, tambah makanan atau minuman kalau mau, semuanya dalam satu pembayaran.'],
    ['fa-qrcode',          'Bayar QRIS atau tunai', 'Bayar lewat QRIS dari HP, atau tunai di kasir. Kasir konfirmasi dan timer langsung jalan.'],
    ['fa-gamepad',         'Main, pesan, tambah jam', 'Pantau sisa waktu dari HP, pesan camilan ke TV kamu, atau perpanjang kapan saja.'],
];
$why = [
    ['fa-bolt',           'Tanpa Antre',         'Pilih dan bayar dari HP. Tidak perlu menunggu kasir untuk mulai main.'],
    ['fa-stopwatch',      'Timer Transparan',    'Sisa waktu terlihat jelas di HP, lengkap dengan tombol tambah waktu.'],
    ['fa-burger',         'Makan & Minum Diantar', 'Pesan dari HP, pesanan langsung masuk ke kasir dan diantar ke TV kamu.'],
    ['fa-bell-concierge', 'Panggil Staff',       'Butuh bantuan? Satu tombol di HP, staff langsung datang ke tempatmu.'],
];
$title = APP_NAME . ' — Rental PlayStation, Scan QR & Main';
$desc = 'Rental PlayStation dengan sistem scan QR: pilih paket, bayar QRIS, timer jalan, pesan makan & minum langsung dari HP.';
$css = @filemtime(__DIR__ . '/assets/landing.css');
?><!DOCTYPE html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark">
<title><?= e($title) ?></title><meta name="description" content="<?= e($desc) ?>">
<meta property="og:type" content="website"><meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($desc) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Orbitron:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/landing.css?v=<?= $css ?>">
</head><body>

<header class="nav"><div class="ct nav__in">
  <a href="#top" class="logo"><i class="fa-solid fa-gamepad"></i><?= e(APP_NAME) ?></a>
  <nav class="nav__links"><a href="#cara">Cara Main</a><?php if ($hasLoc): ?><a href="#lokasi">Lokasi</a><?php endif; ?></nav>
</div></header>

<?php if ($hasSession): ?><div class="resume"><div class="ct"><i class="fa-solid fa-stopwatch"></i> Kamu punya sesi di salah satu TV. <a href="app.php">Kembali ke timer &rarr;</a></div></div><?php endif; ?>

<main id="top">

<!-- HERO -->
<section class="hero"><div class="ct hero__in">
  <div class="hero__txt">
    <span class="eyebrow">Rental PlayStation · Pesan dari HP</span>
    <h1>Scan QR.<br><span class="hl">Pilih Paket.</span> Main.</h1>
    <p>Tidak perlu antre di kasir. Scan QR di samping TV, pilih paket, bayar lewat QRIS, dan timer langsung jalan. Mau ngemil? Pesan dari HP, diantar ke TV kamu.</p>
    <div class="cta">
      <a href="#cara" class="btn btn--red"><i class="fa-solid fa-circle-info"></i> Cara Main</a>
      <?php if ($hasLoc): ?><a href="#lokasi" class="btn"><i class="fa-solid fa-location-dot"></i> Lokasi</a><?php endif; ?>
    </div>
    <ul class="stats">
      <?php if ($tv): ?><li><b><?= $tv ?></b><span>TV Tersedia</span></li><?php endif; ?>
      <li><b>QRIS</b><span>Bayar dari HP</span></li>
    </ul>
  </div>
  <div class="hero__vis" aria-hidden="true">
    <div class="phone">
      <div class="phone__bar"><i class="fa-solid fa-tv"></i> PS 1</div>
      <div class="ring"><svg viewBox="0 0 200 200"><circle class="rb" cx="100" cy="100" r="90"/><circle class="rf" cx="100" cy="100" r="90"/></svg><div class="ring__t"><b>00:42:18</b><span>sisa waktu</span></div></div>
      <div class="phone__btn"><i class="fa-solid fa-burger"></i> Pesan makan &amp; minum</div>
      <div class="phone__btn phone__btn--ghost"><i class="fa-solid fa-bell-concierge"></i> Panggil staff</div>
    </div>
  </div>
</div></section>

<!-- KENAPA -->
<section class="sec"><div class="ct">
  <div class="head"><span class="eyebrow">Kenapa <?= e(APP_NAME) ?></span><h2>Main Tanpa <span class="hl">Ribet</span></h2><p>Semua diatur dari satu QR di samping TV kamu.</p></div>
  <div class="grid4">
    <?php foreach ($why as [$ic, $t, $p]): ?><div class="tile fade"><div class="tile__ic"><i class="fa-solid <?= $ic ?>"></i></div><h3><?= e($t) ?></h3><p><?= e($p) ?></p></div><?php endforeach; ?>
  </div>
</div></section>

<!-- CARA -->
<section class="sec sec--alt" id="cara"><div class="ct">
  <div class="head"><span class="eyebrow">Simpel &amp; Cepat</span><h2>Cara <span class="hl">Main</span> di Sini</h2><p>Empat langkah. Tanpa aplikasi dan tanpa bikin akun.</p></div>
  <ol class="steps">
    <?php foreach ($steps as $i => [$ic, $t, $p]): ?><li class="fade"><span class="steps__n"><?= $i + 1 ?></span><div><h3><?= e($t) ?></h3><p><?= e($p) ?></p></div></li><?php endforeach; ?>
  </ol>
  <p class="note"><i class="fa-solid fa-circle-exclamation"></i> Pemesanan hanya bisa lewat QR yang ditempel di samping TV, bukan dari halaman ini.</p>
</div></section>

<!-- CTA -->
<section class="sec sec--tight"><div class="ct"><div class="banner fade">
  <div><h2>Sudah di tempat kami?</h2><p>Scan QR di samping TV kamu sekarang dan mulai main dalam hitungan detik.</p></div>
  <?php if ($hasLoc): ?><a href="#lokasi" class="btn btn--ghost"><i class="fa-solid fa-location-dot"></i> Lihat Lokasi</a><?php endif; ?>
</div></div></section>

<!-- LOKASI -->
<?php if ($hasLoc): ?>
<section class="sec" id="lokasi"><div class="ct"><div class="loc fade">
  <div>
    <span class="eyebrow">Kunjungi Kami</span><h2><span class="hl">Lokasi</span> &amp; Kontak</h2>
    <?php if ($addr !== ''): ?><p class="loc__a"><i class="fa-solid fa-location-dot"></i> <?= e($addr) ?></p><?php endif; ?>
    <?php if ($hours): ?><ul class="hours"><?php foreach ($hours as [$day, $time]): ?><li><span><?= e($day) ?></span><span><?= e($time) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    <div class="cta">
      <?php if ($wa !== ''): ?><a class="btn btn--red" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>"><i class="fa-brands fa-whatsapp"></i> Chat WhatsApp</a><?php endif; ?>
      <?php if ($ig !== ''): ?><a class="btn" target="_blank" rel="noopener" href="https://instagram.com/<?= e($ig) ?>"><i class="fa-brands fa-instagram"></i> @<?= e($ig) ?></a><?php endif; ?>
    </div>
  </div>
  <div class="loc__vis" aria-hidden="true"><i class="fa-brands fa-playstation"></i></div>
</div></div></section>
<?php endif; ?>

</main>

<footer class="foot"><div class="ct"><span class="logo"><i class="fa-solid fa-gamepad"></i><?= e(APP_NAME) ?></span><span class="mu">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Semua hak dilindungi.</span></div></footer>

<script>
(function(){var els=document.querySelectorAll('.fade');if(!('IntersectionObserver' in window)||matchMedia('(prefers-reduced-motion:reduce)').matches){els.forEach(function(e){e.classList.add('in')});return}
var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');io.unobserve(x.target)}})},{threshold:.15});els.forEach(function(e){io.observe(e)})})();
</script>
</body></html>
