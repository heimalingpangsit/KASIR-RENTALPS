<?php
require __DIR__ . '/_l.php';
require __DIR__ . '/../includes/qr.php';
$id = (int) ($_GET['id'] ?? 0);
$q = $d->prepare('SELECT * FROM units WHERE is_active = 1' . ($id ? ' AND id = ?' : '') . ' ORDER BY unit_number'); $q->execute($id ? [$id] : []);
head('Cetak QR', '../', '<button class="btn sm red noprint" onclick="print()">Cetak</button>');
echo '<main class="wrap wide"><p class="mu noprint">Tempel QR di sebelah TV masing-masing. URL dasar: <code>' . e(base_url()) . '</code> (pastikan ini alamat yang bisa dibuka dari HP pelanggan).</p><div class="qrs">';
foreach ($q->fetchAll() as $u) {
    $url = base_url() . '/index.php?unit=' . (int) $u['unit_number'] . '&token=' . $u['qr_token'];
    echo '<div class="qrc"><h2>' . e($u['name']) . '</h2>' . qr_svg($url, 220) . '<p style="margin:8px 0 0"><b>Scan buat main &amp; pesan</b><br><small>Pilih billing · pesan makan minum</small></p></div>';
}
echo '</div></main>';
foot('../');
