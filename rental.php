<?php
// Logika inti: sesi main, billing, pembayaran.
function code6(): string { return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT); }
function dur(int $m): string { $h = intdiv($m, 60); $r = $m % 60; return trim(($h ? $h . ' jam ' : '') . ($r ? $r . ' menit' : '')); }
/** Kolom penanda 'pelanggan manggil staff' (dibuat otomatis kalau belum ada; SQL manualnya ada di database/call_staff.sql). */
function ensure_call_col(PDO $d): void {
    static $done = false; if ($done) return; $done = true;
    try { if (!$d->query("SHOW COLUMNS FROM play_sessions LIKE 'call_at'")->fetch()) $d->exec('ALTER TABLE play_sessions ADD call_at DATETIME NULL'); } catch (Throwable $e) {}
}
/** Pelanggan menekan 'Panggil staff'. true = sesi aktif (panggilan tercatat / sudah menunggu). */
function call_staff(PDO $d, int $sid): bool {
    ensure_call_col($d);
    $d->prepare("UPDATE play_sessions SET call_at = ? WHERE id = ? AND status = 'aktif' AND call_at IS NULL")->execute([now(), $sid]);
    $q = $d->prepare("SELECT COUNT(*) FROM play_sessions WHERE id = ? AND status = 'aktif'"); $q->execute([$sid]);
    return (bool) $q->fetchColumn();
}
function ack_call(PDO $d, int $sid): void { ensure_call_col($d); $d->prepare('UPDATE play_sessions SET call_at = NULL WHERE id = ?')->execute([$sid]); }
function open_session(PDO $d, int $unit): ?array {
    ensure_call_col($d);
    $q = $d->prepare("SELECT * FROM play_sessions WHERE unit_id = ? AND status IN ('menunggu_bayar','aktif') ORDER BY id DESC LIMIT 1");
    $q->execute([$unit]);
    return $q->fetch() ?: null;
}
function pkg_label(array $p): string { return $p['label']; }
/** Tabel penghubung paket <-> TV (dibuat otomatis kalau belum ada; SQL manualnya ada di database/package_units.sql). */
function ensure_pkg_units(PDO $d): void {
    static $done = false; if ($done) return; $done = true;
    try { $d->exec('CREATE TABLE IF NOT EXISTS package_units (package_id INT UNSIGNED NOT NULL, unit_id INT UNSIGNED NOT NULL, PRIMARY KEY (package_id, unit_id)) ENGINE=InnoDB'); } catch (Throwable $e) {}
}
/** @return array<int,int[]> package_id => [unit_id,...] (hanya paket yang sudah dipilihkan TV-nya) */
function package_assignments(PDO $d): array {
    ensure_pkg_units($d); $as = [];
    try { foreach ($d->query('SELECT package_id, unit_id FROM package_units')->fetchAll() as $r) $as[(int) $r['package_id']][] = (int) $r['unit_id']; } catch (Throwable $e) {}
    return $as;
}
/**
 * Paket yang tampil untuk satu TV: paket yang TV-nya dicentang admin.
 * Paket lama yang belum pernah dipilihkan TV memakai aturan lama (kolom tipe kosong = semua TV).
 */
