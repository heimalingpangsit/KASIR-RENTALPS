<?php
// Halaman bayar: billing (?b=) atau pesanan makan/minum (?o=)
require __DIR__ . '/config.php';
require __DIR__ . '/rental.php';
boot();
$d = db();
$isOrder = isset($_GET['o']);
$tbl = $isOrder ? 'orders' : 'billings';
$id = (int) ($isOrder ? $_GET['o'] : ($_GET['b'] ?? 0));
$q = $d->prepare("SELECT * FROM $tbl WHERE id = ?"); $q->execute([$id]); $r = $q->fetch();
if (!$r || !hash_equals((string) $r['token'], (string) ($_GET['t'] ?? ''))) { header('Location: app.php'); exit; }
$total = (int) ($isOrder ? $r['subtotal'] : $r['price']);
// Checkout gabungan: pesanan makan/minum dengan kode bayar yang sama ikut dibayar bersama billing
$linked = [];
if (!$isOrder) {
    $lq = $d->prepare("SELECT * FROM orders WHERE session_id = ? AND code = ? AND pay_status != 'gagal'"); $lq->execute([$r['session_id'], $r['code']]);
    $linked = $lq->fetchAll();
    foreach ($linked as $lo) $total += (int) $lo['subtotal'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d->prepare("UPDATE $tbl SET pay_status = 'menunggu_konfirmasi' WHERE id = ? AND pay_status = 'pending'")->execute([$id]);
    foreach ($linked as $lo) $d->prepare("UPDATE orders SET pay_status = 'menunggu_konfirmasi' WHERE id = ? AND pay_status = 'pending'")->execute([$lo['id']]);
    header('Location: pay.php?' . ($isOrder ? 'o' : 'b') . '=' . $id . '&t=' . urlencode($r['token'])); exit;
}
$done = $r['pay_status'] === 'lunas';
$back = $isOrder ? 'menu.php' : 'app.php';
head('Pembayaran');
echo '<main class="wrap"><div class="card c">';
if ($done) {
    echo '<i class="fa-solid fa-circle-check" style="font-size:3rem;color:var(--ok)"></i><h1 class="mt">Pembayaran diterima</h1><p class="mu">' . ($isOrder ? 'Pesananmu sedang disiapkan dan akan diantar ke unit.' : ($linked ? 'Timer main sudah jalan dan pesanan makan/minummu sedang disiapkan. Selamat bermain!' : 'Timer main sudah jalan. Selamat bermain!')) . '</p><a class="btn red blk" href="app.php">Kembali ke timer</a><a class="btn blk mt" target="_blank" href="struk.php?' . ($isOrder ? 'o' : 'b') . '=' . $id . '&t=' . urlencode($r['token']) . '"><i class="fa-solid fa-receipt"></i> Lihat struk</a>';
} elseif ($r['pay_status'] === 'gagal') {
    echo '<i class="fa-solid fa-circle-xmark" style="font-size:3rem;color:var(--red)"></i><h1 class="mt">Pembayaran ditolak</h1><p class="mu">Kasir belum menerima pembayaran ini. Tanya operator, atau coba pesan ulang.</p><a class="btn blk" href="app.php">Kembali</a>';
} else {
    echo '<div class="mu">Total bayar</div><h1 style="font-size:2rem">' . rp($total) . '</h1>';
    if (!$isOrder && ($ri = member_redeem_info($d, (int) $r['session_id'], (string) $r['code']))) echo '<div class="mu sm" style="margin:6px 0">Sudah termasuk diskon poin member −' . rp((int) $ri['amount']) . ' (' . (int) $ri['points'] . ' poin)</div>';
    if ($linked) {   // rincian gabungan
        echo '<div class="mu sm" style="text-align:left;margin:8px 0">Billing ' . e($r['label']) . ' · ' . rp((int) $r['price']);
        foreach ($linked as $lo) { $iq = $d->prepare('SELECT qty, item_name FROM order_items WHERE order_id = ?'); $iq->execute([$lo['id']]);
            foreach ($iq->fetchAll() as $it) echo '<br>' . (int) $it['qty'] . 'x ' . e($it['item_name']); }
        echo '</div>';
    }
    if ($r['method'] === 'QRIS') {
        echo '<p class="mu sm">Scan QRIS di bawah pakai e-wallet / m-banking, lalu tekan tombol di bawah.</p>';
        if (is_file(__DIR__ . '/' . QRIS_IMAGE)) echo '<div class="qris"><img src="' . e(QRIS_IMAGE) . '" alt="QRIS"></div>';
        else echo '<div class="msg">Gambar QRIS belum dipasang (taruh file di <code>' . e(QRIS_IMAGE) . '</code>).</div>';
    } else {
        echo '<p class="mu">Tunjukkan kode ini ke kasir dan bayar tunai.</p>';
    }
    echo '<div class="mt mu sm">Kode</div><div class="code">' . e($r['code']) . '</div>';
    if ($r['pay_status'] === 'menunggu_konfirmasi') {
        echo '<p class="mt"><span class="pill w">Menunggu konfirmasi kasir…</span></p><script>(function r(){setTimeout(function(){if(document.hidden){r();return}location.reload()},8000)})();document.addEventListener("visibilitychange",function(){if(!document.hidden)location.reload()})</script>';
    } else {
        echo '<form method="post" class="mt">' . csrf_field() . '<button class="btn red blk">' . ($r['method'] === 'QRIS' ? 'Saya sudah bayar' : 'Oke, saya ke kasir') . '</button></form>';
    }
}
echo '</div></main>';
foot();
