<?php
// Data laporan pemasukan + pembuat PDF. Dipakai admin/laporan.php, admin/laporan-pdf.php, admin/riwayat.php.

const BULAN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function tgl_id(string $ymd): string { $t = strtotime($ymd); return date('j', $t) . ' ' . BULAN[(int) date('n', $t)] . ' ' . date('Y', $t); }
function valid_ymd($s): bool { return is_string($s) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]); }

/** @return array{0:string,1:string,2:string} [dari, sampai, kode range] */
function report_range(array $q, string $default = '7hari'): array {
    $today = date('Y-m-d'); $r = $q['range'] ?? $default;
    if (valid_ymd($q['from'] ?? null) && valid_ymd($q['to'] ?? null)) {
        $f = $q['from']; $t = $q['to']; if ($f > $t) [$f, $t] = [$t, $f];
        return [$f, $t, 'custom'];
    }
    if ($r === 'hari_ini') return [$today, $today, 'hari_ini'];
    if ($r === '30hari') return [date('Y-m-d', strtotime('-29 days')), $today, '30hari'];
    return [date('Y-m-d', strtotime('-6 days')), $today, '7hari'];
}

/** Transaksi lunas (billing + pesanan makan/minum dengan kode sama digabung jadi 1 transaksi). */
function report_tx(PDO $d, string $from, string $to): array {
    $tx = [];
    $bq = $d->prepare("SELECT b.*, s.customer_name, u.name uname FROM billings b JOIN play_sessions s ON s.id=b.session_id JOIN units u ON u.id=s.unit_id WHERE b.pay_status='lunas' AND DATE(b.paid_at) BETWEEN ? AND ?");
    $bq->execute([$from, $to]);
    foreach ($bq->fetchAll() as $b) {
        $k = $b['session_id'] . '|' . $b['code'];
        $tx[$k] = ['at' => $b['paid_at'], 'unit' => $b['uname'], 'code' => $b['code'], 'name' => $b['customer_name'], 'method' => $b['method'],
            'bill' => (int) $b['price'], 'fnb' => 0, 'parts' => ['Billing ' . $b['label'] . ' (' . dur((int) $b['minutes']) . ')'], 'b' => (int) $b['id'], 'o' => 0];
    }
    $oq = $d->prepare("SELECT o.*, s.customer_name, u.name uname FROM orders o JOIN play_sessions s ON s.id=o.session_id JOIN units u ON u.id=o.unit_id WHERE o.pay_status='lunas' AND DATE(o.paid_at) BETWEEN ? AND ?");
    $oq->execute([$from, $to]);
    $iq = $d->prepare('SELECT qty, item_name FROM order_items WHERE order_id = ?');
    foreach ($oq->fetchAll() as $o) {
        $iq->execute([$o['id']]);
        $foods = implode(', ', array_map(fn($x) => $x['qty'] . 'x ' . $x['item_name'], $iq->fetchAll()));
        $k = $o['session_id'] . '|' . $o['code'];
        if (!isset($tx[$k])) $tx[$k] = ['at' => $o['paid_at'], 'unit' => $o['uname'], 'code' => $o['code'], 'name' => $o['customer_name'], 'method' => $o['method'], 'bill' => 0, 'fnb' => 0, 'parts' => [], 'b' => 0, 'o' => 0];
        $tx[$k]['fnb'] += (int) $o['subtotal']; $tx[$k]['parts'][] = $foods; $tx[$k]['o'] = (int) $o['id'];
    }
    foreach ($tx as &$t) { $t['total'] = $t['bill'] + $t['fnb']; $t['desc'] = implode(' + ', $t['parts']); } unset($t);
    $tx = array_values($tx);
    usort($tx, fn($a, $b) => strcmp($b['at'], $a['at']));
    return $tx;
}

