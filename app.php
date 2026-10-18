<?php
require __DIR__ . '/config.php';
require __DIR__ . '/rental.php';
boot();
$d = db();
expire_unit_session($d);

// 1) Masuk lewat QR di sebelah TV: ?unit=3&token=xxxx
$bad = false;
if (isset($_GET['unit'], $_GET['token'])) {
    $q = $d->prepare('SELECT id FROM units WHERE unit_number = ? AND qr_token = ? AND is_active = 1');
    $q->execute([(int) $_GET['unit'], (string) $_GET['token']]);
    if ($id = $q->fetchColumn()) { $_SESSION['unit_id'] = (int) $id; $_SESSION['unit_at'] = time(); $_SESSION['welcome'] = (int) $id; header('Location: app.php'); exit; }
    http_response_code(404); $bad = true;
}
$u = null;
if (!empty($_SESSION['unit_id'])) {
    $q = $d->prepare('SELECT * FROM units WHERE id = ? AND is_active = 1'); $q->execute([$_SESSION['unit_id']]);
    $u = $q->fetch() ?: null;
}
$s = $u ? open_session($d, (int) $u['id']) : null;
if ($u && isset($_GET['go'])) { unset($_SESSION['welcome']); header('Location: app.php'); exit; }
$welcome = $u && !$s && (int) ($_SESSION['welcome'] ?? 0) === (int) $u['id'];

if (isset($_GET['poll'])) { header('Content-Type: application/json'); echo json_encode(['s' => $s['status'] ?? '', 'e' => $s['ends_at'] ?? '', 'c' => !empty($s['call_at']) ? 1 : 0]); exit; }

// 2) Mulai main / tambah jam
$err = null;
if ($u && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'call') {   // tombol 'Panggil staff' di halaman timer (dipanggil lewat fetch)
        header('Content-Type: application/json');
        echo json_encode(['ok' => $s && $s['status'] === 'aktif' && call_staff($d, (int) $s['id'])]); exit;
    }
    $method = ($_POST['method'] ?? '') === 'qris' ? 'QRIS' : 'Tunai';
    $pkg = get_package($d, (int) ($_POST['package_id'] ?? 0), $u['unit_type'], (int) $u['id']);
    if (!$pkg) {
        $err = 'Pilih paket billing dulu.';
    } elseif ($act === 'extend' && $s && $s['status'] === 'aktif') {
        $b = new_billing($d, (int) $s['id'], $pkg, $method); header('Location: pay.php?b=' . $b['id'] . '&t=' . $b['token']); exit;
    }
}

$pkgs = $u ? packages_for($d, $u['unit_type'], (int) $u['id']) : [];
function pkg_cards(array $pkgs): void {
    foreach ($pkgs as $p) echo '<label class="pkg"><input type="radio" name="package_id" value="' . (int) $p['id'] . '" required><span><b>' . e($p['label']) . '</b><small>' . e(dur((int) $p['minutes'])) . '</small></span><em>' . rp((int) $p['price']) . '</em></label>';
}
function pay_choice(): void {
    echo '<label class="f">Bayar pakai</label><div class="pay"><label class="pkg"><input type="radio" name="method" value="qris" checked> QRIS</label><label class="pkg"><input type="radio" name="method" value="tunai"> Tunai di kasir</label></div>';
}

head($u ? $u['name'] : 'Scan QR', '', $u ? '<span class="chip">' . e($u['name']) . '</span>' : '');
echo '<main class="wrap">';

