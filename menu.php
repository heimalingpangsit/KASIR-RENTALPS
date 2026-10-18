<?php
require __DIR__ . '/config.php';
require __DIR__ . '/rental.php';
boot();
$d = db();
expire_unit_session($d);
$u = null;
if (!empty($_SESSION['unit_id'])) { $q = $d->prepare('SELECT * FROM units WHERE id = ? AND is_active = 1'); $q->execute([$_SESSION['unit_id']]); $u = $q->fetch() ?: null; }
$s = $u ? open_session($d, (int) $u['id']) : null;
if (!$s || $s['status'] !== 'aktif') { header('Location: app.php'); exit; }

$items = $d->query('SELECT * FROM menu_items ORDER BY category, sort_order, name')->fetchAll();
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $byId = array_column($items, null, 'id');
    $lines = []; $sub = 0;
    foreach ((array) ($_POST['qty'] ?? []) as $mid => $qty) {
        $qty = max(0, min(20, (int) $qty));
        if ($qty && isset($byId[$mid]) && $byId[$mid]['stock_status'] === 'tersedia') { $lines[] = [$byId[$mid], $qty]; $sub += $byId[$mid]['price'] * $qty; }
    }
    if (!$lines) { $err = 'Pilih minimal satu item.'; }
    else {
        $token = bin2hex(random_bytes(16));
        ensure_menu_cols($d);
        try {
        $d->beginTransaction();
        $d->prepare('INSERT INTO orders (session_id, unit_id, code, token, notes, subtotal, method, created_at) VALUES (?,?,?,?,?,?,?,?)')
          ->execute([$s['id'], $u['id'], code6(), $token, mb_substr(trim($_POST['notes'] ?? ''), 0, 255), $sub, ($_POST['method'] ?? '') === 'qris' ? 'QRIS' : 'Tunai', now()]);
        $oid = (int) $d->lastInsertId();
        $ins = $d->prepare('INSERT INTO order_items (order_id, item_name, price, qty, cost, item_id) VALUES (?,?,?,?,?,?)');
        foreach ($lines as [$m, $qty]) { take_stock($d, (int) $m['id'], (int) $qty, (string) $m['name']); $ins->execute([$oid, $m['name'], $m['price'], $qty, (int) ($m['cost'] ?? 0), (int) $m['id']]); }
        $d->commit();
        header('Location: pay.php?o=' . $oid . '&t=' . $token); exit;
        } catch (StockException $ex) { if ($d->inTransaction()) $d->rollBack(); $err = $ex->getMessage();
        } catch (Throwable $ex) { if ($d->inTransaction()) $d->rollBack(); throw $ex; }
    }
}
head('Menu', '', '<a href="app.php" class="chip">← Timer</a>');
echo '<main class="wrap"><h1>Makan &amp; minum</h1><p class="mu">Diantar ke ' . e($u['name']) . ' setelah pembayaran dikonfirmasi.</p>';
if ($err) echo '<div class="msg">' . e($err) . '</div>';
if (!$items) { echo '<div class="card c mu">Menu belum tersedia.</div></main>'; foot(); exit; }
echo '<form method="post">' . csrf_field();
$cat = null;
foreach ($items as $m) {
    if ($m['category'] !== $cat) { if ($cat !== null) echo '</div>'; $cat = $m['category']; echo '<div class="card"><h2>' . e($cat) . '</h2>'; }
    $off = $m['stock_status'] !== 'tersedia';
    echo '<div class="menu-item' . ($off ? ' off' : '') . '">' . ($m['image'] ? '<img src="' . e($m['image']) . '" alt="" loading="lazy">' : '<span class="ph"><i class="fa-solid fa-utensils"></i></span>')
       . '<div class="i"><b>' . e($m['name']) . '</b><span class="mu sm">' . e($m['description']) . '</span><div><b>' . rp((int) $m['price']) . '</b></div></div>';
    if ($off) echo '<span class="pill r">Habis</span>';
    else echo '<div class="stp" data-price="' . (int) $m['price'] . '"><button type="button" data-d="-1" aria-label="Kurangi">−</button><input name="qty[' . (int) $m['id'] . ']" value="0" readonly aria-label="Jumlah"><button type="button" data-d="1" aria-label="Tambah">+</button></div>';
    echo '</div>';
}
echo '</div><div class="card"><label class="f" for="nt">Catatan (opsional)</label><input id="nt" name="notes" maxlength="255" placeholder="mis. es batunya dikit">';
echo '<label class="f">Bayar pakai</label><div class="pay"><label class="pkg"><input type="radio" name="method" value="qris" checked> QRIS</label><label class="pkg"><input type="radio" name="method" value="tunai"> Tunai di kasir</label></div></div>';
echo '<div class="bar"><div><b id="sum">Rp 0</b><div class="mu sm" id="cnt">0 item</div></div><button class="btn red" id="go" disabled>Pesan sekarang</button></div></form></main>';
foot();