function norm_type(string $t): string { return strtoupper(preg_replace('/\s+/', '', $t)); }
function packages_for(PDO $d, string $type, int $unitId = 0): array {
    $as = package_assignments($d); $out = [];
    foreach ($d->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order, minutes, id")->fetchAll() as $p) {
        $pid = (int) $p['id'];
        $ok = isset($as[$pid]) ? ($unitId > 0 && in_array($unitId, $as[$pid], true))
                               : ($p['unit_type'] === '' || $type === '' || norm_type($p['unit_type']) === norm_type($type));
        if ($ok) $out[] = $p;
    }
    return $out;
}
function get_package(PDO $d, int $id, string $type, int $unitId = 0): ?array {
    foreach (packages_for($d, $type, $unitId) as $p) if ((int) $p['id'] === $id) return $p;
    return null;
}
function new_billing(PDO $d, int $sid, array $pkg, string $method): array {
    $token = bin2hex(random_bytes(16));
    $d->prepare('INSERT INTO billings (session_id, code, token, label, minutes, price, method, created_at) VALUES (?,?,?,?,?,?,?,?)')
      ->execute([$sid, code6(), $token, pkg_label($pkg), $pkg['minutes'], $pkg['price'], $method, now()]);
    return ['id' => (int) $d->lastInsertId(), 'token' => $token];
}
function start_session(PDO $d, int $unit, string $name, string $phone, array $pkg, string $method): array {
    $d->prepare('INSERT INTO play_sessions (code, unit_id, customer_name, customer_phone, created_at) VALUES (?,?,?,?,?)')
      ->execute([code6(), $unit, $name, $phone, now()]);
    return new_billing($d, (int) $d->lastInsertId(), $pkg, $method);
}
/** Billing lunas -> sesi mulai (pertama kali) atau waktu bertambah (tambah jam). */
function confirm_billing(PDO $d, int $bid, ?int $staff): void {
    $d->beginTransaction();
    $q = $d->prepare('SELECT * FROM billings WHERE id = ? FOR UPDATE'); $q->execute([$bid]); $b = $q->fetch();
    if (!$b || $b['pay_status'] === 'lunas') { $d->rollBack(); return; }
    $q = $d->prepare('SELECT * FROM play_sessions WHERE id = ? FOR UPDATE'); $q->execute([$b['session_id']]); $s = $q->fetch();
    if (!$s || in_array($s['status'], ['selesai', 'dibatalkan'], true)) { $d->rollBack(); return; }
    $d->prepare("UPDATE billings SET pay_status = 'lunas', paid_at = ?, cashier_id = ? WHERE id = ?")->execute([now(), $staff, $bid]);
    $t = time(); $sec = (int) $b['minutes'] * 60;
    if ($s['status'] === 'menunggu_bayar') {
        $d->prepare("UPDATE play_sessions SET status = 'aktif', started_at = ?, ends_at = ?, minutes_total = ? WHERE id = ?")
          ->execute([date('Y-m-d H:i:s', $t), date('Y-m-d H:i:s', $t + $sec), $b['minutes'], $s['id']]);
    } else { // tambah jam: lanjut dari sisa waktu (atau dari sekarang kalau sudah lewat)
        $end = max($t, strtotime($s['ends_at'])) + $sec;
        $d->prepare('UPDATE play_sessions SET ends_at = ?, minutes_total = minutes_total + ? WHERE id = ?')
          ->execute([date('Y-m-d H:i:s', $end), $b['minutes'], $s['id']]);
    }
    $d->commit();
    $oq = $d->prepare("SELECT COALESCE(SUM(subtotal),0) FROM orders WHERE session_id = ? AND code = ? AND pay_status != 'lunas' AND status = 'menunggu_bayar'");
    $oq->execute([$b['session_id'], $b['code']]); $fb = (int) $oq->fetchColumn();
    // checkout gabungan (billing + makan/minum): pesanan dengan kode bayar yang sama ikut lunas
    $d->prepare("UPDATE orders SET pay_status = 'lunas', status = 'baru_masuk', paid_at = ?, cashier_id = ? WHERE session_id = ? AND code = ? AND pay_status != 'lunas' AND status = 'menunggu_bayar'")
      ->execute([now(), $staff, $b['session_id'], $b['code']]);
    member_earn($d, (int) $b['session_id'], (int) $b['price'] + $fb);
}
function reject_billing(PDO $d, int $bid): void {
    $q = $d->prepare('SELECT session_id, code, pay_status FROM billings WHERE id = ?'); $q->execute([$bid]);
    if (($lb = $q->fetch()) && $lb['pay_status'] !== 'lunas') {
        $oq = $d->prepare("SELECT id FROM orders WHERE session_id = ? AND code = ? AND pay_status != 'lunas' AND status != 'dibatalkan'"); $oq->execute([$lb['session_id'], $lb['code']]);
        foreach ($oq->fetchAll(PDO::FETCH_COLUMN) as $oid) restock_order($d, (int) $oid);   // stok balik kalau dibatalkan
        member_refund($d, (int) $lb['session_id'], (string) $lb['code']);                   // poin yang dipakai balik
        $d->prepare("UPDATE orders SET pay_status = 'gagal', status = 'dibatalkan' WHERE session_id = ? AND code = ? AND pay_status != 'lunas'")->execute([$lb['session_id'], $lb['code']]);
    }
    $d->prepare("UPDATE billings SET pay_status = 'gagal' WHERE id = ? AND pay_status != 'lunas'")->execute([$bid]);
    $d->prepare("UPDATE play_sessions SET status = 'dibatalkan', ended_at = ? WHERE status = 'menunggu_bayar' AND id = (SELECT session_id FROM billings WHERE id = ?)")->execute([now(), $bid]);
}
function linked_billing_id(PDO $d, int $oid): ?int {
    $q = $d->prepare("SELECT b.id FROM billings b JOIN orders o ON o.session_id = b.session_id AND o.code = b.code WHERE o.id = ? AND b.pay_status != 'lunas' LIMIT 1");
    $q->execute([$oid]); $id = $q->fetchColumn();
    return $id ? (int) $id : null;
}
function confirm_order(PDO $d, int $oid, ?int $staff): void {
    $u = $d->prepare("UPDATE orders SET pay_status = 'lunas', status = 'baru_masuk', paid_at = ?, cashier_id = ? WHERE id = ? AND pay_status != 'lunas'"); $u->execute([now(), $staff, $oid]);
    if ($u->rowCount()) { $q = $d->prepare('SELECT session_id, subtotal FROM orders WHERE id = ?'); $q->execute([$oid]); if ($o = $q->fetch()) member_earn($d, (int) $o['session_id'], (int) $o['subtotal']); }
    if ($bid = linked_billing_id($d, $oid)) confirm_billing($d, $bid, $staff);
}
function reject_order(PDO $d, int $oid): void {
    $bid = linked_billing_id($d, $oid);
    restock_order_open($d, $oid);
    $d->prepare("UPDATE orders SET pay_status = 'gagal', status = 'dibatalkan' WHERE id = ? AND pay_status != 'lunas'")->execute([$oid]);
    if ($bid) reject_billing($d, $bid);
}
/**
 * Checkout dari halaman scan QR: billing (opsional) + makan/minum (opsional) dalam satu pembayaran.
 * Harga SELALU dari database. $lines = [[baris_menu, qty], ...]. Kode bayar billing & pesanan sama
 * supaya kasir cukup menekan Lunas sekali. Kalau hanya pesan makan (tanpa billing), sesi dibuat
 * berstatus 'selesai' (tanpa timer) hanya sebagai penampung pesanan.
 */