if (!$u) {
    echo '<div class="card c" style="padding:44px 20px"><i class="fa-solid fa-qrcode" style="font-size:3rem;color:var(--red)"></i><h1 class="mt">' . ($bad ? 'QR tidak dikenali' : 'Scan QR di sebelah TV') . '</h1><p class="mu">' . ($bad ? 'QR ini tidak cocok dengan unit mana pun, atau unitnya sedang dinonaktifkan. Tanya operator ya.' : 'Scan kode QR yang ada di samping TV untuk mulai main dan pesan makan minum.') . '</p></div>';
} elseif ($welcome) {
    // ---------- Layar selamat datang setelah scan QR ----------
    echo '<div class="card c" style="padding:44px 20px"><i class="fa-brands fa-playstation" style="font-size:5.5rem;color:var(--red)"></i><div class="mu mt">Selamat datang di</div><h1 style="font-size:2.8rem;letter-spacing:.04em;margin:.1em 0">' . e($u['name']) . '</h1><p class="mu">Pilih paket billing dan pesan makan &amp; minum langsung dari HP.</p><a class="btn red blk mt" href="app.php?go=1">Mulai</a></div>';
} elseif (!$s) {
    // ---------- Unit kosong: pilih billing dan/atau makan & minum ----------
    $items = $d->query('SELECT * FROM menu_items ORDER BY category, sort_order, name')->fetchAll();
    $err = $_SESSION['err'] ?? $err; unset($_SESSION['err']);
    echo '<h1>Mau main berapa lama?</h1><p class="mu">Pilih paket billing dan/atau makan &amp; minum. Timer jalan setelah pembayaran dikonfirmasi.</p>';
    if ($err) echo '<div class="msg">' . e($err) . '</div>';
    if (!$pkgs && !$items) echo '<div class="card c mu">Paket billing dan menu belum diatur. Hubungi operator.</div>';
    else {
        echo '<form method="post" action="checkout.php" id="pick">' . csrf_field();
        if ($pkgs) {
            echo '<div class="card"><h2>Paket billing</h2><div class="pkgs">';
            foreach ($pkgs as $p) echo '<label class="pkg"><input type="checkbox" name="package_id" value="' . (int) $p['id'] . '" data-price="' . (int) $p['price'] . '"><span><b>' . e($p['label']) . '</b><small>' . e(dur((int) $p['minutes'])) . '</small></span><em>' . rp((int) $p['price']) . '</em></label>';
            echo '</div></div>';
        }
        if ($items) {
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
            echo '</div>';
        }
        echo '<div style="height:100px"></div><div class="bar" id="pbar" hidden><div><b id="psum">Rp 0</b><div class="mu sm" id="pcnt"></div></div><button class="btn red">Lanjut bayar</button></div></form>';
        echo '<script>(function(){var f=document.getElementById("pick");if(!f)return;var bar=document.getElementById("pbar"),sum=document.getElementById("psum"),cnt=document.getElementById("pcnt");'
           . 'function rec(){var t=0,q=0,p=0;f.querySelectorAll("input[name=package_id]:checked").forEach(function(c){t+=+c.dataset.price;p=1});'
           . 'f.querySelectorAll(".stp").forEach(function(s){var v=+s.querySelector("input").value;q+=v;t+=v*(+s.dataset.price)});'
           . 'sum.textContent="Rp "+t.toLocaleString("id-ID");cnt.textContent=(p?"1 paket billing":"")+(p&&q?" + ":"")+(q?q+" item":"");bar.hidden=!(p||q)}'
           . 'f.querySelectorAll("input[name=package_id]").forEach(function(c){c.addEventListener("change",function(){if(c.checked)f.querySelectorAll("input[name=package_id]").forEach(function(o){if(o!==c)o.checked=false});rec()})});'
           . 'f.querySelectorAll(".stp").forEach(function(s){var i=s.querySelector("input");s.addEventListener("click",function(e){var d=+e.target.dataset.d;if(!d)return;i.value=Math.max(0,Math.min(20,(+i.value)+d));rec()})});rec()})()</script>';
    }
} elseif ($s['status'] === 'menunggu_bayar') {
    // ---------- Menunggu konfirmasi billing ----------
    $q = $d->prepare("SELECT * FROM billings WHERE session_id = ? AND pay_status IN ('pending','menunggu_konfirmasi') ORDER BY id DESC LIMIT 1"); $q->execute([$s['id']]); $b = $q->fetch();
    echo '<div class="card c"><i class="fa-solid fa-hourglass-half" style="font-size:2.4rem;color:var(--red)"></i><h1 class="mt">Menunggu pembayaran</h1><p class="mu">Unit ini sedang menunggu pembayaran billing atas nama <b>' . e($s['customer_name']) . '</b>.</p>';
    if ($b) echo '<a class="btn red" href="pay.php?b=' . (int) $b['id'] . '&t=' . e($b['token']) . '">Lihat cara bayar</a>';
    echo '</div><script>function P(){if(document.hidden)return;fetch("app.php?poll=1").then(r=>r.json()).then(j=>{if(j.s!=="menunggu_bayar")location.reload()})}setInterval(P,12000);document.addEventListener("visibilitychange",P)</script>';
} else {
    // ---------- Sesi aktif ----------
    $end = strtotime($s['ends_at']); $total = max(60, (int) $s['minutes_total'] * 60);
    echo '<span data-now="' . time() . '"></span>';
    echo '<div class="card c" data-total="' . $total . '"><div class="mu sm">Sisa waktu main · ' . e($s['customer_name']) . '</div><div class="timer"><svg viewBox="0 0 200 200"><circle class="ring-bg" cx="100" cy="100" r="90"/><circle class="ring-fg" cx="100" cy="100" r="90"/></svg><div class="t"><span class="clock" data-end="' . $end . '">--:--:--</span><span class="mu sm">selesai ' . date('H:i', $end) . '</span></div></div>';
    echo '<a class="btn red blk" href="menu.php"><i class="fa-solid fa-burger"></i> Pesan makan &amp; minum</a>';
    echo '<form id="callF" onsubmit="return false">' . csrf_field() . '<input type="hidden" name="act" value="call"></form>'
       . '<button type="button" class="btn blk mt callbtn" id="callBtn"></button><p class="mu sm" style="margin:8px 0 0">Butuh bantuan? Tekan tombol di atas, staff akan datang ke meja kamu.</p></div>';

    $q = $d->prepare("SELECT * FROM billings WHERE session_id = ? AND pay_status IN ('pending','menunggu_konfirmasi') ORDER BY id DESC LIMIT 1"); $q->execute([$s['id']]);
    if ($b = $q->fetch()) echo '<div class="msg">Ada tambahan waktu yang belum dibayar (' . e($b['label']) . '). <a href="pay.php?b=' . (int) $b['id'] . '&t=' . e($b['token']) . '">Lanjut bayar</a></div>';

    echo '<details class="card"><summary><i class="fa-solid fa-clock-rotate-left" style="color:var(--red)"></i> Tambah waktu main</summary>';
    if ($err) echo '<div class="msg mt">' . e($err) . '</div>';
    if ($pkgs) { echo '<form method="post" class="mt">' . csrf_field() . '<input type="hidden" name="act" value="extend"><div class="pkgs">'; pkg_cards($pkgs); echo '</div>'; pay_choice(); echo '<button class="btn red blk mt">Tambah waktu</button></form>'; }
    else echo '<p class="mu">Belum ada paket billing.</p>';
    echo '</details>';

    $q = $d->prepare('SELECT * FROM orders WHERE session_id = ? ORDER BY id DESC'); $q->execute([$s['id']]); $os = $q->fetchAll();
    if ($os) {
        echo '<div class="card"><h2>Pesanan kamu</h2>';
        $lbl = ['menunggu_bayar' => 'Menunggu bayar', 'baru_masuk' => 'Sedang disiapkan', 'selesai' => 'Sudah diantar', 'dibatalkan' => 'Dibatalkan'];
        foreach ($os as $o) {
            $it = $d->prepare('SELECT qty, item_name FROM order_items WHERE order_id = ?'); $it->execute([$o['id']]);
            $txt = implode(', ', array_map(fn($r) => $r['qty'] . 'x ' . $r['item_name'], $it->fetchAll()));
            echo '<div class="row"><div><b>' . e($txt) . '</b><div class="mu sm">' . rp((int) $o['subtotal']) . '</div></div><div>';
            if ($o['pay_status'] === 'lunas') echo '<span class="pill ok">' . e($lbl[$o['status']] ?? $o['status']) . '</span>';
            elseif ($o['status'] === 'dibatalkan') echo '<span class="pill r">Dibatalkan</span>';
            else echo '<a class="pill w" href="pay.php?o=' . (int) $o['id'] . '&t=' . e($o['token']) . '">Bayar</a>';
            echo '</div></div>';
        }
        echo '</div>';
    }
    echo '<script>(function(){var cur=' . json_encode($s['ends_at']) . ',cb=document.getElementById("callBtn"),f=document.getElementById("callF");'
       . 'function setCall(on){cb.disabled=on;cb.classList.toggle("called",on);cb.innerHTML=on?"<i class=\\"fa-solid fa-bell fa-shake\\"></i> Staff dipanggil &middot; mohon tunggu sebentar":"<i class=\\"fa-solid fa-bell-concierge\\"></i> Panggil staff"}'
       . 'setCall(' . (!empty($s['call_at']) ? 'true' : 'false') . ');'
       . 'cb.addEventListener("click",function(){if(cb.disabled)return;cb.disabled=true;fetch("app.php",{method:"POST",body:new FormData(f)}).then(function(r){return r.json()}).then(function(j){if(j.ok)setCall(true);else location.reload()}).catch(function(){cb.disabled=false;alert("Gagal memanggil staff, coba lagi.")})});'
       . 'function P(){if(document.hidden)return;fetch("app.php?poll=1").then(function(r){return r.json()}).then(function(j){if(j.s!=="aktif"||j.e!==cur){if(!document.querySelector("details[open]"))location.reload();return}setCall(!!j.c)})}setInterval(P,12000);document.addEventListener("visibilitychange",P)})()</script>';
    // Peringatan waktu hampir habis di HP pelanggan (5 menit terakhir + saat habis)
    echo <<<'JS'
<script>(function(){var ce=document.querySelector("[data-end]"),nw=document.querySelector("[data-now]"),dt=document.querySelector("details.card");if(!ce||!nw||!dt)return;
var off=(+nw.dataset.now)-Date.now()/1000,end=+ce.dataset.end,st=0,wb=document.createElement("div");wb.className="msg";wb.hidden=true;wb.style.cssText="font-weight:700;cursor:pointer";dt.parentNode.insertBefore(wb,dt);
wb.addEventListener("click",function(){dt.open=true;dt.scrollIntoView({behavior:"smooth"})});
function chk(){var l=end-(Date.now()/1000+off),s=l<=0?2:(l<=300?1:0);if(s===st)return;st=s;
if(s===0){wb.hidden=true;return}wb.hidden=false;
wb.textContent=s===2?"Waktu main habis. Ketuk di sini untuk tambah waktu.":"Sisa waktu kurang dari 5 menit. Ketuk di sini untuk tambah waktu.";
if(navigator.vibrate)navigator.vibrate([250,120,250])}
chk();setInterval(chk,1000)})()</script>
JS;
}
echo '</main>';
foot();