/** Menu terjual + HPP + untung. $limit 0 = semua. HPP: snapshot saat dipesan; kalau 0 pakai HPP menu sekarang. */
function report_menus(PDO $d, string $from, string $to, int $limit = 10): array {
    $tail = " WHERE o.pay_status = 'lunas' AND DATE(o.paid_at) BETWEEN ? AND ? GROUP BY i.item_name ORDER BY qty DESC" . ($limit > 0 ? ' LIMIT ' . (int) $limit : '');
    try {
        $q = $d->prepare("SELECT i.item_name, SUM(i.qty) qty, SUM(i.qty * i.price) omzet, SUM(i.qty * COALESCE(NULLIF(i.cost, 0), m.cost, 0)) cost FROM order_items i JOIN orders o ON o.id = i.order_id LEFT JOIN menu_items m ON m.id = i.item_id" . $tail);
        $q->execute([$from, $to]); return $q->fetchAll();
    } catch (Throwable $e) {
        $q = $d->prepare("SELECT i.item_name, SUM(i.qty) qty, SUM(i.qty * i.price) omzet, 0 cost FROM order_items i JOIN orders o ON o.id = i.order_id" . $tail);
        $q->execute([$from, $to]); return $q->fetchAll();
    }
}
/** Pemasukan per TV: jumlah transaksi billing, menit terjual, omzet billing & makan/minum. */
function report_units(PDO $d, string $from, string $to): array {
    $out = [];
    foreach ($d->query('SELECT id, name, unit_type FROM units ORDER BY unit_number')->fetchAll() as $u)
        $out[(int) $u['id']] = ['name' => $u['name'], 'type' => trim($u['unit_type']) !== '' ? trim($u['unit_type']) : 'Tanpa tipe', 'n' => 0, 'mins' => 0, 'bill' => 0, 'fnb' => 0, 'total' => 0];
    $q = $d->prepare("SELECT s.unit_id, COUNT(*) n, SUM(b.minutes) mins, SUM(b.price) omzet FROM billings b JOIN play_sessions s ON s.id = b.session_id WHERE b.pay_status = 'lunas' AND DATE(b.paid_at) BETWEEN ? AND ? GROUP BY s.unit_id");
    $q->execute([$from, $to]);
    foreach ($q->fetchAll() as $r) if (isset($out[(int) $r['unit_id']])) { $k = (int) $r['unit_id']; $out[$k]['n'] = (int) $r['n']; $out[$k]['mins'] = (int) $r['mins']; $out[$k]['bill'] = (int) $r['omzet']; }
    $q = $d->prepare("SELECT unit_id, SUM(subtotal) omzet FROM orders WHERE pay_status = 'lunas' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY unit_id");
    $q->execute([$from, $to]);
    foreach ($q->fetchAll() as $r) if (isset($out[(int) $r['unit_id']])) $out[(int) $r['unit_id']]['fnb'] = (int) $r['omzet'];
    foreach ($out as &$u) $u['total'] = $u['bill'] + $u['fnb']; unset($u);
    uasort($out, fn($a, $b) => $b['total'] <=> $a['total']);
    return array_values($out);
}
/** Gabungkan hasil report_units per tipe (PS 3 / PS 4 / PS 5 ...). */
function report_types(array $units): array {
    $g = [];
    foreach ($units as $u) {
        $k = norm_type($u['type']);
        $g[$k] ??= ['type' => $u['type'], 'tvs' => 0, 'n' => 0, 'mins' => 0, 'bill' => 0, 'fnb' => 0, 'total' => 0];
        $g[$k]['tvs']++; foreach (['n', 'mins', 'bill', 'fnb', 'total'] as $f) $g[$k][$f] += $u[$f];
    }
    uasort($g, fn($a, $b) => $b['total'] <=> $a['total']);
    return array_values($g);
}

