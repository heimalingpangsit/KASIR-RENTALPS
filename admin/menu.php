<?php
require __DIR__ . '/_l.php';
$msg = null; $cls = 'ok';
function save_img(): ?string {   // upload foto menu (jpg/png/webp, maks 2 MB), nama file acak
    if (empty($_FILES['image']['tmp_name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) return null;
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][mime_content_type($_FILES['image']['tmp_name'])] ?? null;
    if (!$ext || $_FILES['image']['size'] > 2097152) return null;
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    return move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../assets/uploads/' . $name) ? 'assets/uploads/' . $name : null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? ''; $id = (int) ($_POST['id'] ?? 0);
    $f = [trim($_POST['category'] ?? '') ?: 'Lainnya', trim($_POST['name'] ?? ''), trim($_POST['description'] ?? ''), max(0, (int) ($_POST['price'] ?? 0)), ($_POST['stock_status'] ?? '') === 'habis' ? 'habis' : 'tersedia'];
    $cost = max(0, (int) ($_POST['cost'] ?? 0));
    $st = trim((string) ($_POST['stock_qty'] ?? '')) === '' ? null : (int) $_POST['stock_qty'];   // kosong = stok tidak dihitung
    if ($st !== null && $st <= 0) $f[4] = 'habis';
    if ($act === 'save' && $st !== null && $st > 0 && $f[4] === 'habis') {   // isi stok lagi dari 0 -> otomatis tersedia
        $cq = $d->prepare('SELECT stock_qty FROM menu_items WHERE id = ?'); $cq->execute([$id]); $cv = $cq->fetchColumn();
        if ($cv !== false && $cv !== null && (int) $cv <= 0) $f[4] = 'tersedia';
    }
    $img = save_img();
    if ($act === 'add' && $f[1] !== '') { $d->prepare('INSERT INTO menu_items (category, name, description, price, stock_status, image, cost, stock_qty) VALUES (?,?,?,?,?,?,?,?)')->execute([...$f, $img ?? '', $cost, $st]); $msg = 'Menu ditambahkan.'; }
    elseif ($act === 'save') {
        $d->prepare('UPDATE menu_items SET category=?, name=?, description=?, price=?, stock_status=?, cost=?, stock_qty=? WHERE id=?')->execute([...$f, $cost, $st, $id]);
        if ($img) $d->prepare('UPDATE menu_items SET image=? WHERE id=?')->execute([$img, $id]);
        $msg = 'Tersimpan.';
    } elseif ($act === 'del') { $d->prepare('DELETE FROM menu_items WHERE id=?')->execute([$id]); $msg = 'Menu dihapus.'; }
    if (!empty($_FILES['image']['name']) && !$img) { $cls = ''; $msg = 'Foto ditolak (harus JPG/PNG/WEBP, maks 2 MB).'; }
}
$ms = $d->query('SELECT * FROM menu_items ORDER BY category, name')->fetchAll();
$cats = array_unique(array_column($ms, 'category'));
a_head('Menu Makan & Minum');
flash($msg, $cls);
echo '<datalist id="cats">' . implode('', array_map(fn($c) => '<option value="' . e($c) . '">', array_merge($cats, ['Makanan', 'Minuman', 'Snack']))) . '</datalist>';
echo '<div class="card"><h2>Tambah menu</h2><form method="post" enctype="multipart/form-data" class="inl">' . csrf_field() . '<input type="hidden" name="act" value="add"><input name="category" list="cats" placeholder="Kategori" style="width:130px" required><input name="name" placeholder="Nama menu" required><input name="description" placeholder="Deskripsi (opsional)"><input name="price" type="number" min="0" placeholder="Harga" required style="width:110px"><input name="cost" type="number" min="0" placeholder="HPP (modal)" style="width:110px"><input name="stock_qty" type="number" placeholder="Stok (kosong = tak dihitung)" style="width:190px"><input type="file" name="image" accept="image/jpeg,image/png,image/webp" style="width:200px"><button class="btn red">Tambah</button></form></div>';
if (!$ms) echo '<div class="card c mu">Menu masih kosong — isi sendiri di atas.</div>';
else {
    echo '<div class="card ov"><table><tr><th></th><th>Kategori</th><th>Nama</th><th>Deskripsi</th><th>Harga</th><th>HPP</th><th>Jml stok</th><th>Status</th><th></th></tr>';
    $fx = '';
foreach ($ms as $m) {
        $i = 'm' . $m['id'];
        $fx .= '<form method="post" enctype="multipart/form-data" id="' . $i . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $m['id'] . '"></form>'; echo '<tr><td>' . ($m['image'] ? '<img src="../' . e($m['image']) . '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:8px">' : '') . '<input form="' . $i . '" type="file" name="image" accept="image/*" style="width:110px;font-size:.7rem"></td>'
           . '<td><input form="' . $i . '" name="category" list="cats" value="' . e($m['category']) . '" style="width:110px"></td><td><input form="' . $i . '" name="name" value="' . e($m['name']) . '"></td><td><input form="' . $i . '" name="description" value="' . e($m['description']) . '"></td><td><input form="' . $i . '" name="price" type="number" value="' . (int) $m['price'] . '" style="width:100px"></td>'
           . '<td><input form="' . $i . '" name="cost" type="number" min="0" value="' . (int) ($m['cost'] ?? 0) . '" style="width:90px"></td><td><input form="' . $i . '" name="stock_qty" type="number" value="' . (($m['stock_qty'] ?? null) === null ? '' : (int) $m['stock_qty']) . '" placeholder="∞" style="width:80px"></td>'
           . '<td><select form="' . $i . '" name="stock_status"><option value="tersedia"' . ($m['stock_status'] === 'tersedia' ? ' selected' : '') . '>Tersedia</option><option value="habis"' . ($m['stock_status'] === 'habis' ? ' selected' : '') . '>Habis</option></select></td>'
           . '<td><div class="inl"><button form="' . $i . '" name="act" value="save" class="btn sm red">Simpan</button><button form="' . $i . '" name="act" value="del" class="btn sm del" onclick="return confirm(\'Hapus menu ini?\')">Hapus</button></div></td></tr>';
    }
    echo '</table></div>' . $fx;
}
a_foot();
