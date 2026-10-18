<?php
require __DIR__ . '/_l.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d->prepare("UPDATE orders SET status = 'selesai' WHERE id = ? AND status = 'baru_masuk'")->execute([(int) $_POST['id']]);
    header('Location: orders.php'); exit;
}
$os = $d->query("SELECT o.*, s.customer_name, u.name uname FROM orders o JOIN play_sessions s ON s.id=o.session_id JOIN units u ON u.id=o.unit_id WHERE o.status='baru_masuk' ORDER BY o.paid_at")->fetchAll();
$live = true;
a_head('Antrian Makan & Minum');
if (!$os) echo '<div class="card c mu">Belum ada pesanan masuk.</div>';
echo '<div class="grid">';
foreach ($os as $o) {
    $it = $d->prepare('SELECT qty, item_name FROM order_items WHERE order_id = ?'); $it->execute([$o['id']]);
    echo '<div class="card unit on"><div class="row" style="border:0;padding:0"><b>' . e($o['uname']) . '</b><span class="mu sm">' . date('H:i', strtotime($o['paid_at'])) . '</span></div><div class="mu sm">' . e($o['customer_name']) . '</div><ul style="padding-left:18px">';
    foreach ($it->fetchAll() as $x) echo '<li><b>' . (int) $x['qty'] . 'x</b> ' . e($x['item_name']) . '</li>';
    echo '</ul>' . ($o['notes'] ? '<div class="msg sm">' . e($o['notes']) . '</div>' : '') . '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $o['id'] . '"><button class="btn red blk">Sudah diantar</button></form></div>';
}
echo '</div>';
a_foot();