function report_data(PDO $d, string $from, string $to): array {
    $tx = report_tx($d, $from, $to);
    $daily = []; $methods = [];
    foreach ($tx as $t) {
        $day = substr($t['at'], 0, 10);
        $daily[$day] ??= ['n' => 0, 'bill' => 0, 'fnb' => 0, 'total' => 0];
        $daily[$day]['n']++; $daily[$day]['bill'] += $t['bill']; $daily[$day]['fnb'] += $t['fnb']; $daily[$day]['total'] += $t['total'];
        $m = $t['method'] ?: '-'; $methods[$m] ??= ['n' => 0, 'total' => 0]; $methods[$m]['n']++; $methods[$m]['total'] += $t['total'];
    }
    krsort($daily);
    $pq = $d->prepare("SELECT label, COUNT(*) n, SUM(price) omzet FROM billings WHERE pay_status='lunas' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY label ORDER BY n DESC, omzet DESC LIMIT 10");
    $pq->execute([$from, $to]);
    $allMenus = report_menus($d, $from, $to, 0); $menus = array_slice($allMenus, 0, 10);
    $fnbCost = (int) array_sum(array_column($allMenus, 'cost'));
    $bill = array_sum(array_column($tx, 'bill')); $fnb = array_sum(array_column($tx, 'fnb'));
    return ['from' => $from, 'to' => $to, 'tx' => $tx, 'daily' => $daily, 'methods' => $methods, 'pkgs' => $pq->fetchAll(), 'menus' => $menus, 'fnb_cost' => $fnbCost, 'units' => report_units($d, $from, $to),
        'n' => count($tx), 'bill' => $bill, 'fnb' => $fnb, 'total' => $bill + $fnb];
}

// ---------------------------------------------------------------- PDF
// Desain: kertas putih, tipografi serif untuk judul & angka, aksen emas tipis, garis rambut (tanpa blok warna).
require_once __DIR__ . '/fpdf/fpdf.php';

function pdf_t(string $s): string {   // UTF-8 -> Windows-1252 (font bawaan FPDF)
    if (function_exists('mb_convert_encoding')) return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    if (function_exists('iconv')) return (string) iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
    return preg_replace('/[^\x20-\x7E]/', '?', $s);
}
function pdf_rp(int $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }

class ReportPDF extends FPDF {
    const INK = [24, 24, 27], MUTE = [118, 118, 126], HAIR = [226, 226, 230], GOLD = [168, 133, 76];   // ganti GOLD kalau mau aksen warna lain
    const L = 18, W = 174;                                                                                 // margin kiri & lebar konten (A4)
    public string $brand = 'RENTAL PS KU', $periode = '', $printed = '';

