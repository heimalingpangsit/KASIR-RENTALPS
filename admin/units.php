<?php
require __DIR__ . '/_l.php';
$msg = null; $cls = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? ''; $id = (int) ($_POST['id'] ?? 0);
    $ut = trim($_POST['unit_type'] ?? ''); $nm = trim($_POST['name'] ?? '');
    try {
        if ($act === 'add') {
            $n = (int) $_POST['unit_number'];
            if ($n < 1) throw new InvalidArgumentException('Nomor TV harus 1 atau lebih.');
            $d->prepare('INSERT INTO units (unit_number, name, unit_type, qr_token) VALUES (?,?,?,?)')->execute([$n, $nm !== '' ? $nm : 'PS ' . $n, $ut, bin2hex(random_bytes(16))]);
            $msg = 'Unit ditambahkan.';
        } elseif ($act === 'save') {
            if ((int) $_POST['unit_number'] < 1) throw new InvalidArgumentException('Nomor TV harus 1 atau lebih.');
            if ($nm === '') $nm = 'PS ' . (int) $_POST['unit_number'];
            $d->prepare('UPDATE units SET unit_number=?, name=?, unit_type=?, is_active=? WHERE id=?')->execute([(int) $_POST['unit_number'], $nm, $ut, isset($_POST['is_active']) ? 1 : 0, $id]);
            $msg = 'Tersimpan.';
        } elseif ($act === 'token') {
            $d->prepare('UPDATE units SET qr_token=? WHERE id=?')->execute([bin2hex(random_bytes(16)), $id]);
            $msg = 'Token QR diganti. Cetak ulang QR unit ini — QR lama tidak berlaku lagi.';
        } elseif ($act === 'del') {
            $d->prepare('DELETE FROM units WHERE id=?')->execute([$id]); $msg = 'Unit dihapus.';
        }
    } catch (InvalidArgumentException $ex) { $cls = ''; $msg = $ex->getMessage();
    } catch (PDOException $ex) {
        $cls = ''; $constraint = (string) $ex->getCode() === '23000';   // pelanggaran UNIQUE / foreign key
        $msg = !$constraint ? 'Gagal menyimpan (database): ' . $ex->getMessage()
            : ($act === 'del' ? 'Unit punya riwayat sesi, nonaktifkan saja.' : 'Nomor TV itu sudah dipakai (termasuk TV yang nonaktif). Pakai nomor lain.');
    }
}
$us = $d->query('SELECT * FROM units ORDER BY unit_number')->fetchAll();
$types = [];   // daftar tipe: PS 3, PS 4, PS 5 + tipe lain yang sudah dipakai di database
foreach (['PS 3', 'PS 4', 'PS 5'] as $t) $types[norm_type($t)] = $t;
foreach ($us as $u) if (trim($u['unit_type']) !== '') $types[norm_type($u['unit_type'])] = $types[norm_type($u['unit_type'])] ?? trim($u['unit_type']);
$typeOpt = function (string $sel) use ($types): string {
    $h = '<option value="">Tanpa tipe</option>';
    foreach ($types as $k => $t) $h .= '<option value="' . e($t) . '"' . ($sel !== '' && norm_type($sel) === $k ? ' selected' : '') . '>' . e($t) . '</option>';
    return $h;
};
$next = (int) $d->query('SELECT COALESCE(MAX(unit_number), 0) + 1 FROM units')->fetchColumn();   // nomor kosong berikutnya
$GLOBALS['noh1'] = true;
a_head('Unit & QR');
flash($msg, $cls);
$nAktif = count(array_filter($us, fn($u) => $u['is_active']));
echo '<div class="stat"><div class="card"><b>' . count($us) . '</b><span class="mu sm">Total TV</span></div><div class="card"><b>' . $nAktif . '</b><span class="mu sm">TV Aktif</span></div><div class="card"><b>' . (count($us) - $nAktif) . '</b><span class="mu sm">Nonaktif</span></div></div>';
echo '<div class="inl" style="margin-bottom:18px"><button type="button" class="btn red" onclick="var d=document.getElementById(\'dadd\');if(d)d.showModal()"><i class="fa-solid fa-plus"></i> Tambah TV</button><a class="btn" href="print-qr.php" target="_blank"><i class="fa-solid fa-print"></i> Cetak semua QR</a></div>';
echo '<h2 class="sec">Daftar TV</h2><div class="tvs">';
$dlg = '<dialog class="dlg" id="dadd"><div class="dh"><b><i class="fa-solid fa-plus"></i> Tambah TV</b><button type="button" class="x" onclick="this.closest(\'dialog\').close()" aria-label="Tutup">&times;</button></div><div class="db"><form method="post">' . csrf_field() . '<input type="hidden" name="act" value="add"><label class="f">Nomor</label><input name="unit_number" type="number" min="1" value="' . $next . '" required><label class="f">Nama</label><input name="name" placeholder="Nama (mis. PS 1)"><label class="f">Tipe</label><select name="unit_type">' . $typeOpt('') . '</select><button class="btn red blk mt">Tambah</button></form></div></dialog>';
foreach ($us as $u) {
    $id = (int) $u['id'];
    echo '<button type="button" class="tv' . ($u['is_active'] ? '' : ' off') . '" onclick="var d=document.getElementById(\'u' . $id . '\');if(d)d.showModal()"><b>' . e($u['name']) . '</b><i class="dot"></i><small>' . ($u['is_active'] ? ($u['unit_type'] ? e(strtoupper($u['unit_type'])) : 'AKTIF') : 'NONAKTIF') . '</small></button>';
    $dlg .= '<dialog class="dlg" id="u' . $id . '"><div class="dh"><b><i class="fa-solid fa-tv"></i> ' . e($u['name']) . '</b><button type="button" class="x" onclick="this.closest(\'dialog\').close()" aria-label="Tutup">&times;</button></div><div class="db"><form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<label class="f">Nomor</label><input name="unit_number" type="number" min="1" value="' . (int) $u['unit_number'] . '">'
        . '<label class="f">Nama</label><input name="name" value="' . e($u['name']) . '">'
        . '<label class="f">Tipe</label><select name="unit_type">' . $typeOpt($u['unit_type']) . '</select>'
        . '<label class="f chk"><input type="checkbox" name="is_active" ' . ($u['is_active'] ? 'checked' : '') . '> Aktif</label>'
        . '<div class="inl mt"><button name="act" value="save" class="btn sm red">Simpan</button><a class="btn sm" target="_blank" href="print-qr.php?id=' . $id . '"><i class="fa-solid fa-qrcode"></i> QR</a>'
        . '<button name="act" value="token" class="btn sm" onclick="return confirm(\'Ganti token? QR lama jadi tidak berlaku.\')">Ganti token</button>'
        . '<button name="act" value="del" class="btn sm del" onclick="return confirm(\'Hapus unit ini?\')">Hapus</button></div></form></div></dialog>';
}
echo '</div>' . $dlg;
a_foot();
