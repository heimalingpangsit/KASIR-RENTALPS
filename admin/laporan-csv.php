<?php
// Ekspor CSV (Excel/Google Sheets): ?from=YYYY-MM-DD&to=YYYY-MM-DD&type=transaksi|harian|tv|menu
require __DIR__ . '/_l.php';
require __DIR__ . '/../includes/report.php';
[$from, $to] = report_range($_GET, '7hari');
$type = in_array($_GET['type'] ?? '', ['transaksi', 'harian', 'tv', 'menu'], true) ? $_GET['type'] : 'transaksi';
$r = report_data($d, $from, $to);
$name = 'Laporan-' . $type . '-RentalPsKu-' . $from . ($from === $to ? '' : '_sd_' . $to) . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
$o = fopen('php://output', 'w');
fwrite($o, "\xEF\xBB\xBF");   // BOM supaya Excel membaca UTF-8
function csv_cell($v) { return (is_string($v) && $v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v; }   // cegah formula injection
$put = function (array $row) use ($o) { fputcsv($o, array_map('csv_cell', $row), ';', '"', '\\'); };   // titik-koma = default Excel Indonesia
if ($type === 'transaksi') {
    $put(['Waktu', 'TV', 'Kode', 'Pemesan', 'Rincian', 'Billing (Rp)', 'Makan & Minum (Rp)', 'Total (Rp)', 'Metode']);
    foreach ($r['tx'] as $t) $put([$t['at'], $t['unit'], $t['code'], $t['name'], $t['desc'], (int) $t['bill'], (int) $t['fnb'], (int) $t['total'], $t['method']]);
} elseif ($type === 'harian') {
    $put(['Tanggal', 'Transaksi', 'Billing (Rp)', 'Makan & Minum (Rp)', 'Total (Rp)']);
    foreach ($r['daily'] as $day => $v) $put([$day, (int) $v['n'], (int) $v['bill'], (int) $v['fnb'], (int) $v['total']]);
} elseif ($type === 'tv') {
    $put(['TV', 'Tipe', 'Transaksi', 'Jam terjual', 'Billing (Rp)', 'Makan & Minum (Rp)', 'Total (Rp)']);
    foreach ($r['units'] as $u) $put([$u['name'], $u['type'], (int) $u['n'], round($u['mins'] / 60, 2), (int) $u['bill'], (int) $u['fnb'], (int) $u['total']]);
    $put([]); $put(['Per tipe']);
    foreach (report_types($r['units']) as $t) $put([$t['type'], $t['tvs'] . ' TV', (int) $t['n'], round($t['mins'] / 60, 2), (int) $t['bill'], (int) $t['fnb'], (int) $t['total']]);
} else {
    $put(['Menu', 'Terjual', 'Pemasukan (Rp)', 'HPP (Rp)', 'Untung (Rp)']);
    foreach (report_menus($d, $from, $to, 0) as $m) $put([$m['item_name'], (int) $m['qty'], (int) $m['omzet'], (int) $m['cost'], (int) $m['omzet'] - (int) $m['cost']]);
}
fclose($o);