    function ink(array $c): void { $this->SetTextColor(...$c); }
    function rule(float $y, array $c, float $w = .2, ?float $x1 = null, ?float $x2 = null): void {
        $this->SetDrawColor(...$c); $this->SetLineWidth($w); $this->Line($x1 ?? self::L, $y, $x2 ?? self::L + self::W, $y);
    }
    /** Lebar teks dengan jarak antar-huruf. */
    function spW(string $s, float $sp): float { $s = pdf_t($s); $w = 0; for ($i = 0, $n = strlen($s); $i < $n; $i++) $w += $this->GetStringWidth($s[$i]) + ($i < $n - 1 ? $sp : 0); return $w; }
    /** Teks berjarak huruf, posisi baseline y; $al: L (dari x) atau R (berakhir di x). */
    function sp(float $x, float $y, string $s, float $sp, string $al = 'L'): void {
        $t = pdf_t($s); if ($al === 'R') $x -= $this->spW($s, $sp);
        for ($i = 0, $n = strlen($t); $i < $n; $i++) { $this->Text($x, $y, $t[$i]); $x += $this->GetStringWidth($t[$i]) + $sp; }
    }
    function Header() {
        $this->SetFillColor(...self::GOLD); $this->Rect(0, 0, 210, 1.1, 'F');
        if ($this->PageNo() === 1) {
            $this->SetFont('Helvetica', 'B', 8); $this->ink(self::GOLD); $this->sp(self::L, 20, $this->brand, 1.5);
            $this->SetFont('Times', '', 31); $this->ink(self::INK); $this->Text(self::L, 35, 'Laporan Pemasukan');
            $this->SetFont('Helvetica', '', 6.8); $this->ink(self::MUTE); $this->sp(self::L + self::W, 20, 'PERIODE', 1.1, 'R');
            $this->SetFont('Times', 'I', 12.5); $this->ink(self::INK); $this->Text(self::L + self::W - $this->GetStringWidth(pdf_t($this->periode)), 27.5, pdf_t($this->periode));
            $this->rule(43, self::INK, .35);
            $this->SetY(52);
        } else {
            $this->SetFont('Helvetica', 'B', 7); $this->ink(self::GOLD); $this->sp(self::L, 13, $this->brand, 1.3);
            $this->SetFont('Times', 'I', 9.5); $this->ink(self::MUTE); $this->Text(self::L + self::W - $this->GetStringWidth(pdf_t($this->periode)), 13, pdf_t($this->periode));
            $this->rule(17, self::HAIR, .25);
            $this->SetY(26);
        }
    }
    function Footer() {
        $this->rule(283, self::HAIR, .25);
        $this->SetFont('Helvetica', '', 7); $this->ink(self::MUTE);
        $this->Text(self::L, 288, pdf_t('Rental PS Ku  ·  Laporan Pemasukan' . ($this->printed !== '' ? '  ·  ' . $this->printed : '')));
        $pg = 'Halaman ' . $this->PageNo() . ' dari {nb}';
        $this->Text(self::L + self::W - $this->GetStringWidth($this->nbReplace($pg)), 288, $pg);
    }
    private function nbReplace(string $s): string { return str_replace('{nb}', (string) max(1, $this->PageNo()), $s); }

