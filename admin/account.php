<?php
require __DIR__ . '/_l.php';
$msg = null; $cls = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $q = $d->prepare('SELECT * FROM staff_users WHERE id = ?'); $q->execute([$sid]); $st = $q->fetch();
    $new = (string) ($_POST['new'] ?? '');
    if (!password_verify($_POST['old'] ?? '', $st['password_hash'])) { $cls = ''; $msg = 'Kata sandi lama salah.'; }
    elseif (strlen($new) < 8) { $cls = ''; $msg = 'Kata sandi baru minimal 8 karakter.'; }
    else {
        $email = trim($_POST['email'] ?? '') ?: $st['email'];
        $d->prepare('UPDATE staff_users SET email = ?, name = ?, password_hash = ? WHERE id = ?')->execute([$email, trim($_POST['name'] ?? '') ?: $st['name'], password_hash($new, PASSWORD_DEFAULT), $sid]);
        $msg = 'Akun diperbarui.';
    }
}
$q = $d->prepare('SELECT * FROM staff_users WHERE id = ?'); $q->execute([$sid]); $st = $q->fetch();
a_head('Akun');
flash($msg, $cls);
echo '<div class="card" style="max-width:480px"><form method="post">' . csrf_field() . '<label class="f">Nama</label><input name="name" value="' . e($st['name']) . '"><label class="f">Email login</label><input type="email" name="email" value="' . e($st['email']) . '"><label class="f">Kata sandi lama</label><input type="password" name="old" required><label class="f">Kata sandi baru (min. 8 karakter)</label><input type="password" name="new" required><button class="btn red mt">Simpan</button></form></div>';
a_foot();
