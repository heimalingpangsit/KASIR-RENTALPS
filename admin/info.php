<?php
// Info toko untuk halaman promosi (index.php): alamat, jam buka, WhatsApp, Instagram.
require __DIR__ . '/_l.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $wa = preg_replace('/\D+/', '', (string) ($_POST['wa'] ?? ''));
    if ($wa !== '' && $wa[0] === '0') $wa = '62' . substr($wa, 1);          // 0812… -> 62812…
    $ig = (string) ($_POST['ig'] ?? '');
    if (preg_match('~instagram\.com/([A-Za-z0-9._]+)~i', $ig, $m)) $ig = $m[1];  // boleh tempel link profil
    $ig = preg_replace('/[^A-Za-z0-9._]/', '', ltrim(trim($ig), '@'));
    $hours = implode("\n", array_slice(array_filter(array_map(fn($l) => mb_substr(trim($l), 0, 80), preg_split('/\R/', (string) ($_POST['hours'] ?? ''))), fn($l) => $l !== ''), 0, 10));
    shop_save($d, ['address' => mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 200), 'hours' => $hours, 'wa' => substr($wa, 0, 16), 'ig' => substr($ig, 0, 30)]);
    $_SESSION['flash'] = 'Info toko disimpan. Halaman promosi sudah diperbarui.'; header('Location: info.php'); exit;
}
$msg = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$i = shop_info($d);

a_head('Info Toko');
flash($msg);
echo '<form method="post" class="card" style="max-width:640px">' . csrf_field()
   . '<p class="mu sm" style="margin:0 0 4px">Tampil di bagian <b>Lokasi &amp; Kontak</b> halaman promosi. Kolom yang dikosongkan tidak ditampilkan; kalau semuanya kosong, bagian itu disembunyikan.</p>'
   . '<label class="f">Alamat<input name="address" maxlength="200" value="' . e($i['address']) . '" placeholder="mis. Jl. Pesut No. 10, Samarinda"></label>'
   . '<label class="f">Jam buka <span class="mu sm">(satu baris per jadwal, format: Hari | Jam)</span><textarea name="hours" rows="4" placeholder="Senin - Jumat | 10.00 - 24.00&#10;Sabtu - Minggu | 09.00 - 02.00">' . e($i['hours']) . '</textarea></label>'
   . '<label class="f">WhatsApp <span class="mu sm">(boleh 08xxx, otomatis jadi 62xxx)</span><input name="wa" inputmode="tel" maxlength="20" value="' . e($i['wa']) . '" placeholder="081234567890"></label>'
   . '<label class="f">Instagram <span class="mu sm">(username atau link profil)</span><input name="ig" maxlength="80" value="' . e($i['ig']) . '" placeholder="rentalpsku"></label>'
   . '<div class="inl mt"><button class="btn red"><i class="fa-solid fa-floppy-disk"></i> Simpan</button><a class="btn" href="../index.php" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat halaman promosi</a></div></form>';
a_foot();