function place_checkout(PDO $d, array $u, string $name, string $phone, ?array $pkg, array $lines, string $notes, string $method): array {
    ensure_menu_cols($d);   // DDL harus sebelum transaksi
    $d->beginTransaction();
    try {
        $now = now(); $pay = code6(); $out = ['b' => null, 'o' => null];
        $d->prepare('INSERT INTO play_sessions (code, unit_id, customer_name, customer_phone, status, ended_at, created_at) VALUES (?,?,?,?,?,?,?)')
          ->execute([code6(), $u['id'], $name, $phone, $pkg ? 'menunggu_bayar' : 'selesai', $pkg ? null : $now, $now]);
        $sid = (int) $d->lastInsertId();
        if ($pkg) {
            $tk = bin2hex(random_bytes(16));
            $d->prepare('INSERT INTO billings (session_id, code, token, label, minutes, price, method, created_at) VALUES (?,?,?,?,?,?,?,?)')
              ->execute([$sid, $pay, $tk, pkg_label($pkg), $pkg['minutes'], $pkg['price'], $method, $now]);
            $out['b'] = ['id' => (int) $d->lastInsertId(), 'token' => $tk];
        }
        if ($lines) {
            $sub = 0; foreach ($lines as [$m, $qty]) $sub += (int) $m['price'] * $qty;
            $tk = bin2hex(random_bytes(16));
            $d->prepare('INSERT INTO orders (session_id, unit_id, code, token, notes, subtotal, method, created_at) VALUES (?,?,?,?,?,?,?,?)')
              ->execute([$sid, $u['id'], $pay, $tk, mb_substr($notes, 0, 255), $sub, $method, $now]);
            $oid = (int) $d->lastInsertId();
            $ins = $d->prepare('INSERT INTO order_items (order_id, item_name, price, qty, cost, item_id) VALUES (?,?,?,?,?,?)');
            foreach ($lines as [$m, $qty]) { take_stock($d, (int) $m['id'], (int) $qty, (string) $m['name']); $ins->execute([$oid, $m['name'], $m['price'], $qty, (int) ($m['cost'] ?? 0), (int) $m['id']]); }
            $out['o'] = ['id' => $oid, 'token' => $tk];
        }
        $d->commit();
        return $out;
    } catch (Throwable $ex) { if ($d->inTransaction()) $d->rollBack(); throw $ex; }
}
function finish_session(PDO $d, int $sid): void {
    ensure_call_col($d);
    $d->prepare("UPDATE play_sessions SET status = 'selesai', ended_at = ?, call_at = NULL WHERE id = ? AND status = 'aktif'")->execute([now(), $sid]);
}
function pay_pill(string $s): string {
    $m = ['pending' => ['w', 'Belum bayar'], 'menunggu_konfirmasi' => ['w', 'Menunggu kasir'], 'lunas' => ['ok', 'Lunas'], 'gagal' => ['r', 'Ditolak']][$s] ?? ['', $s];
    return '<span class="pill ' . $m[0] . '">' . e($m[1]) . '</span>';
}

