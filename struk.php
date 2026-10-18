<?php
// Struk pembayaran (diadaptasi dari struk.php proyek market). Buka: struk.php?b=ID (billing) atau ?o=ID (pesanan makan/minum).
// Akses: staf yang sudah login (cetak ulang apa saja) ATAU pelanggan dengan token (&t=...) dari halaman bayar.
require __DIR__ . '/config.php';
require __DIR__ . '/rental.php';
boot();
$d = db();
$isOrder = isset($_GET['o']);
$id = (int) ($isOrder ? $_GET['o'] : ($_GET['b'] ?? 0));
$tok = (string) ($_GET['t'] ?? '');

$q = $d->prepare($isOrder ? 'SELECT * FROM orders WHERE id = ?' : 'SELECT * FROM billings WHERE id = ?');
$q->execute([$id]); $row = $q->fetch();
$isStaff = !empty($_SESSION['staff_id']);
$okTok = $row && $tok !== '' && hash_equals((string) $row['token'], $tok);
if (!$row || $row['pay_status'] !== 'lunas' || (!$isStaff && !$okTok)) {
    http_response_code(404); exit('Struk tidak ditemukan (belum dibayar atau tidak ada).');
}

$sid = (int) $row['session_id'];
// billing + pesanan dengan kode sama = 1 struk gabungan
$bill = null; $orders = [];
if ($isOrder) {
    $orders[] = $row;
    $bq = $d->prepare("SELECT * FROM billings WHERE session_id = ? AND code = ? AND pay_status = 'lunas'"); $bq->execute([$sid, $row['code']]); $bill = $bq->fetch() ?: null;
} else $bill = $row;
if ($bill) { $oq = $d->prepare("SELECT * FROM orders WHERE session_id = ? AND code = ? AND pay_status = 'lunas' ORDER BY id"); $oq->execute([$sid, $bill['code']]); $orders = $oq->fetchAll(); }

$sq = $d->prepare('SELECT s.customer_name, u.name uname FROM play_sessions s JOIN units u ON u.id = s.unit_id WHERE s.id = ?'); $sq->execute([$sid]); $ses = $sq->fetch();
$main = $bill ?: $orders[0];
$cashier = '';
if (!empty($main['cashier_id'])) { $cq = $d->prepare('SELECT name FROM staff_users WHERE id = ?'); $cq->execute([$main['cashier_id']]); $cashier = (string) $cq->fetchColumn(); }
$isCash = ($main['method'] ?? 'Tunai') === 'Tunai';
$subtitle = $isCash ? 'Struk Pembayaran (Tunai - Bayar di Kasir)' : 'Struk Pembayaran (' . $main['method'] . ')';

$lines = []; $total = 0;
if ($bill) { $lines[] = ['1x', $bill['label'] . ' (' . dur((int) $bill['minutes']) . ')', (int) $bill['price']]; $total += (int) $bill['price']; }
$iq = $d->prepare('SELECT qty, item_name, price FROM order_items WHERE order_id = ?');
foreach ($orders as $o) { $iq->execute([$o['id']]); foreach ($iq->fetchAll() as $it) $lines[] = [(int) $it['qty'] . 'x', $it['item_name'], (int) $it['qty'] * (int) $it['price']]; $total += (int) $o['subtotal']; }
$paidAt = $main['paid_at'] ?? null;
$brand = strtoupper(APP_NAME);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Struk <?= e($main['code']) ?> — <?= e(APP_NAME) ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Courier New', Courier, monospace; background: #000; display: flex; justify-content: center; padding: 24px 0; color: #111; }
    .receipt { width: 80mm; max-width: 320px; background: #fff; padding: 14px 14px 12px; border-top: 5px solid #e8202f; box-shadow: 0 0 28px rgba(232,32,47,.35); }
    .center { text-align: center; }
    .name { font-weight: bold; font-size: 17px; letter-spacing: .06em; }
    .muted { color: #555; font-size: 11px; }
    hr { border: none; border-top: 1px dashed #999; margin: 8px 0; }
    .row { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; margin: 2px 0; }
    .row span:last-child { text-align: right; }
    .row.total { font-weight: bold; font-size: 14px; }
    table.items { width: 100%; font-size: 12px; border-collapse: collapse; margin: 6px 0; }
    table.items td { padding: 2px 0; vertical-align: top; }
    .item-name { padding-right: 6px; }
    .qty-col { width: 26px; }
    .price-col { text-align: right; white-space: nowrap; }
    .footer-note { font-size: 11px; text-align: center; margin-top: 10px; }
    .btns { width: 80mm; max-width: 320px; margin: 14px auto 0; display: grid; gap: 8px; }
    .btn { display: block; text-align: center; padding: 10px; font: bold 14px sans-serif; background: #e8202f; color: #fff; border: 0; border-radius: 8px; cursor: pointer; text-decoration: none; }
    .btn.alt { background: #17171a; border: 1px solid #2a2a2f; }
    @media print {
        body { background: #fff; padding: 0; }
        .receipt { box-shadow: none; width: 100%; max-width: none; border-top: 0; }
        .btns { display: none; }
        @page { margin: 0; size: 80mm auto; }
    }
</style>
</head>
<body>
    <div>
        <div class="receipt">
            <div class="center">
                <div class="name"><?= e($brand) ?></div>
                <div class="muted"><?= e($subtitle) ?></div>
            </div>
            <hr>
            <div class="row"><span>Kode</span><span>#<?= e($main['code']) ?></span></div>
            <div class="row"><span>TV</span><span><?= e($ses['uname'] ?? '-') ?></span></div>
            <div class="row"><span>Tanggal</span><span><?= date('d/m/Y H:i', strtotime($main['created_at'])) ?></span></div>
            <div class="row"><span>Pemesan</span><span><?= e($ses['customer_name'] ?? '-') ?></span></div>
            <hr>
            <table class="items">
                <?php foreach ($lines as [$qty, $nm, $pr]): ?>
                <tr><td class="qty-col"><?= e($qty) ?></td><td class="item-name"><?= e($nm) ?></td><td class="price-col"><?= number_format($pr, 0, ',', '.') ?></td></tr>
                <?php endforeach; ?>
            </table>
            <hr>
            <div class="row total"><span>TOTAL</span><span>Rp <?= number_format($total, 0, ',', '.') ?></span></div>
            <hr>
            <div class="row"><span>Metode</span><span><?= e($main['method'] ?? 'Tunai') ?></span></div>
            <?php if ($cashier !== ''): ?><div class="row"><span>Dikonfirmasi oleh</span><span><?= e($cashier) ?></span></div><?php endif; ?>
            <div class="row"><span>Dibayar</span><span><?= $paidAt ? date('d/m/Y H:i', strtotime($paidAt)) : '-' ?></span></div>
            <div class="footer-note">Terima kasih sudah main di<br><?= e(APP_NAME) ?></div>
        </div>
        <div class="btns">
            <button class="btn" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
            <?php if ($isStaff): ?><a class="btn alt" href="javascript:history.length>1?history.back():window.close()">Kembali</a><?php endif; ?>
        </div>
    </div>
</body>
</html>
