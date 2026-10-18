<?php
require __DIR__ . '/_l.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) $_POST['id']; $ok = ($_POST['act'] ?? '') === 'ok';
    if (($_POST['kind'] ?? '') === 'b') { $ok ? confirm_billing($d, $id, $sid) : reject_billing($d, $id); }
    else { $ok ? confirm_order($d, $id, $sid) : reject_order($d, $id); }
    header('Location: kasir.php'); exit;
}
$bills = $d->query("SELECT b.*, s.customer_name, u.name uname FROM billings b JOIN play_sessions s ON s.id=b.session_id JOIN units u ON u.id=s.unit_id WHERE b.pay_status IN ('pending','menunggu_konfirmasi') AND s.status IN ('menunggu_bayar','aktif') ORDER BY b.id")->fetchAll();
$ords = $d->query("SELECT o.*, s.customer_name, u.name uname FROM orders o JOIN play_sessions s ON s.id=o.session_id JOIN units u ON u.id=o.unit_id WHERE o.pay_status IN ('pending','menunggu_konfirmasi') AND o.status='menunggu_bayar' ORDER BY o.id")->fetchAll();
$rows = [];
$foods = function (array $o) use ($d): string {
    $it = $d->prepare('SELECT qty, item_name FROM order_items WHERE order_id = ?'); $it->execute([$o['id']]);
    return implode(', ', array_map(fn($x) => $x['qty'] . 'x ' . $x['item_name'], $it->fetchAll())) . ($o['notes'] ? ' — ' . $o['notes'] : '');
};
$merged = [];   // pesanan yang sekode dengan billing (checkout gabungan) ditampilkan di satu baris
foreach ($bills as $b) {
    $what = 'Billing ' . $b['label'] . ' (' . dur((int) $b['minutes']) . ')'; $tot = (int) $b['price'];
    foreach ($ords as $o) if ($o['session_id'] == $b['session_id'] && $o['code'] === $b['code']) { $what .= ' + ' . $foods($o); $tot += (int) $o['subtotal']; $merged[$o['id']] = true; }
    if ($ri = member_redeem_info($d, (int) $b['session_id'], (string) $b['code'])) $what .= ' · DISKON POIN −' . rp((int) $ri['amount']) . ' (' . ($ri['name'] ?: 'member') . ' ' . $ri['phone'] . ') — cek orangnya benar';
    $rows[] = ['k' => 'b', 'r' => $b, 'what' => $what, 'total' => $tot];
}
foreach ($ords as $o) if (empty($merged[$o['id']])) $rows[] = ['k' => 'o', 'r' => $o, 'what' => $foods($o), 'total' => $o['subtotal']];
$live = true;
a_head('Pembayaran');
echo '<input id="q" placeholder="Cari kode / nama / unit…" class="noprint" style="max-width:340px;margin-bottom:14px">';
if (!$rows) echo '<div class="card c mu">Tidak ada pembayaran yang menunggu. 🎮</div>';
else {
    echo '<div class="card ov"><table><tr><th>Kode</th><th>Unit / Pemesan</th><th>Tagihan</th><th>Total</th><th>Metode</th><th></th></tr>';
    foreach ($rows as $x) {
        $r = $x['r'];
        echo '<tr data-s="' . e(strtolower($r['code'] . ' ' . $r['customer_name'] . ' ' . $r['uname'])) . '"><td><b>' . e($r['code']) . '</b></td><td><b>' . e($r['uname']) . '</b><div class="mu sm">' . e($r['customer_name']) . '</div></td><td>' . e($x['what']) . '</td><td><b>' . rp((int) $x['total']) . '</b></td><td>' . e($r['method']) . '<br>' . pay_pill($r['pay_status']) . '</td><td><div class="inl">';
        foreach (['ok' => ['red', 'Lunas'], 'no' => ['del', 'Tolak']] as $a => [$c, $l])
            echo '<form method="post"' . ($a === 'no' ? ' data-confirm="Tolak pembayaran ini?"' : '') . '>' . csrf_field() . '<input type="hidden" name="kind" value="' . $x['k'] . '"><input type="hidden" name="id" value="' . (int) $r['id'] . '"><input type="hidden" name="act" value="' . $a . '"><button class="btn sm ' . $c . '">' . $l . '</button></form>';
        echo '</div></td></tr>';
    }
    echo '</table></div>';
}
a_foot();
