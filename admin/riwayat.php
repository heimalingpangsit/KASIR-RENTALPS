<?php
require __DIR__ . '/_l.php';
require __DIR__ . '/../includes/report.php';
$range = $_GET['range'] ?? 'hari_ini'; if (!in_array($range, ['hari_ini', '7hari', '30hari'], true)) $range = 'hari_ini';
[$from, $to] = report_range(['range' => $range]);
$q = trim($_GET['q'] ?? '');
$rows = report_tx($d, $from, $to);
if ($q !== '') { $ql = mb_strtolower($q); $rows = array_values(array_filter($rows, fn($t) => str_contains(mb_strtolower($t['code'] . ' ' . $t['name'] . ' ' . $t['unit']), $ql))); }
$rows = array_slice($rows, 0, 300);
$GLOBALS['noh1'] = true;
a_head('Riwayat');
$qs = $q !== '' ? '&q=' . urlencode($q) : '';
$tab = fn($k, $l) => '<a href="?range=' . $k . $qs . '" class="' . ($range === $k ? 'on' : '') . '">' . $l . '</a>';
echo '<div class="toolbar"><h2>Riwayat Transaksi</h2><nav class="seg">' . $tab('hari_ini', 'Hari Ini') . $tab('7hari', '7 Hari') . $tab('30hari', '30 Hari') . '</nav></div>';
echo '<div class="card"><form method="get" class="inl"><input type="hidden" name="range" value="' . e($range) . '"><div style="flex:1;min-width:200px"><label class="f" style="margin-top:0">Cari (kode / nama / TV)</label><input name="q" value="' . e($q) . '" placeholder="mis. 482917 atau Budi"></div><button class="btn red" style="align-self:flex-end">Cari</button></form></div>';
echo '<div class="card ov"><table><tr><th>Waktu</th><th>TV</th><th class="hm">Kode</th><th>Pemesan</th><th class="hm">Rincian</th><th>Total</th><th class="hm">Bayar</th><th class="hm">Status</th><th>Aksi</th></tr>';
if (!$rows) echo '<tr><td colspan="9" class="c mu">Belum ada riwayat di rentang ini.</td></tr>';
foreach ($rows as $t) {
    $link = 'struk.php?' . ($t['b'] ? 'b=' . $t['b'] : 'o=' . $t['o']);
    echo '<tr><td>' . date('d/m H:i', strtotime($t['at'])) . '</td><td>' . e($t['unit']) . '</td><td class="hm">' . e($t['code']) . '</td><td>' . e($t['name']) . '</td><td class="mu sm hm">' . e($t['desc']) . '</td><td><b>' . rp($t['total']) . '</b></td><td class="hm">' . e($t['method']) . '</td><td class="hm"><span class="pill ok">Lunas</span></td><td><a class="btn sm" target="_blank" href="../' . $link . '"><i class="fa-solid fa-receipt"></i> Cetak Struk</a></td></tr>';
}
echo '</table></div>';
a_foot();