/** Info toko untuk halaman promosi (alamat, jam buka, WhatsApp, Instagram) — diubah lewat admin/info.php. Tabel dibuat otomatis. */
function ensure_settings(PDO $d): void {
    static $done = false; if ($done) return; $done = true;
    try { $d->exec('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(50) NOT NULL PRIMARY KEY, v TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); } catch (Throwable $e) {}
}
/** @return array{address:string,hours:string,wa:string,ig:string} */
function shop_info(PDO $d): array {
    $o = ['address' => 'Samarinda, Kalimantan Timur', 'hours' => '', 'wa' => '', 'ig' => ''];
    try { ensure_settings($d); foreach ($d->query('SELECT k, v FROM settings')->fetchAll() as $r) if (strncmp($r['k'], 'shop_', 5) === 0 && array_key_exists(substr($r['k'], 5), $o)) $o[substr($r['k'], 5)] = (string) $r['v']; } catch (Throwable $e) {}
    return $o;
}
function shop_save(PDO $d, array $v): void {
    ensure_settings($d);
    $q = $d->prepare('REPLACE INTO settings (k, v) VALUES (?, ?)');
    foreach (['address', 'hours', 'wa', 'ig'] as $k) $q->execute(['shop_' . $k, (string) ($v[$k] ?? '')]);
}
/** "Senin - Jumat | 10.00 - 22.00" per baris -> [[hari, jam], ...] */
function shop_hours_list(string $t): array {
    $out = [];
    foreach (preg_split('/\R/', $t) as $ln) { $ln = trim($ln); if ($ln === '') continue; $p = explode('|', $ln, 2); $out[] = [trim($p[0]), trim($p[1] ?? '')]; }
    return $out;
}


// ======================================================================
// Stok menu + HPP, pengaturan, member/poin, kedaluwarsa sesi QR
// ======================================================================
class StockException extends RuntimeException {}

/** Kolom baru (dibuat otomatis sekali): menu_items.cost (HPP), menu_items.stock_qty (NULL = stok tidak dihitung), order_items.cost & item_id. */
function ensure_menu_cols(PDO $d): void {
    static $done = false; if ($done) return; $done = true;
    try {
        ensure_settings($d);
        $q = $d->prepare("SELECT v FROM settings WHERE k = 'schema_menu2'"); $q->execute();
        if ($q->fetchColumn() === '1') return;
        if (!$d->query("SHOW COLUMNS FROM menu_items LIKE 'cost'")->fetch()) $d->exec('ALTER TABLE menu_items ADD cost INT UNSIGNED NOT NULL DEFAULT 0');
        if (!$d->query("SHOW COLUMNS FROM menu_items LIKE 'stock_qty'")->fetch()) $d->exec('ALTER TABLE menu_items ADD stock_qty INT NULL');
        if (!$d->query("SHOW COLUMNS FROM order_items LIKE 'cost'")->fetch()) $d->exec('ALTER TABLE order_items ADD cost INT UNSIGNED NOT NULL DEFAULT 0');
        if (!$d->query("SHOW COLUMNS FROM order_items LIKE 'item_id'")->fetch()) $d->exec('ALTER TABLE order_items ADD item_id INT UNSIGNED NULL');
        $d->prepare('REPLACE INTO settings (k, v) VALUES (?, ?)')->execute(['schema_menu2', '1']);
    } catch (Throwable $e) {}
}
/** Kurangi stok saat pesanan dibuat (dipanggil di dalam transaksi). Stok NULL = tidak dihitung. Habis -> status otomatis 'habis'. */
function take_stock(PDO $d, int $id, int $qty, string $name): void {
    $q = $d->prepare('SELECT stock_qty FROM menu_items WHERE id = ? FOR UPDATE'); $q->execute([$id]); $v = $q->fetchColumn();
    if ($v === false || $v === null) return;
    if ((int) $v < $qty) throw new StockException('Stok ' . $name . ' tinggal ' . max(0, (int) $v) . '. Kurangi jumlahnya ya.');
    $d->prepare("UPDATE menu_items SET stock_qty = stock_qty - ?, stock_status = IF(stock_qty <= 0, 'habis', stock_status) WHERE id = ?")->execute([$qty, $id]);
}
/** Pesanan dibatalkan -> stok dikembalikan. */
function restock_order(PDO $d, int $oid): void {
    try {
        $q = $d->prepare('SELECT item_id, qty FROM order_items WHERE order_id = ? AND item_id IS NOT NULL'); $q->execute([$oid]);
        $u = $d->prepare("UPDATE menu_items SET stock_qty = stock_qty + ?, stock_status = IF(stock_qty > 0, 'tersedia', stock_status) WHERE id = ? AND stock_qty IS NOT NULL");
        foreach ($q->fetchAll() as $r) $u->execute([(int) $r['qty'], (int) $r['item_id']]);
    } catch (Throwable $e) {}
}
function restock_order_open(PDO $d, int $oid): void {
    try {
        $q = $d->prepare('SELECT status, pay_status FROM orders WHERE id = ?'); $q->execute([$oid]); $o = $q->fetch();
        if ($o && $o['status'] !== 'dibatalkan' && $o['pay_status'] !== 'lunas') restock_order($d, $oid);
    } catch (Throwable $e) {}
}

/** Pengaturan sederhana (tabel settings). */
function setting_get(PDO $d, string $k, string $def = ''): string {
    try { ensure_settings($d); $q = $d->prepare('SELECT v FROM settings WHERE k = ?'); $q->execute([$k]); $v = $q->fetchColumn(); return $v === false ? $def : (string) $v; } catch (Throwable $e) { return $def; }
}
function setting_set(PDO $d, string $k, string $v): void {
    ensure_settings($d); $d->prepare('REPLACE INTO settings (k, v) VALUES (?, ?)')->execute([$k, $v]);
}

/** Nomor WA -> 62xxxxxxxxxx ('' kalau tidak valid). */
function norm_phone(string $p): string {
    $x = preg_replace('/\D+/', '', $p); if ($x === '') return '';
    if ($x[0] === '0') $x = '62' . substr($x, 1); elseif ($x[0] === '8') $x = '62' . $x;
    return (strlen($x) >= 10 && strlen($x) <= 16) ? $x : '';
}
function ensure_members(PDO $d): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $d->exec("CREATE TABLE IF NOT EXISTS members (phone VARCHAR(20) NOT NULL PRIMARY KEY, name VARCHAR(100) NOT NULL DEFAULT '', points INT NOT NULL DEFAULT 0, spent BIGINT NOT NULL DEFAULT 0, visits INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, last_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $d->exec("CREATE TABLE IF NOT EXISTS member_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, phone VARCHAR(20) NOT NULL, session_id INT UNSIGNED NOT NULL DEFAULT 0, code VARCHAR(6) NOT NULL DEFAULT '', kind VARCHAR(10) NOT NULL, points INT NOT NULL, amount INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, INDEX (session_id, code), INDEX (phone)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}
/** @return array{on:bool,earn:int,value:int,min:int} earn = Rp per 1 poin; value = Rp diskon per 1 poin; min = minimal poin untuk ditukar */
function member_cfg(PDO $d): array {
    return ['on' => setting_get($d, 'member_on', '1') === '1', 'earn' => max(1, (int) setting_get($d, 'member_earn', '1000')),
            'value' => max(1, (int) setting_get($d, 'member_value', '50')), 'min' => max(1, (int) setting_get($d, 'member_min', '20'))];
}
/** Pembayaran lunas -> poin masuk ke member (kalau nomor WA diisi). Tidak pernah menggagalkan pembayaran. */
function member_earn(PDO $d, int $sid, int $amount): void {
    if ($amount <= 0) return;
    try {
        $c = member_cfg($d); if (!$c['on']) return;
        $q = $d->prepare('SELECT customer_name, customer_phone FROM play_sessions WHERE id = ?'); $q->execute([$sid]); $s = $q->fetch();
        $ph = $s ? norm_phone((string) $s['customer_phone']) : ''; if ($ph === '') return;
        ensure_members($d); $pts = intdiv($amount, $c['earn']); $now = now();
        $d->prepare("INSERT INTO members (phone, name, points, spent, visits, created_at, last_at) VALUES (?,?,?,?,1,?,?) ON DUPLICATE KEY UPDATE points = points + VALUES(points), spent = spent + VALUES(spent), visits = visits + 1, last_at = VALUES(last_at), name = IF(name = '', VALUES(name), name)")
          ->execute([$ph, mb_substr((string) $s['customer_name'], 0, 100), $pts, $amount, $now, $now]);
        if ($pts > 0) $d->prepare("INSERT INTO member_log (phone, session_id, code, kind, points, amount, created_at) VALUES (?,?,?,?,?,?,?)")->execute([$ph, $sid, '', 'earn', $pts, $amount, $now]);
    } catch (Throwable $e) {}
}
/** Berapa poin yang bisa ditukar untuk paket seharga $price. @return array{0:int,1:int} [poin, diskon Rp] */
function member_redeem_calc(PDO $d, string $phone, int $price): array {
    try {
        $c = member_cfg($d); $ph = norm_phone($phone); if (!$c['on'] || $ph === '' || $price <= 0) return [0, 0];
        ensure_members($d); $q = $d->prepare('SELECT points FROM members WHERE phone = ?'); $q->execute([$ph]); $have = (int) $q->fetchColumn();
        $pts = min($have, intdiv($price, $c['value']));
        return $pts < $c['min'] ? [0, 0] : [$pts, $pts * $c['value']];
    } catch (Throwable $e) { return [0, 0]; }
}
function member_take_points(PDO $d, string $ph, int $pts): bool {
    try { $u = $d->prepare('UPDATE members SET points = points - ? WHERE phone = ? AND points >= ?'); $u->execute([$pts, $ph, $pts]); return $u->rowCount() > 0; } catch (Throwable $e) { return false; }
}
function member_give_points(PDO $d, string $ph, int $pts): void {
    try { $d->prepare('UPDATE members SET points = points + ? WHERE phone = ?')->execute([$pts, $ph]); } catch (Throwable $e) {}
}
function member_log_redeem(PDO $d, string $ph, int $pts, int $disc, int $billingId): void {
    try {
        $q = $d->prepare('SELECT session_id, code FROM billings WHERE id = ?'); $q->execute([$billingId]); $b = $q->fetch(); if (!$b) return;
        $d->prepare("INSERT INTO member_log (phone, session_id, code, kind, points, amount, created_at) VALUES (?,?,?,?,?,?,?)")->execute([$ph, $b['session_id'], $b['code'], 'redeem', $pts, $disc, now()]);
    } catch (Throwable $e) {}
}
/** Pembayaran ditolak/dibatalkan -> poin yang dipakai dikembalikan (sekali saja). */
function member_refund(PDO $d, int $sid, string $code): void {
    try {
        $q = $d->prepare("SELECT phone, points FROM member_log WHERE session_id = ? AND code = ? AND kind = 'redeem'"); $q->execute([$sid, $code]); $rows = $q->fetchAll();
        if (!$rows) return;
        $c = $d->prepare("SELECT COUNT(*) FROM member_log WHERE session_id = ? AND code = ? AND kind = 'refund'"); $c->execute([$sid, $code]);
        if ((int) $c->fetchColumn() > 0) return;
        foreach ($rows as $r) {
            member_give_points($d, $r['phone'], (int) $r['points']);
            $d->prepare("INSERT INTO member_log (phone, session_id, code, kind, points, amount, created_at) VALUES (?,?,?,?,?,?,?)")->execute([$r['phone'], $sid, $code, 'refund', (int) $r['points'], 0, now()]);
        }
    } catch (Throwable $e) {}
}
/** Info tukar poin pada satu pembayaran (untuk kasir & halaman bayar). */
function member_redeem_info(PDO $d, int $sid, string $code): ?array {
    try {
        $q = $d->prepare("SELECT l.phone, l.points, l.amount, m.name FROM member_log l LEFT JOIN members m ON m.phone = l.phone WHERE l.session_id = ? AND l.code = ? AND l.kind = 'redeem' LIMIT 1");
        $q->execute([$sid, $code]); return $q->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

/** Sesi QR pelanggan berlaku 8 jam (diperpanjang selama ada sesi main/bayar yang masih terbuka). */
function expire_unit_session(PDO $d): void {
    if (empty($_SESSION['unit_id'])) return;
    if (time() - (int) ($_SESSION['unit_at'] ?? 0) <= 28800) return;
    if (open_session($d, (int) $_SESSION['unit_id'])) { $_SESSION['unit_at'] = time(); return; }
    unset($_SESSION['unit_id'], $_SESSION['welcome'], $_SESSION['unit_at']);
}
