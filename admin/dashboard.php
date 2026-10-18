<?php
require __DIR__ . '/_l.php';
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'finish') { finish_session($d, (int) $_POST['sid']); $msg = 'Sesi diselesaikan.'; }
    if ($act === 'cancel') {   // batalkan sesi yang nyangkut di 'menunggu bayar' (pelanggan kabur / salah pilih)
        $sidc = (int) $_POST['sid'];
        $bq = $d->prepare("SELECT id FROM billings WHERE session_id = ? AND pay_status IN ('pending','menunggu_konfirmasi') ORDER BY id DESC LIMIT 1"); $bq->execute([$sidc]);
        if ($bid = $bq->fetchColumn()) reject_billing($d, (int) $bid);
        $d->prepare("UPDATE play_sessions SET status = 'dibatalkan', ended_at = ? WHERE id = ? AND status = 'menunggu_bayar'")->execute([now(), $sidc]);
        $msg = 'Sesi dibatalkan, TV kosong lagi.';
    }
    if ($act === 'ack') { ack_call($d, (int) $_POST['sid']); $msg = 'Panggilan ditandai sudah dihampiri.'; }
    if ($act === 'start' || $act === 'extend') {   // input manual oleh kasir (walk-in / tambah jam), langsung lunas tunai
        $unit = (int) $_POST['unit']; $uq = $d->prepare('SELECT * FROM units WHERE id = ?'); $uq->execute([$unit]); $un = $uq->fetch();
        $pkg = $un ? get_package($d, (int) $_POST['package_id'], $un['unit_type'], (int) $un['id']) : null;
        $s = $un ? open_session($d, $unit) : null;
        if (!$pkg) { $msg = 'Pilih paket dulu.'; }
        elseif ($act === 'start' && !$s) { $b = start_session($d, $unit, trim($_POST['name'] ?? '') ?: 'Walk-in', '', $pkg, 'Tunai'); confirm_billing($d, $b['id'], $sid); $msg = 'Sesi dimulai.'; }
        elseif ($act === 'extend' && $s && $s['status'] === 'aktif') { $b = new_billing($d, (int) $s['id'], $pkg, 'Tunai'); confirm_billing($d, $b['id'], $sid); $msg = 'Waktu ditambah.'; }
    }
    $_SESSION['flash'] = $msg; header('Location: dashboard.php'); exit;
}
$msg = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$units = $d->query("SELECT u.*, s.id sid, s.status sst, s.customer_name, s.ends_at, s.code scode, s.call_at,
 (SELECT COALESCE(SUM(price),0) FROM billings b WHERE b.session_id=s.id AND b.pay_status='lunas') bill,
 (SELECT COALESCE(SUM(subtotal),0) FROM orders o WHERE o.session_id=s.id AND o.pay_status='lunas') fnb
 FROM units u LEFT JOIN play_sessions s ON s.unit_id=u.id AND s.status IN ('menunggu_bayar','aktif') WHERE u.is_active=1 ORDER BY u.unit_number")->fetchAll();
$t0 = date('Y-m-d 00:00:00');
$qb = $d->prepare("SELECT COALESCE(SUM(price),0) FROM billings WHERE pay_status='lunas' AND paid_at >= ?"); $qb->execute([$t0]);
$qf = $d->prepare("SELECT COALESCE(SUM(subtotal),0) FROM orders WHERE pay_status='lunas' AND paid_at >= ?"); $qf->execute([$t0]);
$bill = (int) $qb->fetchColumn(); $fnb = (int) $qf->fetchColumn();
$qn = $d->prepare("SELECT (SELECT COUNT(*) FROM billings WHERE pay_status='lunas' AND paid_at >= ?) + (SELECT COUNT(*) FROM orders WHERE pay_status='lunas' AND paid_at >= ?)"); $qn->execute([$t0, $t0]);
$trx = (int) $qn->fetchColumn();
$aktif = (int) $d->query("SELECT COUNT(*) FROM orders WHERE status = 'baru_masuk'")->fetchColumn();
$busy = count(array_filter($units, fn($u) => $u['sst'] === 'aktif'));

$live = true; $GLOBALS['noh1'] = true;
a_head('Dashboard');
flash($msg);
$stat = [[count($units), 'Total TV'], [$busy, 'TV Terisi'], [$aktif, 'Pesanan Aktif'], [rp($bill + $fnb), 'Omzet Hari Ini (' . $trx . ' transaksi)']];
echo '<span data-now="' . time() . '"></span>';
foreach ($units as $u) if ($u['sst'] === 'aktif' && $u['call_at']) {
    echo '<div class="callbar"><i class="fa-solid fa-bell fa-shake"></i><div><b>' . e($u['name']) . ' memanggil staff</b><span class="mu sm">' . e($u['customer_name']) . ' · sejak ' . date('H:i', strtotime($u['call_at'])) . '</span></div>'
       . '<form method="post">' . csrf_field() . '<input type="hidden" name="act" value="ack"><input type="hidden" name="sid" value="' . (int) $u['sid'] . '"><button class="btn red sm"><i class="fa-solid fa-check"></i> Sudah dihampiri</button></form></div>';
}
echo '<div class="stat">';
foreach ($stat as [$v, $l]) echo '<div class="card"><b>' . e($v) . '</b><span class="mu sm">' . e($l) . '</span></div>';
echo '</div><p class="mu sm" style="margin:-6px 0 18px">Billing ' . rp($bill) . ' · Makan &amp; minum ' . rp($fnb) . '</p>';
if (!$units) echo '<div class="card mu">Belum ada TV. Tambah dulu di menu <a href="units.php">Unit &amp; QR</a>.</div>';
echo '<h2 class="sec">Status TV</h2><div class="tvs">';
$dlg = '';
foreach ($units as $u) {
    $on = $u['sst'] === 'aktif'; $wait = !$on && $u['sst'];
    $st = $on ? 'DIPAKAI' : ($wait ? 'MENUNGGU BAYAR' : 'KOSONG');
    $id = (int) $u['id']; $call = $on && $u['call_at'];
    echo '<div class="tvc"><button type="button" class="tv' . ($on ? ' on' : ($wait ? ' wait' : '')) . ($call ? ' call' : '') . '" data-dlg="d' . $id . '"><b>' . e($u['name']) . '</b><i class="dot"></i>'
        . ($on ? '<span class="clock" data-end="' . strtotime($u['ends_at']) . '">--:--:--</span>' : '') . '<small>' . $st . '</small>'
        . ($call ? '<span class="callbadge"><i class="fa-solid fa-bell"></i> MEMANGGIL</span>' : '') . '</button>';
    if ($call) echo '<form method="post">' . csrf_field() . '<input type="hidden" name="act" value="ack"><input type="hidden" name="sid" value="' . (int) $u['sid'] . '"><button class="btn sm blk"><i class="fa-solid fa-check"></i> Dihampiri</button></form>';
    if ($on) echo '<form method="post" data-confirm="Selesaikan sesi ' . e($u['name']) . '?">' . csrf_field() . '<input type="hidden" name="act" value="finish"><input type="hidden" name="sid" value="' . (int) $u['sid'] . '"><button class="btn sm del blk"><i class="fa-solid fa-flag-checkered"></i> Selesaikan</button></form>';
    echo '</div>';
    $pk = packages_for($d, $u['unit_type'], $id);
    $opts = ''; foreach ($pk as $p) $opts .= '<option value="' . (int) $p['id'] . '">' . e(pkg_label($p)) . ' — ' . rp((int) $p['price']) . '</option>';
    $dlg .= '<dialog class="dlg" id="d' . $id . '"><div class="dh"><b><i class="fa-solid fa-tv"></i> ' . e($u['name']) . '</b><span class="pill ' . ($on ? 'r' : ($wait ? 'w' : 'ok')) . '">' . ($on ? 'Dipakai' : ($wait ? 'Menunggu bayar' : 'Kosong')) . '</span><button type="button" class="x" data-close aria-label="Tutup">&times;</button></div><div class="db">';
    if ($u['unit_type']) $dlg .= '<p class="mu sm" style="margin:0 0 8px">' . e($u['unit_type']) . '</p>';
    if ($on) {
        $dlg .= '<span class="clock" data-end="' . strtotime($u['ends_at']) . '">--:--:--</span><div class="mu sm">' . e($u['customer_name']) . ' · Billing ' . rp((int) $u['bill']) . ' · F&amp;B ' . rp((int) $u['fnb']) . '</div>';
        if ($call) $dlg .= '<form method="post" class="mt">' . csrf_field() . '<input type="hidden" name="act" value="ack"><input type="hidden" name="sid" value="' . (int) $u['sid'] . '"><button class="btn red sm blk"><i class="fa-solid fa-bell"></i> Memanggil staff · tandai sudah dihampiri</button></form>';
        $dlg .= '<form method="post" class="mt"><b class="sm">Tambah waktu</b>' . csrf_field() . '<input type="hidden" name="act" value="extend"><input type="hidden" name="unit" value="' . $id . '"><select name="package_id" required style="margin-top:6px">' . $opts . '</select><button class="btn red sm blk mt">Tambah (tunai)</button></form>';
        $dlg .= '<form method="post" class="mt" data-confirm="Selesaikan sesi ' . e($u['name']) . '?">' . csrf_field() . '<input type="hidden" name="act" value="finish"><input type="hidden" name="sid" value="' . (int) $u['sid'] . '"><button class="btn sm del blk">Selesaikan sesi</button></form>';
    } elseif ($wait) {
        $dlg .= '<p class="mu">' . e($u['customer_name']) . ' belum bayar. Cek menu <a href="kasir.php">Pembayaran</a>.</p><form method="post" class="mt" data-confirm="Batalkan sesi ' . e($u['name']) . '? TV jadi kosong lagi.">' . csrf_field() . '<input type="hidden" name="act" value="cancel"><input type="hidden" name="sid" value="' . (int) $u['sid'] . '"><button class="btn sm del blk">Batalkan sesi</button></form>';
    } else {
        $dlg .= '<form method="post"><b class="sm">Mulai manual</b>' . csrf_field() . '<input type="hidden" name="act" value="start"><input type="hidden" name="unit" value="' . $id . '"><input name="name" placeholder="Nama (opsional)" style="margin-top:6px"><select name="package_id" required style="margin-top:8px">' . $opts . '</select><button class="btn red sm blk mt">Mulai (tunai)</button></form>';
    }
    $dlg .= '</div></dialog>';
}
echo '</div>' . $dlg;
a_foot();
