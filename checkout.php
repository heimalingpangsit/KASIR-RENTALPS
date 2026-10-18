<?php
// Langkah 2 setelah pilih billing/menu: isi nama + nomor TV + metode bayar, lalu buat tagihan.
require __DIR__ . '/config.php';
require __DIR__ . '/rental.php';
boot();
$d = db();
expire_unit_session($d);
$u = null;
if (!empty($_SESSION['unit_id'])) { $q = $d->prepare('SELECT * FROM units WHERE id = ? AND is_active = 1'); $q->execute([$_SESSION['unit_id']]); $u = $q->fetch() ?: null; }
if (!$u || $_SERVER['REQUEST_METHOD'] !== 'POST' || open_session($d, (int) $u['id'])) { header('Location: app.php'); exit; }
csrf_check();

// Pilihan dibaca ulang & dihitung dari database (harga dari client tidak dipercaya)
$pkg = get_package($d, (int) ($_POST['package_id'] ?? 0), $u['unit_type'], (int) $u['id']);
$byId = array_column($d->query('SELECT * FROM menu_items')->fetchAll(), null, 'id');
$lines = []; $sub = 0;
foreach ((array) ($_POST['qty'] ?? []) as $mid => $qty) {
    $qty = max(0, min(20, (int) $qty));
    if ($qty && isset($byId[$mid]) && $byId[$mid]['stock_status'] === 'tersedia') { $lines[] = [$byId[$mid], $qty]; $sub += (int) $byId[$mid]['price'] * $qty; }
}
if (!$pkg && !$lines) { $_SESSION['err'] = 'Pilih paket billing atau menu dulu.'; header('Location: app.php'); exit; }
$total = ($pkg ? (int) $pkg['price'] : 0) + $sub;

$err = null;
if (isset($_POST['name'])) {   // langkah 2 disubmit
    $name = trim($_POST['name']); $tv = (int) ($_POST['tv'] ?? 0);
    $method = ($_POST['method'] ?? '') === 'qris' ? 'QRIS' : 'Tunai';
    if ($name === '') $err = 'Nama wajib diisi.';
    elseif ($tv !== (int) $u['unit_number']) $err = 'Nomor TV tidak sesuai dengan QR yang kamu scan (TV ' . (int) $u['unit_number'] . ').';
    else {
        // tukar poin member -> diskon billing (poin dipotong dulu; balik lagi kalau gagal/ditolak kasir)
        $phN = norm_phone(trim($_POST['phone'] ?? '')); $usePts = 0; $disc = 0;
        if ($pkg && !empty($_POST['use_points']) && $phN !== '') {
            [$usePts, $disc] = member_redeem_calc($d, $phN, (int) $pkg['price']);
            if ($usePts && !member_take_points($d, $phN, $usePts)) { $usePts = 0; $disc = 0; }
        }
        $pkgPay = $pkg; if ($pkgPay && $disc) $pkgPay['price'] = (int) $pkg['price'] - $disc;
        try {
            $r = place_checkout($d, $u, mb_substr($name, 0, 100), mb_substr(trim($_POST['phone'] ?? ''), 0, 30), $pkgPay, $lines, trim($_POST['notes'] ?? ''), $method);
            if ($usePts && $r['b']) member_log_redeem($d, $phN, $usePts, $disc, (int) $r['b']['id']);
            $x = $r['b'] ?: $r['o'];
            header('Location: pay.php?' . ($r['b'] ? 'b=' : 'o=') . $x['id'] . '&t=' . $x['token']); exit;
        } catch (Throwable $ex) {
            if ($usePts) member_give_points($d, $phN, $usePts);
            if (!($ex instanceof StockException)) throw $ex;
            $err = $ex->getMessage();
        }
    }
}

head('Pembayaran', '', '<span class="chip">' . e($u['name']) . '</span>');
echo '<main class="wrap"><a href="app.php" class="mu sm">← Ubah pilihan</a><h1 class="mt">Ringkasan pesanan</h1>';
if ($err) echo '<div class="msg">' . e($err) . '</div>';
echo '<div class="card">';
if ($pkg) echo '<div class="row"><div><b>Billing ' . e(pkg_label($pkg)) . '</b><div class="mu sm">' . e(dur((int) $pkg['minutes'])) . '</div></div><b>' . rp((int) $pkg['price']) . '</b></div>';
foreach ($lines as [$m, $qty]) echo '<div class="row"><div><b>' . $qty . 'x ' . e($m['name']) . '</b></div><b>' . rp((int) $m['price'] * $qty) . '</b></div>';
echo '<div class="row" style="border:0"><span class="mu">Total</span><b style="font-size:1.3rem">' . rp($total) . '</b></div></div>';

echo '<form method="post">' . csrf_field();
if ($pkg) echo '<input type="hidden" name="package_id" value="' . (int) $pkg['id'] . '">';
foreach ($lines as [$m, $qty]) echo '<input type="hidden" name="qty[' . (int) $m['id'] . ']" value="' . $qty . '">';
echo '<label class="f" for="n">Nama</label><input id="n" name="name" required maxlength="100" value="' . e($_POST['name'] ?? '') . '">';
echo '<label class="f" for="tv">Nomor TV</label><input id="tv" name="tv" required inputmode="numeric" pattern="[0-9]*" maxlength="4" value="' . e($_POST['tv'] ?? (string) (int) $u['unit_number']) . '">';
echo '<label class="f" for="p">No. WhatsApp (opsional, untuk poin member)</label><input id="p" name="phone" inputmode="tel" maxlength="30" value="' . e($_POST['phone'] ?? '') . '">';
$mc = member_cfg($d);
if ($mc['on']) {
    echo '<div class="mu sm" style="margin-top:6px">Isi nomor WhatsApp untuk kumpulkan poin member: tiap ' . rp($mc['earn']) . ' = 1 poin.</div>';
    if ($pkg) echo '<label class="f chk"><input type="checkbox" name="use_points" value="1"' . (!empty($_POST['use_points']) ? ' checked' : '') . '> Tukar poin jadi diskon billing</label><div class="mu sm">1 poin = ' . rp($mc['value']) . ' · minimal ' . $mc['min'] . ' poin · pakai nomor WA yang sama</div>';
}
if ($lines) echo '<label class="f" for="nt">Catatan pesanan (opsional)</label><input id="nt" name="notes" maxlength="255" placeholder="mis. es batunya dikit" value="' . e($_POST['notes'] ?? '') . '">';
$m = ($_POST['method'] ?? 'qris') === 'tunai' ? 'tunai' : 'qris';
echo '<label class="f">Bayar pakai</label><div class="pay"><label class="pkg"><input type="radio" name="method" value="qris"' . ($m === 'qris' ? ' checked' : '') . '> QRIS</label><label class="pkg"><input type="radio" name="method" value="tunai"' . ($m === 'tunai' ? ' checked' : '') . '> Tunai di kasir</label></div>';
echo '<button class="btn red blk mt">Bayar ' . rp($total) . '</button></form></main>';
foot();
