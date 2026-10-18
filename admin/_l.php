<?php
// Layout + auth panel admin. Di-include paling atas setiap halaman admin.
require __DIR__ . '/../config.php';
require __DIR__ . '/../rental.php';
boot();
$GLOBALS['adm'] = true;   // tema terang ala dashboard (lihat body.adm di style.css)
$d = db();
ensure_call_col($d);
ensure_menu_cols($d);
if (empty($_SESSION['staff_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') { header('Location: login.php'); exit; }
$sid = (int) ($_SESSION['staff_id'] ?? 0);

function counts(PDO $d): array {
    $p = (int) $d->query("SELECT (SELECT COUNT(*) FROM billings b JOIN play_sessions s ON s.id=b.session_id WHERE b.pay_status IN ('pending','menunggu_konfirmasi') AND s.status IN ('menunggu_bayar','aktif')) + (SELECT COUNT(*) FROM orders WHERE pay_status IN ('pending','menunggu_konfirmasi') AND status = 'menunggu_bayar')")->fetchColumn();
    $o = (int) $d->query("SELECT COUNT(*) FROM orders WHERE status = 'baru_masuk'")->fetchColumn();
    $c = (int) $d->query("SELECT COUNT(*) FROM play_sessions WHERE status = 'aktif' AND call_at IS NOT NULL")->fetchColumn();
    $who = $d->query("SELECT u.name FROM play_sessions s JOIN units u ON u.id = s.unit_id WHERE s.status = 'aktif' AND s.call_at IS NOT NULL ORDER BY s.call_at")->fetchAll(PDO::FETCH_COLUMN);
    $t = time(); $ts = $d->prepare("SELECT u.name, s.ends_at FROM play_sessions s JOIN units u ON u.id = s.unit_id WHERE s.status = 'aktif' AND s.ends_at <= ? ORDER BY s.ends_at");
    $ts->execute([date('Y-m-d H:i:s', $t + 300)]); $soon = []; $over = [];   // sisa <= 5 menit / sudah habis
    foreach ($ts->fetchAll() as $r) { if (strtotime($r['ends_at']) <= $t) $over[] = $r['name']; else $soon[] = $r['name']; }
    return ['pay' => $p, 'ord' => $o, 'call' => $c, 'who' => $who, 'soon' => count($soon), 'soonwho' => $soon, 'over' => count($over), 'overwho' => $over];
}
if (isset($_GET['badge'])) { header('Content-Type: application/json'); echo json_encode(counts($d)); exit; }

function a_head(string $t): void {
    global $d;
    $c = counts($d); $cur = basename($_SERVER['PHP_SELF']);
    $nav = ['dashboard.php' => 'Dashboard', 'units.php' => 'Unit & QR', 'packages.php' => 'Paket Billing', 'orders.php' => 'Pesanan', 'kasir.php' => 'Pembayaran', 'riwayat.php' => 'Riwayat', 'laporan.php' => 'Laporan', 'members.php' => 'Member', 'menu.php' => 'Menu', 'info.php' => 'Info Toko'];
    head($t, '../', '<span class="who"><button type="button" id="alarmBtn" class="alarmbtn" aria-live="polite"></button>' . e($_SESSION['staff_name'] ?? '') . ' · Staff <a href="account.php"><i class="fa-solid fa-key"></i> Ganti Sandi</a><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a></span>');
    echo '<nav class="nav">';
    foreach ($nav as $h => $l) {
        $b = $h === 'dashboard.php' ? '<span class="bd" data-b="call" data-n="' . $c['call'] . '"><i class="fa-solid fa-bell"></i> ' . $c['call'] . '</span>' : ($h === 'kasir.php' ? '<span class="bd" data-b="pay" data-n="' . $c['pay'] . '">' . $c['pay'] . '</span>' : ($h === 'orders.php' ? '<span class="bd" data-b="ord" data-n="' . $c['ord'] . '">' . $c['ord'] . '</span>' : ''));
        echo '<a href="' . $h . '" class="' . ($cur === $h ? 'on' : '') . '">' . $l . $b . '</a>';
    }
    echo '</nav><main class="wrap wide">' . (empty($GLOBALS['noh1']) ? '<h1>' . e($t) . '</h1>' : '');
}
function a_foot(): void {
    $c = counts(db()); $v = @filemtime(__DIR__ . '/../assets/admin.js');
    echo '</main><script>window.ADM=' . json_encode(['last' => $c, 'live' => !empty($GLOBALS['live'])]) . ';</script><script src="../assets/admin.js?v=' . $v . '"></script>';
    foot('../');
}
function flash(?string $m, string $cls = 'ok'): void { if ($m) echo '<div class="msg ' . $cls . '">' . e($m) . '</div>'; }
