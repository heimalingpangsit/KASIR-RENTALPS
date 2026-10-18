<?php
require __DIR__ . '/_l.php';
if (!empty($_SESSION['staff_id'])) { header('Location: dashboard.php'); exit; }
$err = null; $first = false;
if ((int) $d->query('SELECT COUNT(*) FROM staff_users')->fetchColumn() === 0) {   // akun pertama otomatis
    $d->prepare('INSERT INTO staff_users (name, email, password_hash) VALUES (?,?,?)')->execute(['Admin', 'admin@rental.local', password_hash('admin123', PASSWORD_DEFAULT)]);
    $first = true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $q = $d->prepare('SELECT * FROM staff_users WHERE email = ?'); $q->execute([trim($_POST['email'] ?? '')]); $st = $q->fetch();
    if ($st && $st['locked_until'] && strtotime($st['locked_until']) > time()) {
        $err = 'Akun terkunci sementara karena terlalu banyak percobaan. Coba lagi nanti.';
    } elseif ($st && password_verify($_POST['password'] ?? '', $st['password_hash'])) {
        $d->prepare('UPDATE staff_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$st['id']]);
        session_regenerate_id(true);
        $_SESSION['staff_id'] = (int) $st['id']; $_SESSION['staff_name'] = $st['name'];
        header('Location: dashboard.php'); exit;
    } else {
        if ($st) {
            $n = (int) $st['failed_attempts'] + 1;
            if ($n >= 5) $d->prepare('UPDATE staff_users SET failed_attempts = 0, locked_until = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', time() + 900), $st['id']]);
            else $d->prepare('UPDATE staff_users SET failed_attempts = ? WHERE id = ?')->execute([$n, $st['id']]);
        }
        usleep(400000); $err = 'Email atau kata sandi salah.';
    }
}
head('Login Admin', '../');
echo '<main class="wrap"><div class="card"><h1>Login admin</h1>';
if ($first) echo '<div class="msg ok">Akun pertama dibuat: <b>admin@rental.local</b> / <b>admin123</b>. Segera ganti di menu Akun setelah masuk.</div>';
if ($err) echo '<div class="msg">' . e($err) . '</div>';
echo '<form method="post">' . csrf_field() . '<label class="f" for="e">Email</label><input id="e" type="email" name="email" required><label class="f" for="p">Kata sandi</label><input id="p" type="password" name="password" required><button class="btn red blk mt">Masuk</button></form></div></main>';
foot('../');