    function section(string $title): void {
        if ($this->GetY() > 245) $this->AddPage();
        $this->Ln(9); $y = $this->GetY() + 4;
        $this->SetFont('Helvetica', 'B', 7.8); $this->ink(self::INK); $this->sp(self::L, $y, mb_strtoupper($title), 1.05);
        $this->SetFillColor(...self::GOLD); $this->Rect(self::L, $y + 2.4, 7, .55, 'F');
        $this->SetY($y + 6.5);
    }
    /** Pecah teks jadi baris-baris sesuai lebar kolom (font harus sudah di-set). */
    function wrap(string $s, float $w): array {
        $s = pdf_t($s); $lines = []; $cur = '';
        foreach (preg_split('/\s+/', trim($s)) as $word) {
            while ($this->GetStringWidth($word) > $w) {              // kata lebih panjang dari kolom: potong paksa
                $k = strlen($word); while ($k > 1 && $this->GetStringWidth(substr($word, 0, $k)) > $w) $k--;
                if ($cur !== '') { $lines[] = $cur; $cur = ''; }
                $lines[] = substr($word, 0, $k); $word = substr($word, $k);
            }
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($cur !== '' && $this->GetStringWidth($try) > $w) { $lines[] = $cur; $cur = $word; } else $cur = $try;
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines ?: [''];
    }
    /**
     * @param float[] $w lebar kolom  @param string[] $al L/R/C per kolom
     * @param array|null $foot baris total  @param int $wrapCol indeks kolom yang teksnya dibungkus (-1 = tidak ada)
     */
    function table(array $head, array $rows, array $w, array $al, ?array $foot = null, float $fs = 9, int $wrapCol = -1): void {
        $lh = $fs * .5; $pad = 1.9;
        $hdr = function () use ($head, $w, $al) {
            $this->SetFont('Helvetica', 'B', 6.8); $this->ink(self::MUTE); $y = $this->GetY(); $x = self::L;
            foreach ($head as $i => $h) {
                $t = mb_strtoupper($h); $cw = $w[$i] - 3; $tx = $x + 1.5;
                if ($al[$i] === 'R') $tx = $x + $w[$i] - 1.5 - $this->spW($t, .45); elseif ($al[$i] === 'C') $tx = $x + ($w[$i] - $this->spW($t, .45)) / 2;
                $this->sp($tx, $y + 4.2, $t, .45); $x += $w[$i];
            }
            $this->rule($y + 6.2, self::INK, .3); $this->SetY($y + 6.2);
        };
        $hdr();
        if (!$rows) { $this->SetFont('Times', 'I', 10); $this->ink(self::MUTE); $this->Cell(self::W, 10, 'Tidak ada data.', 0, 1, 'C'); }
        $this->SetFont('Helvetica', '', $fs);
        foreach ($rows as $r) {
            $this->SetFont('Helvetica', '', $fs);
            $cells = []; $n = 1;
            foreach ($r as $i => $c) { $cells[$i] = $i === $wrapCol ? $this->wrap((string) $c, $w[$i] - 3) : [$this->fit((string) $c, $w[$i])]; $n = max($n, count($cells[$i])); }
            $h = $n * $lh + $pad * 2;
            if ($this->GetY() + $h > 276) { $this->AddPage(); $hdr(); $this->SetFont('Helvetica', '', $fs); }
            $y = $this->GetY(); $x = self::L; $this->ink(self::INK);
            foreach ($cells as $i => $ls) {
                foreach ($ls as $k => $ln) { $this->SetXY($x + 1.5, $y + $pad + $k * $lh); $this->Cell($w[$i] - 3, $lh, $ln, 0, 0, $al[$i]); }
                $x += $w[$i];
            }
            $this->rule($y + $h, self::HAIR, .15); $this->SetY($y + $h);
        }
        if ($foot) {
            $y = $this->GetY(); $this->rule($y + .4, self::INK, .3);
            $this->SetFont('Helvetica', 'B', $fs); $this->ink(self::INK); $x = self::L;
            foreach ($foot as $i => $c) { $this->SetXY($x + 1.5, $y + 1.8); $this->Cell($w[$i] - 3, 6, pdf_t((string) $c), 0, 0, $al[$i]); $x += $w[$i]; }
            $this->SetY($y + 9);
        }
    }
    function fit(string $s, float $w): string {
        $s = pdf_t($s); if ($this->GetStringWidth($s) <= $w - 3) return $s;
        while ($s !== '' && $this->GetStringWidth($s . '...') > $w - 3) $s = substr($s, 0, -1);
        return rtrim($s) . '...';
    }
}

function build_report_pdf(array $r, string $who): ReportPDF {
    $pdf = new ReportPDF('P', 'mm', 'A4');
    $pdf->SetTitle(pdf_t('Laporan Pemasukan - ' . $pdf->brand), false); $pdf->SetAuthor(pdf_t($pdf->brand), false);
    $pdf->periode = $r['from'] === $r['to'] ? tgl_id($r['from']) : tgl_id($r['from']) . '  -  ' . tgl_id($r['to']);
    $pdf->printed = 'Dicetak ' . date('d/m/Y H:i') . ($who !== '' ? ' oleh ' . $who : '');
    $pdf->SetMargins(ReportPDF::L, 26, ReportPDF::L); $pdf->SetAutoPageBreak(true, 22); $pdf->AliasNbPages(); $pdf->AddPage();

    // ---- ringkasan: satu angka besar + empat metrik pendamping
    $avg = $r['n'] ? (int) round($r['total'] / $r['n']) : 0; $y = $pdf->GetY();
    $pdf->SetFillColor(...ReportPDF::GOLD); $pdf->Rect(ReportPDF::L, $y, 10, .7, 'F');
    $pdf->SetFont('Helvetica', 'B', 7.2); $pdf->ink(ReportPDF::MUTE); $pdf->sp(ReportPDF::L, $y + 7, 'TOTAL PEMASUKAN', 1.2);
    $pdf->SetFont('Times', '', 40); $pdf->ink(ReportPDF::INK); $pdf->Text(ReportPDF::L, $y + 24, pdf_rp($r['total']));
    $my = $y + 33; $pdf->rule($my, ReportPDF::HAIR, .25);
    $cols = [['BILLING', pdf_rp($r['bill'])], ['MAKAN & MINUM', pdf_rp($r['fnb'])], ['TRANSAKSI', (string) $r['n']], ['RATA-RATA', pdf_rp($avg)]];
    $cw = ReportPDF::W / 4;
    foreach ($cols as $i => [$lb, $val]) {
        $x = ReportPDF::L + $i * $cw + ($i ? 5 : 0);
        if ($i) { $pdf->SetDrawColor(...ReportPDF::HAIR); $pdf->SetLineWidth(.25); $pdf->Line(ReportPDF::L + $i * $cw, $my + 5, ReportPDF::L + $i * $cw, $my + 22); }
        $pdf->SetFont('Helvetica', 'B', 6.4); $pdf->ink(ReportPDF::MUTE); $pdf->sp($x, $my + 9, $lb, .8);
        $pdf->SetFont('Times', '', 15); $pdf->ink(ReportPDF::INK); $pdf->Text($x, $my + 18, pdf_t($val));
    }
    $pdf->rule($my + 26, ReportPDF::HAIR, .25); $pdf->SetY($my + 26);

    $pdf->section('Pemasukan per Hari');
    $rows = []; foreach ($r['daily'] as $day => $v) $rows[] = [tgl_id($day), $v['n'], pdf_rp($v['bill']), pdf_rp($v['fnb']), pdf_rp($v['total'])];
    $pdf->table(['Tanggal', 'Transaksi', 'Billing', 'Makan & Minum', 'Total'], $rows, [40, 24, 36, 38, 36], ['L', 'C', 'R', 'R', 'R'], ['Total', $r['n'], pdf_rp($r['bill']), pdf_rp($r['fnb']), pdf_rp($r['total'])]);

    if ($r['methods']) {
        $pdf->section('Per Metode Pembayaran');
        $rows = []; foreach ($r['methods'] as $m => $v) $rows[] = [$m, $v['n'], pdf_rp($v['total'])];
        $pdf->table(['Metode', 'Transaksi', 'Total'], $rows, [70, 40, 64], ['L', 'C', 'R']);
    }
    if ($r['pkgs']) {
        $pdf->section('Paket Billing Terlaris');
        $rows = []; foreach ($r['pkgs'] as $i => $p) $rows[] = [$i + 1, $p['label'], $p['n'] . 'x', pdf_rp((int) $p['omzet'])];
        $pdf->table(['#', 'Paket', 'Terjual', 'Pemasukan'], $rows, [12, 90, 28, 44], ['C', 'L', 'C', 'R']);
    }
    if ($r['menus']) {
        $pdf->section('Menu Makan & Minum Terlaris');
        $rows = []; foreach ($r['menus'] as $i => $p) $rows[] = [$i + 1, $p['item_name'], $p['qty'] . 'x', pdf_rp((int) $p['omzet'])];
        $pdf->table(['#', 'Menu', 'Terjual', 'Pemasukan'], $rows, [12, 90, 28, 44], ['C', 'L', 'C', 'R']);
    }

    $pdf->section('Rincian Transaksi');
    $rows = []; foreach ($r['tx'] as $t) $rows[] = [date('d/m H:i', strtotime($t['at'])), $t['unit'], $t['code'], $t['name'], $t['desc'], $t['method'], pdf_rp($t['total'])];
    $pdf->table(['Waktu', 'TV', 'Kode', 'Pemesan', 'Rincian', 'Metode', 'Total'], $rows, [21, 14, 16, 24, 56, 15, 28], ['L', 'L', 'L', 'L', 'L', 'L', 'R'], null, 8, 4);
    return $pdf;
}
