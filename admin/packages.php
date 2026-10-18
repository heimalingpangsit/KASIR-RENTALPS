<?php
require __DIR__ . '/_l.php';
ensure_pkg_units($d);
$msg = null;
$all = $d->query('SELECT id, unit_type FROM units')->fetchAll();
$utype = []; $typeLabel = [];
foreach ($all as $u) { $utype[(int) $u['id']] = norm_type($u['unit_type']); if ($u['unit_type'] !== '') $typeLabel[norm_type($u['unit_type'])] = $u['unit_type']; }
foreach (['PS 3', 'PS 4', 'PS 5'] as $t) $typeLabel[norm_type($t)] = $typeLabel[norm_type($t)] ?? $t;
foreach ($d->query("SELECT DISTINCT unit_type FROM packages WHERE unit_type <> ''")->fetchAll() as $r) $typeLabel[norm_type($r['unit_type'])] = $typeLabel[norm_type($r['unit_type'])] ?? $r['unit_type'];
ksort($typeLabel);
$cats = array_values($typeLabel);   // label kategori: PS 3, PS 4, PS 5, ...

// Migrasi otomatis: paket lama yang dipilihkan TV-nya, kalau mencakup SEMUA TV satu tipe -> jadi paket kategori tipe itu.
foreach (package_assignments($d) as $pid => $ids) {
    $ts = array_unique(array_map(fn($i) => $utype[$i] ?? '?', $ids));
    if (count($ts) !== 1 || in_array($ts[0], ['?', ''], true)) continue;
    $total = count(array_filter($utype, fn($t) => $t === $ts[0]));
    if (count($ids) < $total) continue;
    $d->prepare('UPDATE packages SET unit_type = ? WHERE id = ?')->execute([$typeLabel[$ts[0]] ?? $ts[0], $pid]);
    $d->prepare('DELETE FROM package_units WHERE package_id = ?')->execute([$pid]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? ''; $id = (int) ($_POST['id'] ?? 0);
    $cat = trim($_POST['cat'] ?? ''); if ($cat !== '' && !in_array($cat, $cats, true)) $cat = '';
    $f = [trim($_POST['label'] ?? ''), max(1, (int) ($_POST['minutes'] ?? 0)), max(0, (int) ($_POST['price'] ?? 0)), $cat, (int) ($_POST['sort_order'] ?? 0)];
    if ($act === 'add' && $f[0] !== '') {
        $d->prepare('INSERT INTO packages (label, minutes, price, unit_type, sort_order) VALUES (?,?,?,?,?)')->execute($f);
        $msg = 'Paket ditambahkan' . ($cat !== '' ? ' ke ' . $cat : ' (semua tipe)') . '.';
    } elseif ($act === 'save') {
        $d->prepare('UPDATE packages SET label=?, minutes=?, price=?, unit_type=?, sort_order=?, is_active=? WHERE id=?')->execute([...$f, isset($_POST['is_active']) ? 1 : 0, $id]);
        $d->prepare('DELETE FROM package_units WHERE package_id = ?')->execute([$id]);   // sekarang pakai kategori, bukan pilih TV satu-satu
        $msg = 'Tersimpan.';
    } elseif ($act === 'del') {
        $d->prepare('DELETE FROM package_units WHERE package_id=?')->execute([$id]);
        $d->prepare('DELETE FROM packages WHERE id=?')->execute([$id]); $msg = 'Paket dihapus.';
    }
}
$ps = $d->query('SELECT * FROM packages ORDER BY sort_order, minutes, id')->fetchAll();
$as = package_assignments($d);
$opt = function (string $sel) use ($cats): string {
    $h = '<option value="">Semua tipe</option>';
    foreach ($cats as $c) $h .= '<option' . (norm_type($c) === norm_type($sel) && $sel !== '' ? ' selected' : '') . ' value="' . e($c) . '">' . e($c) . '</option>';
    return $h;
};
$groups = []; foreach ($cats as $c) $groups[$c] = []; $groups['Semua tipe'] = []; $legacy = [];
foreach ($ps as $p) {
    if (isset($as[(int) $p['id']])) { $legacy[] = $p; continue; }
    $k = 'Semua tipe'; foreach ($cats as $c) if ($p['unit_type'] !== '' && norm_type($c) === norm_type($p['unit_type'])) $k = $c;
    $groups[$k][] = $p;
}
a_head('Paket Billing');
flash($msg);
echo '<div class="card"><h2>Tambah paket</h2><p class="mu sm">Durasi dalam menit (1 jam = 60). Pilih kategori: paket hanya tampil di TV bertipe itu. &quot;Semua tipe&quot; = tampil di semua TV.</p><form method="post">' . csrf_field() . '<input type="hidden" name="act" value="add"><div class="inl"><input name="label" placeholder="Nama (mis. 1 Jam)" required><input name="minutes" type="number" min="1" placeholder="Menit" required style="width:100px"><input name="price" type="number" min="0" placeholder="Harga (Rp)" required style="width:140px"><select name="cat" style="width:150px">' . $opt('') . '</select><button class="btn red">Tambah</button></div></form></div>';
$row = function (array $p, string $note = '') use ($opt): string {
    $i = 'p' . $p['id'];
    return '<tr><td><form method="post" id="' . $i . '">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $p['id'] . '"></form><input form="' . $i . '" name="label" value="' . e($p['label']) . '">' . $note . '</td><td><input form="' . $i . '" name="minutes" type="number" value="' . (int) $p['minutes'] . '" style="width:80px"></td><td><input form="' . $i . '" name="price" type="number" value="' . (int) $p['price'] . '" style="width:110px"></td><td><select form="' . $i . '" name="cat" style="width:120px">' . $opt($p['unit_type']) . '</select></td><td><input form="' . $i . '" name="sort_order" type="number" value="' . (int) $p['sort_order'] . '" style="width:60px"></td><td><input form="' . $i . '" type="checkbox" name="is_active" ' . ($p['is_active'] ? 'checked' : '') . ' style="width:auto"></td><td><div class="inl"><button form="' . $i . '" name="act" value="save" class="btn sm red">Simpan</button><button form="' . $i . '" name="act" value="del" class="btn sm del" onclick="return confirm(\'Hapus paket ini?\')">Hapus</button></div></td></tr>';
};
$th = '<tr><th>Nama</th><th>Menit</th><th>Harga</th><th>Kategori</th><th>Urut</th><th>Aktif</th><th></th></tr>';
if (!$ps) echo '<div class="card c mu">Belum ada paket. Tambah dulu supaya pelanggan bisa pilih billing.</div>';
foreach ($groups as $g => $list) {
    if (!$list && $g === 'Semua tipe') continue;
    $n = count(array_filter($utype, fn($t) => $t === norm_type($g)));
    echo '<h2 class="sec">' . e($g) . ' <span class="mu sm" style="font-weight:600">' . ($g === 'Semua tipe' ? '· tampil di semua TV' : '· ' . $n . ' TV') . '</span></h2><div class="card ov">';
    if (!$list) echo '<p class="mu c" style="margin:6px">Belum ada paket untuk ' . e($g) . '. Tambah lewat form di atas.</p>';
    else { echo '<table>' . $th; foreach ($list as $p) echo $row($p); echo '</table>'; }
    echo '</div>';
}
if ($legacy) {
    echo '<h2 class="sec">Dipilih per TV (lama)</h2><div class="card ov"><p class="mu sm">Paket ini masih dipilihkan TV satu-satu, makanya cuma muncul di TV tertentu. Pilih kategori lalu Simpan supaya tampil di semua TV tipe itu.</p><table>' . $th;
    foreach ($legacy as $p) {
        $names = []; foreach ($as[(int) $p['id']] as $uid) $names[] = $uid;
        $q = $d->query('SELECT name FROM units WHERE id IN (' . implode(',', array_map('intval', $as[(int) $p['id']])) . ') ORDER BY unit_number')->fetchAll(PDO::FETCH_COLUMN);
        echo $row($p, '<div class="mu sm">Sekarang: ' . e(implode(', ', $q)) . '</div>');
    }
    echo '</table></div>';
}
a_foot();
